<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceEntry extends Model
{
    protected $fillable = [
        'order_id',
        'category',
        'type',
        'amount',
        'note',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
