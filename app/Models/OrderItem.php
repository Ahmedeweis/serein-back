<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name_en',
        'product_name_ar',
        'product_image',
        'variant_label',
        'price',
        'quantity',
    ];

    protected $casts = [
        'price'    => 'float',
        'quantity' => 'integer',
    ];

    protected $appends = [
        'line_total',
        'unit_price',
        'product_title',
        'variant_title',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getLineTotalAttribute(): float
    {
        return round($this->price * $this->quantity, 2);
    }

    public function getUnitPriceAttribute(): float
    {
        return (float) ($this->price ?? 0);
    }

    public function getProductTitleAttribute(): string
    {
        return $this->product_name_en ?? $this->product_name_ar ?? '';
    }

    public function getVariantTitleAttribute(): ?string
    {
        return $this->variant_label;
    }
}
