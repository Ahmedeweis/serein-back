<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Home page hero slider slide: a desktop (wide) and a mobile (portrait) crop of the same banner.
 */
class HeroSlide extends Model
{
    protected $fillable = ['desktop_image', 'mobile_image', 'alt_ar', 'alt_en', 'link', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    protected $appends = ['desktop_url', 'mobile_url'];

    protected $hidden = ['desktop_image', 'mobile_image'];

    protected static function booted(): void
    {
        static::deleted(function (HeroSlide $slide) {
            Storage::disk('public')->delete(array_filter([$slide->desktop_image, $slide->mobile_image]));
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getDesktopUrlAttribute(): ?string
    {
        return $this->desktop_image ? Storage::disk('public')->url($this->desktop_image) : null;
    }

    public function getMobileUrlAttribute(): ?string
    {
        return $this->mobile_image ? Storage::disk('public')->url($this->mobile_image) : null;
    }
}
