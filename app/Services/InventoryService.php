<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Collection;

class InventoryService
{
    /** Sellable stock: positive quantity in non-expired batches only. */
    public function availableStock(int $productId): int
    {
        return (int) ProductBatch::query()
            ->sellable()
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    /** FEFO-ordered sellable batches for a product. */
    public function fefoBatchesFor(int $productId, ?int $needed = null): Collection
    {
        $batches = ProductBatch::query()
            ->sellable()
            ->where('product_id', $productId)
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();

        if ($needed === null) {
            return $batches;
        }

        $picked = collect();
        $remaining = $needed;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $batch->quantity);

            $picked->push([
                'batch' => $batch,
                'take' => $take,
                'expiry_date' => $batch->expiry_date,
            ]);

            $remaining -= $take;
        }

        return $picked;
    }

    public function lowStockProducts(): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Product $product): bool => $product->current_stock <= $product->minimum_stock)
            ->values();
    }

    public function expiringBatches(int $withinDays = 30): Collection
    {
        return ProductBatch::query()
            ->sellable()
            ->whereDate('expiry_date', '<=', today()->addDays($withinDays))
            ->orderBy('expiry_date')
            ->with('product')
            ->get();
    }

    public function expiredBatches(): Collection
    {
        return ProductBatch::query()
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<', today())
            ->orderBy('expiry_date')
            ->with('product')
            ->get();
    }

    public function totalAvailableUnits(): int
    {
        return (int) ProductBatch::query()->sellable()->sum('quantity');
    }

    public function totalStockValue(): float
    {
        return (float) ProductBatch::query()
            ->sellable()
            ->selectRaw('COALESCE(SUM(quantity * purchase_price), 0) AS v')
            ->value('v');
    }

    public function physicalStockValue(): float
    {
        return (float) ProductBatch::query()
            ->selectRaw('COALESCE(SUM(quantity * purchase_price), 0) AS v')
            ->value('v');
    }
}
