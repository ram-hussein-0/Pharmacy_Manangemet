<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'purchase_item_id', 'batch_number',
        'expiry_date', 'quantity', 'purchase_price',
    ];

    protected $casts = [
        'expiry_date'    => 'date',
        'quantity'       => 'integer',
        'purchase_price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Days remaining until expiry. Negative when expired. */
    protected function daysUntilExpiry(): Attribute
    {
        return Attribute::get(fn () => (int) Carbon::today()->diffInDays($this->expiry_date, false));
    }

    /** "expired" | "critical" (≤30d) | "warning" (≤90d) | "ok". */
    protected function expiryStatus(): Attribute
    {
        return Attribute::get(function () {
            $d = $this->days_until_expiry;
            return match (true) {
                $d < 0   => 'expired',
                $d <= 30 => 'critical',
                $d <= 90 => 'warning',
                default  => 'ok',
            };
        });
    }
}
