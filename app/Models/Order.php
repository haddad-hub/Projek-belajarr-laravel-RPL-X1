<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_user_id',
        'courier_user_id',
        'customer_username',
        'customer_name',
        'customer_phone',
        'item',
        'category',
        'qty',
        'pickup',
        'dropoff',
        'note',
        'status',
        'revenue',
        'rating',
        'accepted_at',
        'arrived_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->id ??= 'ORD-'.strtoupper(Str::random(10));
        });
    }

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'revenue' => 'decimal:2',
            'rating' => 'integer',
            'accepted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_user_id');
    }
}
