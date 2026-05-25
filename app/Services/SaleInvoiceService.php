<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\ProductBatch;
use App\Models\SaleInvoice;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleInvoiceService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * Create a sale invoice using FEFO (First Expired, First Out).
     *
     * @param array $data  ['invoice_number','invoice_date','discount','tax','payment_method']
     * @param array $items each: ['product_id','quantity','unit_price']
     *
     * @throws InsufficientStockException
     */
    public function create(array $data, array $items): SaleInvoice
    {
        return DB::transaction(function () use ($data, $items) {
            // 1. Pre-validate stock for every line BEFORE writing anything.
            foreach ($items as $i) {
                $available = $this->inventory->availableStock($i['product_id']);
                if ($available < $i['quantity']) {
                    throw new InsufficientStockException(
                        "Product #{$i['product_id']} has only {$available} units, requested {$i['quantity']}."
                    );
                }
            }

            // 2. Create invoice header (subtotal/total computed after splitting).
            $discount = (float) ($data['discount'] ?? 0);
            $tax      = (float) ($data['tax'] ?? 0);

            $invoice = SaleInvoice::create([
                'created_by'     => Auth::id(),
                'invoice_number' => $data['invoice_number'],
                'invoice_date'   => $data['invoice_date'] ?? now(),
                'customer_name'  => $data['customer_name']  ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'subtotal'       => 0,
                'discount'       => $discount,
                'tax'            => $tax,
                'total'          => 0,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'status'         => 'completed',
            ]);

            $subtotal = 0.0;

            foreach ($items as $i) {
                $remaining = $i['quantity'];

                // 3. FEFO batches for this product, locked for update.
                $batches = ProductBatch::query()
                    ->where('product_id', $i['product_id'])
                    ->where('quantity', '>', 0)
                    ->whereDate('expiry_date', '>=', today()) // exclude expired
                    ->orderBy('expiry_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min($remaining, $batch->quantity);

                    SaleItem::create([
                        'sale_invoice_id'        => $invoice->id,
                        'product_id'             => $i['product_id'],
                        'product_batch_id'       => $batch->id,
                        'quantity'               => $take,
                        'unit_price'             => $i['unit_price'],
                        'purchase_price_at_sale' => $batch->purchase_price,
                        'total'                  => $take * $i['unit_price'],
                    ]);

                    $batch->decrement('quantity', $take);

                    StockMovement::create([
                        'product_id'     => $i['product_id'],
                        'created_by'     => Auth::id(),
                        'type'           => StockMovement::TYPE_OUT,
                        'quantity'       => $take,
                        'reference_type' => StockMovement::REF_SALE,
                        'reference_id'   => $invoice->id,
                        'notes'          => "FEFO from batch {$batch->batch_number}",
                    ]);

                    $remaining -= $take;
                    $subtotal  += $take * $i['unit_price'];
                }

                if ($remaining > 0) {
                    // Should never happen because of pre-validation, but guard anyway.
                    throw new InsufficientStockException(
                        "Stock disappeared mid-transaction for product #{$i['product_id']}."
                    );
                }
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'total'    => $subtotal - $discount + $tax,
            ]);

            return $invoice->load('saleItems');
        });
    }

    /**
     * TODO: implement reversal — restore batch quantities and write `adjust` movements.
     * Keeping as documented limitation per README §6.
     */
    public function cancel(SaleInvoice $invoice): SaleInvoice
    {
        $invoice->update(['status' => 'cancelled']);
        return $invoice;
    }
}
