<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Collection;

class InventoryService
{
    /** Total available stock for a product across all batches. */
    public function availableStock(int $productId): int
    {
        return (int) ProductBatch::where('product_id', $productId)->sum('quantity');
    }

    /** FEFO-ordered batches with stock for a product (excludes expired). */
    public function fefoBatchesFor(int $productId, ?int $needed = null): Collection
    {
        $q = ProductBatch::where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', today())
            ->orderBy('expiry_date', 'asc');

        $batches = $q->get();

        if ($needed === null) {
            return $batches;
        }

        // Slice into the minimum set of batches that satisfies `needed`.
        $picked = collect();
        $remaining = $needed;
        foreach ($batches as $b) {
            if ($remaining <= 0) break;
            $picked->push([
                'batch'       => $b,
                'take'        => min($remaining, $b->quantity),
                'expiry_date' => $b->expiry_date,
            ]);
            $remaining -= $b->quantity;
        }
        return $picked;
    }

    public function lowStockProducts(): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Product $p) => $p->current_stock <= $p->minimum_stock)
            ->values();
    }

    public function expiringBatches(int $withinDays = 30): Collection
    {
        return ProductBatch::query()
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', today())
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

    public function totalStockValue(): float
    {
        return (float) ProductBatch::query()
            ->selectRaw('COALESCE(SUM(quantity * purchase_price), 0) AS v')
            ->value('v');
    }
}
