<?php

namespace App\Services;

use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PurchaseInvoiceService
{
    /**
     * Create a purchase invoice with its items.
     *
     * If status is completed, stock is materialised immediately by creating
     * product_batches and inbound stock_movements inside the same transaction.
     *
     * @param array $data  ['supplier_id','invoice_number','invoice_date','discount','tax','status']
     * @param array $items each: ['product_id','quantity','unit_price','batch_number','expiry_date']
     */
    public function create(array $data, array $items): PurchaseInvoice
    {
        if ($items === []) {
            throw new InvalidArgumentException('A purchase invoice must contain at least one item.');
        }

        return DB::transaction(function () use ($data, $items) {
            $subtotal = collect($items)->sum(fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price']);
            $discount = (float) ($data['discount'] ?? 0);
            $tax = (float) ($data['tax'] ?? 0);
            $total = $subtotal - $discount + $tax;

            $invoice = PurchaseInvoice::create([
                'supplier_id' => $data['supplier_id'],
                'created_by' => $this->currentUserId(),
                'invoice_number' => $data['invoice_number'],
                'invoice_date' => $data['invoice_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'status' => $data['status'] ?? 'draft',
            ]);

            foreach ($items as $itemData) {
                $item = PurchaseItem::create([
                    'purchase_invoice_id' => $invoice->getKey(),
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => (float) $itemData['quantity'] * (float) $itemData['unit_price'],
                    'batch_number' => $itemData['batch_number'],
                    'expiry_date' => $itemData['expiry_date'],
                ]);

                if ($invoice->status === 'completed') {
                    $this->materialiseItemStock($invoice, $item);
                }
            }

            return $invoice->refresh()->load('purchaseItems');
        });
    }

    /**
     * Update header-only fields for a draft/pending purchase invoice.
     *
     * Items, status, stock batches, and stock movements are not changed here.
     * Completion must happen through complete().
     */
    public function updateHeader(PurchaseInvoice $invoice, array $data): PurchaseInvoice
    {
        if (! in_array($invoice->status, ['draft', 'pending'], true)) {
            throw new RuntimeException('Cannot update a completed or cancelled purchase invoice.');
        }

        return DB::transaction(function () use ($invoice, $data) {
            $allowed = array_intersect_key($data, array_flip([
                'supplier_id',
                'invoice_number',
                'invoice_date',
                'discount',
                'tax',
            ]));

            $subtotal = (float) $invoice->purchaseItems()->sum('total');
            $discount = array_key_exists('discount', $allowed) ? (float) $allowed['discount'] : (float) $invoice->discount;
            $tax = array_key_exists('tax', $allowed) ? (float) $allowed['tax'] : (float) $invoice->tax;

            $allowed['subtotal'] = $subtotal;
            $allowed['discount'] = $discount;
            $allowed['tax'] = $tax;
            $allowed['total'] = $subtotal - $discount + $tax;

            $invoice->update($allowed);

            return $invoice->refresh()->load('purchaseItems');
        });
    }

    /**
     * Complete a draft/pending invoice and materialise stock once.
     */
    public function complete(PurchaseInvoice $invoice): PurchaseInvoice
    {
        if ($invoice->status === 'completed') {
            return $invoice;
        }

        if (! in_array($invoice->status, ['draft', 'pending'], true)) {
            throw new RuntimeException('Only draft or pending purchase invoices can be completed.');
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->loadMissing('purchaseItems');

            foreach ($invoice->purchaseItems as $item) {
                $this->materialiseItemStock($invoice, $item);
            }

            $invoice->update(['status' => 'completed']);

            return $invoice->refresh()->load('purchaseItems');
        });
    }

    private function materialiseItemStock(PurchaseInvoice $invoice, PurchaseItem $item): void
    {
        $alreadyMaterialised = ProductBatch::query()
            ->where('purchase_item_id', $item->getKey())
            ->exists();

        if ($alreadyMaterialised) {
            return;
        }

        ProductBatch::create([
            'product_id' => $item->product_id,
            'purchase_item_id' => $item->getKey(),
            'batch_number' => $item->batch_number,
            'expiry_date' => $item->expiry_date,
            'quantity' => $item->quantity,
            'purchase_price' => $item->unit_price,
        ]);

        StockMovement::create([
            'product_id' => $item->product_id,
            'created_by' => $this->currentUserId(),
            'type' => StockMovement::TYPE_IN,
            'quantity' => $item->quantity,
            'reference_type' => StockMovement::REF_PURCHASE,
            'reference_id' => $invoice->getKey(),
            'notes' => "Batch {$item->batch_number} received",
        ]);
    }

    private function currentUserId(): int
    {
        $authId = Auth::id();

        if ($authId === null) {
            throw new RuntimeException('Cannot process a purchase invoice without an authenticated user.');
        }

        return (int) $authId;
    }
}
