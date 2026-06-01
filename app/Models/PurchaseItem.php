<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id', 'product_id', 'quantity',
        'unit_price', 'total', 'batch_number', 'expiry_date',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'unit_price'  => 'decimal:2',
        'total'       => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** A purchase item is materialised as exactly one product batch. */
    public function productBatch(): HasOne
    {
        return $this->hasOne(ProductBatch::class);
    }
}
