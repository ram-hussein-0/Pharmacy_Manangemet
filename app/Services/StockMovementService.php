<?php

namespace App\Services;

use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;

class StockMovementService
{
    public function recordIn(int $productId, int $qty, string $refType, ?int $refId, string $notes = ''): StockMovement
    {
        return StockMovement::create([
            'product_id'     => $productId,
            'created_by'     => Auth::id(),
            'type'           => StockMovement::TYPE_IN,
            'quantity'       => $qty,
            'reference_type' => $refType,
            'reference_id'   => $refId,
            'notes'          => $notes,
        ]);
    }

    public function recordOut(int $productId, int $qty, string $refType, ?int $refId, string $notes = ''): StockMovement
    {
        return StockMovement::create([
            'product_id'     => $productId,
            'created_by'     => Auth::id(),
            'type'           => StockMovement::TYPE_OUT,
            'quantity'       => $qty,
            'reference_type' => $refType,
            'reference_id'   => $refId,
            'notes'          => $notes,
        ]);
    }

    public function recordAdjust(int $productId, int $delta, string $notes): StockMovement
    {
        return StockMovement::create([
            'product_id'     => $productId,
            'created_by'     => Auth::id(),
            'type'           => StockMovement::TYPE_ADJUST,
            'quantity'       => $delta,
            'reference_type' => StockMovement::REF_MANUAL,
            'reference_id'   => null,
            'notes'          => $notes,
        ]);
    }
}
