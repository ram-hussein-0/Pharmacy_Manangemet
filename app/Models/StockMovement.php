<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_IN     = 'in';
    public const TYPE_OUT    = 'out';
    public const TYPE_ADJUST = 'adjust';

    public const REF_PURCHASE = 'purchase_invoice';
    public const REF_SALE     = 'sale_invoice';
    public const REF_MANUAL   = 'manual';

    protected $fillable = [
        'product_id', 'created_by', 'type', 'quantity',
        'reference_type', 'reference_id', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
