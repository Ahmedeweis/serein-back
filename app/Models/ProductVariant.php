<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name_en',
        'name_ar',
        'unit',
        'value',
        'price',
        'compare_at_price',
        'stock',
        'sku',
        'is_default',
    ];

    protected $casts = [
        'value'            => 'float',
        'price'            => 'float',
        'compare_at_price' => 'float',
        'stock'            => 'integer',
        'is_default'       => 'boolean',
    ];

    /**
     * Relationship: Variant belongs to a Product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
