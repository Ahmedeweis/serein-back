<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'customer_city',
        'customer_country',
        'subtotal',
        'shipping_cost',
        'discount_amount',
        'coupon_code',
        'total',
        'shipping_zone_id',
        'payment_method',
        'payment_status',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal'        => 'float',
        'shipping_cost'   => 'float',
        'discount_amount' => 'float',
        'total'           => 'float',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Keeps internal sensitive or administrative fields protected.
     */
    protected $hidden = [
        // Any internal administrative/gateway payload fields
    ];

    protected $appends = [
        'total_amount',
    ];

    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->attributes['total'] ?? 0);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate a unique, unpredictable, and collision-resistant order number.
     * Format: ORD-YYYY-XXXX-YYY (e.g., ORD-2026-7492-9KF)
     *
     * Solves:
     *  1. Race Condition / Collision: Multiple concurrent checkouts cannot collide on the same sequential ID.
     *  2. Business Intelligence Leak: Unpredictable entropy prevents competitors from calculating daily/monthly volume.
     *  3. Substring Truncation Bug: Eliminates failure when order count exceeds 4 digits (substr -4 limit).
     */
    public static function generateOrderNumber(): string
    {
        $year = now()->year;

        do {
            $orderNumber = sprintf(
                'ORD-%d-%04d-%s',
                $year,
                random_int(1000, 9999),
                strtoupper(Str::random(3))
            );
        } while (static::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
