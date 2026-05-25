<?php

namespace App\Services;

use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceService
{
    /**
     * Create a complete purchase invoice with items, batches and inbound movements.
     *
     * @param array $data    ['supplier_id','invoice_number','invoice_date','discount','tax','status']
     * @param array $items   each: ['product_id','quantity','unit_price','batch_number','expiry_date']
     */
    public function create(array $data, array $items): PurchaseInvoice
    {
        return DB::transaction(function () use ($data, $items) {
            $subtotal = collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
            $discount = (float) ($data['discount'] ?? 0);
            $tax      = (float) ($data['tax'] ?? 0);
            $total    = $subtotal - $discount + $tax;

            $invoice = PurchaseInvoice::create([
                'supplier_id'    => $data['supplier_id'],
                'created_by'     => Auth::id(),
                'invoice_number' => $data['invoice_number'],
                'invoice_date'   => $data['invoice_date'],
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'tax'            => $tax,
                'total'          => $total,
                'status'         => $data['status'] ?? 'draft',
            ]);

            foreach ($items as $i) {
                $item = PurchaseItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'product_id'          => $i['product_id'],
                    'quantity'            => $i['quantity'],
                    'unit_price'          => $i['unit_price'],
                    'total'               => $i['quantity'] * $i['unit_price'],
                    'batch_number'        => $i['batch_number'],
                    'expiry_date'         => $i['expiry_date'],
                ]);

                // Only completed invoices materialise stock.
                if ($invoice->status === 'completed') {
                    ProductBatch::create([
                        'product_id'       => $i['product_id'],
                        'purchase_item_id' => $item->id,
                        'batch_number'     => $i['batch_number'],
                        'expiry_date'      => $i['expiry_date'],
                        'quantity'         => $i['quantity'],
                        'purchase_price'   => $i['unit_price'],
                    ]);

                    StockMovement::create([
                        'product_id'     => $i['product_id'],
                        'created_by'     => Auth::id(),
                        'type'           => StockMovement::TYPE_IN,
                        'quantity'       => $i['quantity'],
                        'reference_type' => StockMovement::REF_PURCHASE,
                        'reference_id'   => $invoice->id,
                        'notes'          => "Batch {$i['batch_number']} received",
                    ]);
                }
            }

            return $invoice->load('purchaseItems');
        });
    }

    public function complete(PurchaseInvoice $invoice): PurchaseInvoice
    {
        if ($invoice->status === 'completed') {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice) {
            foreach ($invoice->purchaseItems as $item) {
                ProductBatch::create([
                    'product_id'       => $item->product_id,
                    'purchase_item_id' => $item->id,
                    'batch_number'     => $item->batch_number,
                    'expiry_date'      => $item->expiry_date,
                    'quantity'         => $item->quantity,
                    'purchase_price'   => $item->unit_price,
                ]);

                StockMovement::create([
                    'product_id'     => $item->product_id,
                    'created_by'     => Auth::id(),
                    'type'           => StockMovement::TYPE_IN,
                    'quantity'       => $item->quantity,
                    'reference_type' => StockMovement::REF_PURCHASE,
                    'reference_id'   => $invoice->id,
                    'notes'          => "Batch {$item->batch_number} received",
                ]);
            }

            $invoice->update(['status' => 'completed']);
            return $invoice;
        });
    }
}
