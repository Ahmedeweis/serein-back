<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name_en',
        'name_ar',
        'slug',
        'description_en',
        'description_ar',
        'image',
        'youtube_url',
        'unit_type',
        'rating',
        'reviews_count',
        'is_best_seller',
        'is_featured',
        'is_trending',
        'is_new_arrival',
        'is_active',
    ];

    protected $casts = [
        'rating'         => 'float',
        'reviews_count'  => 'integer',
        'is_best_seller' => 'boolean',
        'is_featured'    => 'boolean',
        'is_trending'    => 'boolean',
        'is_new_arrival' => 'boolean',
        'is_active'      => 'boolean',
    ];

    protected $appends = [
        'image_url',
        'price',
        'compare_at_price',
        'youtube_embed_url',
        'primary_image',
        'gallery_images',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->orderBy('price');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    /**
     * Recalculate cached rating and reviews_count based on approved reviews.
     */
    public function recalculateRatingMetrics(): void
    {
        $approvedReviews = $this->reviews()->where('status', 'approved');
        $count = $approvedReviews->count();
        $avg = $count > 0 ? (float) $approvedReviews->avg('rating') : 5.0;

        $this->update([
            'rating'        => round($avg, 1),
            'reviews_count' => $count,
        ]);
    }

    // ─── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Full public URL to the product image.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->image) {
                    return null;
                }
                if (filter_var($this->image, FILTER_VALIDATE_URL)) {
                    return $this->image;
                }
                return Storage::disk('public')->url($this->image);
            }
        );
    }

    /**
     * Default variant: is_default=true, or cheapest as fallback.
     */
    protected function defaultVariant(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->relationLoaded('variants')) {
                    return $this->variants->firstWhere('is_default', true)
                        ?? $this->variants->sortBy('price')->first();
                }
                return $this->variants()->where('is_default', true)->first()
                    ?? $this->variants()->orderBy('price')->first();
            }
        );
    }

    /**
     * Product price — delegates to the default variant.
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->default_variant?->price ?? 0
        );
    }

    /**
     * Compare-at price — delegates to the default variant.
     */
    protected function compareAtPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->default_variant?->compare_at_price
        );
    }

    /**
     * Alias for primary image full URL.
     */
    protected function primaryImage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_url
        );
    }

    public function getPrimaryImageAttribute(): ?string
    {
        return $this->image_url;
    }

    /**
     * Array of full public URLs for all secondary gallery images.
     */
    protected function galleryImages(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                if ($this->relationLoaded('images')) {
                    return $this->images->map(fn ($img) => $img->image_url)->filter()->values()->all();
                }
                return $this->images()->get()->map(fn ($img) => $img->image_url)->filter()->values()->all();
            }
        );
    }

    public function getGalleryImagesAttribute(): array
    {
        if ($this->relationLoaded('images')) {
            return $this->images->map(fn ($img) => $img->image_url)->filter()->values()->all();
        }
        return $this->images()->get()->map(fn ($img) => $img->image_url)->filter()->values()->all();
    }

    // ─── YouTube Video Embed Helpers ──────────────────────────────────────────

    /**
     * Extract the 11-character YouTube video ID from various URL formats
     * (e.g. watch?v=, youtu.be/, embed/, shorts/, live/).
     */
    public static function extractYoutubeId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $trimmed = trim($url);
        $pattern = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?|shorts|live)\/|.*[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i';

        if (preg_match($pattern, $trimmed, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Standardized YouTube embed URL Attribute (https://www.youtube.com/embed/{id}).
     */
    protected function youtubeEmbedUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $videoId = static::extractYoutubeId($this->youtube_url);
                return $videoId ? "https://www.youtube.com/embed/{$videoId}" : null;
            }
        );
    }

    public function getYoutubeEmbedUrlAttribute(): ?string
    {
        $videoId = static::extractYoutubeId($this->youtube_url);
        return $videoId ? "https://www.youtube.com/embed/{$videoId}" : null;
    }

    // ─── Query Scopes ──────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeBestSeller($query)
    {
        return $query->where('is_best_seller', true);
    }

    public function scopeTrending($query)
    {
        return $query->where('is_trending', true);
    }

    public function scopeNewArrival($query)
    {
        return $query->where('is_new_arrival', true);
    }
}
