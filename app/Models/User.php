<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

protected $fillable = [
    'first_name',
    'last_name',
    'bio',
    'email',
    'phone',
    'password',
    'role',
    'country',
    'governorate',
    'address',
    'postal_code',
    'tax_id',
];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $appends = [
        'full_name',
        'name',
    ];

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Scope for customers only (exclude admins)
    public function scopeCustomers($query)
    {
        return $query->where('role', '!=', 'admin');
    }
}

