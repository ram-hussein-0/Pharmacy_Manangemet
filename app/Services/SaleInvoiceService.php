<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\ProductBatch;
use App\Models\SaleInvoice;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SaleInvoiceService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Create a sale invoice using FEFO (First Expiry, First Out).
     *
     * @param array $data  ['invoice_number','invoice_date','customer_name','customer_phone','discount','tax','payment_method']
     * @param array $items each: ['product_id','quantity','unit_price']
     *
     * @throws InsufficientStockException
     */
    public function create(array $data, array $items): SaleInvoice
    {
        if ($items === []) {
            throw new InvalidArgumentException('A sale invoice must contain at least one item.');
        }

        return DB::transaction(function () use ($data, $items) {
            $userId = $this->currentUserId();

            $normalizedItems = collect($items)
                ->map(fn (array $item): array => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                ])
                ->values();

            foreach ($normalizedItems as $item) {
                if ($item['quantity'] < 1) {
                    throw new InvalidArgumentException('Sale item quantity must be at least 1.');
                }

                if ($item['unit_price'] < 0) {
                    throw new InvalidArgumentException('Sale item unit price cannot be negative.');
                }
            }

            /*
             * Lock FEFO batches first and validate aggregated requested quantity
             * per product before writing the invoice header.
             */
            $requestedByProduct = $normalizedItems
                ->groupBy('product_id')
                ->map(fn ($lines): int => (int) $lines->sum('quantity'));

            $lockedBatchesByProduct = [];

            foreach ($requestedByProduct as $productId => $requestedQuantity) {
                $batches = ProductBatch::query()
                    ->where('product_id', (int) $productId)
                    ->where('quantity', '>', 0)
                    ->whereDate('expiry_date', '>=', today())
                    ->orderBy('expiry_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $available = (int) $batches->sum('quantity');

                if ($available < $requestedQuantity) {
                    throw new InsufficientStockException(
                        "Product #{$productId} has only {$available} units, requested {$requestedQuantity}."
                    );
                }

                $lockedBatchesByProduct[(int) $productId] = $batches;
            }

            $discount = (float) ($data['discount'] ?? 0);
            $tax = (float) ($data['tax'] ?? 0);

            $invoice = SaleInvoice::create([
                'created_by' => $userId,
                'invoice_number' => $data['invoice_number'],
                'invoice_date' => $data['invoice_date'] ?? now(),
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'subtotal' => 0,
                'discount' => $discount,
                'tax' => $tax,
                'total' => 0,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'status' => 'completed',
            ]);

            $subtotal = 0.0;

            foreach ($normalizedItems as $item) {
                $remaining = $item['quantity'];
                $batches = $lockedBatchesByProduct[$item['product_id']] ?? collect();

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $availableInBatch = (int) $batch->quantity;

                    if ($availableInBatch <= 0) {
                        continue;
                    }

                    $take = min($remaining, $availableInBatch);
                    $lineTotal = $take * $item['unit_price'];

                    SaleItem::create([
                        'sale_invoice_id' => $invoice->getKey(),
                        'product_id' => $item['product_id'],
                        'product_batch_id' => $batch->getKey(),
                        'quantity' => $take,
                        'unit_price' => $item['unit_price'],
                        'purchase_price_at_sale' => $batch->purchase_price,
                        'total' => $lineTotal,
                    ]);

                    $batch->decrement('quantity', $take);

                    /*
                     * Keep the in-memory locked batch quantity in sync so repeated
                     * lines for the same product continue consuming correctly.
                     */
                    $batch->quantity = $availableInBatch - $take;

                    StockMovement::create([
                        'product_id' => $item['product_id'],
                        'created_by' => $userId,
                        'type' => StockMovement::TYPE_OUT,
                        'quantity' => $take,
                        'reference_type' => StockMovement::REF_SALE,
                        'reference_id' => $invoice->getKey(),
                        'notes' => "FEFO from batch {$batch->batch_number}",
                    ]);

                    $remaining -= $take;
                    $subtotal += $lineTotal;
                }

                if ($remaining > 0) {
                    throw new InsufficientStockException(
                        "Stock disappeared mid-transaction for product #{$item['product_id']}."
                    );
                }
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal - $discount + $tax,
            ]);

            return $invoice->refresh()->load(['saleItems.product', 'saleItems.productBatch']);
        });
    }

    /**
     * Sale reversal is intentionally not implemented yet.
     *
     * A safe cancellation must restore batch quantities and write adjustment
     * stock movements. Updating status only would corrupt stock and reports.
     */
    public function cancel(SaleInvoice $invoice): SaleInvoice
    {
        throw new RuntimeException('Sale cancellation is not implemented safely yet.');
    }

    private function currentUserId(): int
    {
        $authId = Auth::id();

        if ($authId === null) {
            throw new RuntimeException('Cannot create a sale invoice without an authenticated user.');
        }

        return (int) $authId;
    }
}
