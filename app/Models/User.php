<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'role', 'phone', 'address', 'vehicle', 'password', 'is_active', 'admin_disabled', 'attendance_photo', 'attendance_at', 'attendance_location'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'admin_disabled' => 'boolean',
            'attendance_at' => 'datetime',
            'attendance_location' => 'array',
        ];
    }

    public function customerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_user_id');
    }

    public function courierOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_user_id');
    }
}
