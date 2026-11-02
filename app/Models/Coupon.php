<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Note: 'used_count' is intentionally OMITTED to prevent mass-assignment tampering.
     * It is exclusively managed programmatically by the system.
     */
    protected $fillable = [
        'code',
        'percentage',
        'is_active',
        'expires_at',   // nullable datetime: coupon is rejected when this timestamp is past
        'max_uses',     // nullable integer: maximum total redemptions permitted
        'min_spend',    // nullable decimal: minimum cart subtotal required
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'percentage' => 'integer',
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
        'max_uses'   => 'integer',
        'used_count' => 'integer',
        'min_spend'  => 'decimal:2',
    ];

    /**
     * Always store coupon codes in uppercase and trimmed.
     */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    /**
     * Scope query to only valid coupons:
     * 1. Marked as active
     * 2. Not expired (expires_at is null or in the future)
     * 3. Not exhausted (max_uses is null or used_count < max_uses)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('max_uses')
                  ->orWhereColumn('used_count', '<', 'max_uses');
            });
    }

    /**
     * Check if this coupon has reached its maximum allowed uses.
     */
    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    /**
     * Check if this coupon has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
