<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array matching Nuxt Storefront Product interface.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $defaultVariant = $this->variants->firstWhere('is_default', true)
            ?? $this->variants->sortBy('price')->first();

        $price = $defaultVariant ? (float) $defaultVariant->price : (float) ($this->price ?? 0);
        $compareAtPrice = $defaultVariant?->compare_at_price
            ? (float) $defaultVariant->compare_at_price
            : ($this->compare_at_price ? (float) $this->compare_at_price : null);

        $totalStock = $this->variants->sum('stock');

        // Primary image & Gallery images mapping
        $images = [];
        $primaryImageUrl = $this->image_url ?: asset('images/products/default.webp');
        $images[] = [
            'id'         => 1,
            'url'        => $primaryImageUrl,
            'alt'        => $this->name_en,
            'is_primary' => true,
        ];

        $galleryModels = $this->relationLoaded('images') ? $this->images : $this->images()->get();
        $galleryImages = [];
        foreach ($galleryModels as $img) {
            $url = $img->image_url;
            if ($url) {
                $galleryImages[] = $url;
                $images[] = [
                    'id'         => $img->id,
                    'url'        => $url,
                    'alt'        => $this->name_en,
                    'sort_order' => $img->sort_order,
                    'is_primary' => false,
                ];
            }
        }

        // Sizes/variants mapped to frontend ProductSize interface
        $sizes = $this->variants->map(function ($variant) {
            $unit = trim($variant->unit ?? '');
            $name = trim($variant->name_en ?? '');

            if ($name !== '') {
                if ($unit !== '' && !preg_match('/' . preg_quote($unit, '/') . '$/i', $name)) {
                    $separator = (strlen($unit) > 2 && !ctype_alpha(substr($name, -1))) ? ' ' : '';
                    $label = $name . $separator . $unit;
                } else {
                    $label = $name;
                }
            } else {
                $val = $variant->value ?? '';
                $separator = (strlen($unit) > 2) ? ' ' : '';
                $label = trim("{$val}{$separator}{$unit}");
            }

            return [
                'id'             => $variant->id,
                'name'           => $variant->name_en,
                'label'          => $label,
                'unit'           => $variant->unit,
                'value'          => $variant->value !== null ? (float) $variant->value : null,
                'price'          => (float) $variant->price,
                'compareAtPrice' => $variant->compare_at_price ? (float) $variant->compare_at_price : null,
                'stock'          => (int) $variant->stock,
                'sku'            => $variant->sku ?: "PRD-{$variant->product_id}-{$variant->id}",
            ];
        })->values();

        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'name'           => [
                'en' => $this->name_en,
                'ar' => $this->name_ar ?: $this->name_en,
            ],
            'description'    => [
                'en' => $this->description_en ?: '',
                'ar' => $this->description_ar ?: ($this->description_en ?: ''),
            ],
            'currency'       => 'EGP',
            'unit_type'      => $this->unit_type,
            'price'          => $price,
            'compareAtPrice' => $compareAtPrice,
            'rating'         => (float) $this->rating,
            'reviewsCount'   => (int) $this->reviews_count,
            'stock'          => (int) $totalStock,
            'isFeatured'     => (bool) $this->is_featured,
            'isBestSeller'   => (bool) $this->is_best_seller,
            'isTrending'     => (bool) $this->is_trending,
            'isNewArrival'   => (bool) $this->is_new_arrival,
            'createdAt'      => $this->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'primary_image'     => $this->image_url,
            'gallery_images'    => $galleryImages,
            'youtube_url'       => $this->youtube_url,
            'youtube_embed_url' => $this->youtube_embed_url,
            'youtubeUrl'        => $this->youtube_url,
            'youtubeEmbedUrl'   => $this->youtube_embed_url,
            'images'         => $images,
            'sizes'          => $sizes,
            'category'       => $this->category ? [
                'id'   => $this->category->id,
                'slug' => $this->category->slug,
                'name' => [
                    'en' => $this->category->name_en,
                    'ar' => $this->category->name_ar ?: $this->category->name_en,
                ],
                'image' => $this->category->image_url ?? $this->category->image,
            ] : null,
        ];
    }
}
