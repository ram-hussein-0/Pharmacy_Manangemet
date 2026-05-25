<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_invoice_id', 'product_id', 'product_batch_id',
        'quantity', 'unit_price', 'purchase_price_at_sale', 'total',
    ];

    protected $casts = [
        'quantity'               => 'integer',
        'unit_price'             => 'decimal:2',
        'purchase_price_at_sale' => 'decimal:2',
        'total'                  => 'decimal:2',
    ];

    public function saleInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }
}
