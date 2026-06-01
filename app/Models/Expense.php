<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    public const TYPES = ['rent', 'utilities', 'salary', 'maintenance', 'other'];

    protected $fillable = [
        'created_by', 'title', 'type', 'amount', 'expense_date', 'notes',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
