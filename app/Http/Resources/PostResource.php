<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array matching both Client Storefront and Admin Dashboard.
     */
    public function toArray(Request $request): array
    {
        // Cover image URL resolution
        $coverImageUrl = $this->cover_image;
        if ($coverImageUrl && !filter_var($coverImageUrl, FILTER_VALIDATE_URL) && !str_starts_with($coverImageUrl, '/')) {
            $coverImageUrl = Storage::disk('public')->url($coverImageUrl);
        }

        // Author Avatar resolution
        $authorAvatarUrl = $this->author_avatar;
        if ($authorAvatarUrl && !filter_var($authorAvatarUrl, FILTER_VALIDATE_URL) && !str_starts_with($authorAvatarUrl, '/')) {
            $authorAvatarUrl = Storage::disk('public')->url($authorAvatarUrl);
        }

        // Category formatting
        $categoryData = null;
        if ($this->category) {
            $categoryData = [
                'id'   => $this->category->id,
                'slug' => $this->category->slug,
                'en'   => $this->category->name_en,
                'ar'   => $this->category->name_ar,
            ];
        }

        // Tags array
        $tagsList = $this->relationLoaded('tags')
            ? $this->tags->pluck('name')->values()->all()
            : [];

        // Dual-casing response to seamlessly support both Nuxt 3 Storefront and Vue 3 Admin Dashboard
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'title'             => [
                'en' => $this->title_en,
                'ar' => $this->title_ar ?? '',
            ],
            'excerpt'           => [
                'en' => $this->excerpt_en ?? '',
                'ar' => $this->excerpt_ar ?? '',
            ],
            'content'           => [
                'en' => $this->content_en ?? '',
                'ar' => $this->content_ar ?? '',
            ],
            'sections'          => $this->sections ?? [],
            'coverImage'        => $coverImageUrl ?? '/bloge.webp',
            'cover_image'       => $coverImageUrl ?? '/bloge.webp',
            'youtube_url'       => $this->youtube_url,
            'youtubeUrl'        => $this->youtube_url,
            'youtube_embed_url' => $this->youtube_embed_url,
            'youtubeEmbedUrl'   => $this->youtube_embed_url,
            'author'            => $this->author_name ?? 'Serein Team',
            'author_name'       => $this->author_name ?? 'Serein Team',
            'authorAvatar'      => $authorAvatarUrl ?? '/logo.png',
            'author_avatar'     => $authorAvatarUrl ?? '/logo.png',
            'authorRole'        => [
                'en' => $this->author_role_en ?? '',
                'ar' => $this->author_role_ar ?? '',
            ],
            'author_role'       => [
                'en' => $this->author_role_en ?? '',
                'ar' => $this->author_role_ar ?? '',
            ],
            'category'          => $categoryData,
            'isFeatured'        => (bool) $this->is_featured,
            'is_featured'       => (bool) $this->is_featured,
            'status'            => $this->status,
            'publishedAt'       => $this->published_at ? $this->published_at->toISOString() : null,
            'published_at'      => $this->published_at ? $this->published_at->toISOString() : null,
            'tags'              => $tagsList,
            'readTimeMinutes'   => (int) $this->read_time_minutes,
            'read_time_minutes' => (int) $this->read_time_minutes,
            'views_count'       => (int) $this->views_count,
            'created_at'        => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at'        => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
