<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by', 'invoice_number', 'invoice_date',
        'customer_name', 'customer_phone',
        'subtotal', 'discount', 'tax', 'total',
        'payment_method', 'status',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'subtotal'     => 'decimal:2',
        'discount'     => 'decimal:2',
        'tax'          => 'decimal:2',
        'total'        => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Computed gross profit using captured purchase_price_at_sale. */
    protected function profit(): Attribute
    {
        return Attribute::get(function () {
            return $this->saleItems->sum(
                fn (SaleItem $i) => ($i->unit_price - $i->purchase_price_at_sale) * $i->quantity
            );
        });
    }
}
