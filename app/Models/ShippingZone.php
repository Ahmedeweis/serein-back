<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    use HasFactory;

    protected $table = 'shipping_zones';

    protected $fillable = [
        'name_ar',
        'name_en',
        'price',
        'min_days',
        'max_days',
        'is_active',
        'is_protected',
    ];

    protected $casts = [
        'price'        => 'float',
        'min_days'     => 'integer',
        'max_days'     => 'integer',
        'is_active'    => 'boolean',
        'is_protected' => 'boolean',
    ];

    public function governorates(): HasMany
    {
        return $this->hasMany(Governorate::class, 'shipping_zone_id');
    }
}
