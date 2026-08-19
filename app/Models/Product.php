<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'barcode', 'description',
        'sale_price', 'minimum_stock', 'is_active',
    ];

    protected $casts = [
        'sale_price'    => 'decimal:2',
        'minimum_stock' => 'integer',
        'is_active'     => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Computed: SUM of remaining batch quantities. */
    protected function currentStock(): Attribute
    {
        return Attribute::get(fn () => (int) $this->productBatches()->sellable()->sum('quantity'));
    }

    /** Total physical quantity, including expired batches. */
    protected function physicalStock(): Attribute
    {
        return Attribute::get(fn () => (int) $this->productBatches()->sum('quantity'));
    }

    /** Computed: true when current_stock <= minimum_stock. */
    protected function isLowStock(): Attribute
    {
        return Attribute::get(fn () => $this->current_stock <= $this->minimum_stock);
    }
}
