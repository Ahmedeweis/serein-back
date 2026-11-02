<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Governorate extends Model
{
    protected $fillable = ['country_id', 'shipping_zone_id', 'name_ar', 'name_en', 'is_active'];

    protected static function booted(): void
    {
        static::saved(function (Governorate $gov) {
            if ($gov->country_id) {
                Cache::forget("govs_{$gov->country_id}_ar_v2");
                Cache::forget("govs_{$gov->country_id}_en_v2");
            }
        });

        static::deleted(function (Governorate $gov) {
            if ($gov->country_id) {
                Cache::forget("govs_{$gov->country_id}_ar_v2");
                Cache::forget("govs_{$gov->country_id}_en_v2");
            }
        });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}