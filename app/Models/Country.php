<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Country extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_en', 'phone_code', 'is_active'];

    protected static function booted(): void
    {
        static::saved(function (Country $country) {
            Cache::forget('countries_ar');
            Cache::forget('countries_en');
            Cache::forget("govs_{$country->id}_ar_v2");
            Cache::forget("govs_{$country->id}_en_v2");
        });

        static::deleted(function (Country $country) {
            Cache::forget('countries_ar');
            Cache::forget('countries_en');
            Cache::forget("govs_{$country->id}_ar_v2");
            Cache::forget("govs_{$country->id}_en_v2");
        });
    }

    public function governorates(): HasMany
    {
        return $this->hasMany(Governorate::class);
    }
}
