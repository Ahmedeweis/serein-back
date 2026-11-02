<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';

    protected $fillable = [
        'blog_category_id',
        'title_en',
        'title_ar',
        'slug',
        'excerpt_en',
        'excerpt_ar',
        'sections',
        'content_en',
        'content_ar',
        'cover_image',
        'youtube_url',
        'author_name',
        'author_avatar',
        'author_role_en',
        'author_role_ar',
        'is_featured',
        'status',
        'published_at',
        'read_time_minutes',
        'views_count',
    ];

    protected $appends = [
        'youtube_embed_url',
    ];

    protected $casts = [
        'sections'          => 'array',
        'is_featured'       => 'boolean',
        'read_time_minutes' => 'integer',
        'views_count'       => 'integer',
        'published_at'      => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'post_id', 'tag_id');
    }

    // ─── Query Scopes ──────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where(function ($q) {
                         $q->whereNull('published_at')
                           ->orWhere('published_at', '<=', now());
                     });
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ─── Content Compilation Helper ───────────────────────────────────────────

    /**
     * Compiles clean semantic HTML from structured plain-text sections.
     * Prevents admin from having to write raw HTML tags.
     */
    public static function compileHtmlFromSections(array $sections, string $locale = 'en'): string
    {
        $html = '';
        foreach ($sections as $section) {
            $heading = '';
            if (isset($section["heading_{$locale}"])) {
                $heading = $section["heading_{$locale}"];
            } elseif (isset($section['heading']) && is_array($section['heading'])) {
                $heading = $section['heading'][$locale] ?? '';
            } elseif (isset($section['heading']) && is_string($section['heading'])) {
                $heading = $section['heading'];
            }

            $body = '';
            if (isset($section["body_{$locale}"])) {
                $body = $section["body_{$locale}"];
            } elseif (isset($section['body']) && is_array($section['body'])) {
                $body = $section['body'][$locale] ?? '';
            } elseif (isset($section['body']) && is_string($section['body'])) {
                $body = $section['body'];
            }

            if (!empty(trim($heading))) {
                $html .= '<h2>' . htmlspecialchars(trim($heading)) . '</h2>';
            }

            if (!empty(trim($body))) {
                // Split multi-line plain text into clean paragraphs
                $paragraphs = preg_split("/\r\n|\n|\r/", trim($body));
                foreach ($paragraphs as $p) {
                    $trimmed = trim($p);
                    if ($trimmed !== '') {
                        $html .= '<p>' . htmlspecialchars($trimmed) . '</p>';
                    }
                }
            }
        }

        return $html;
    }

    /**
     * Generate unique slug from title string.
     */
    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);
        if (empty($slug)) {
            $slug = 'post-' . Str::random(6);
        }

        $query = static::where('slug', $slug);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $count = 1;
        $originalSlug = $slug;
        while ($query->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $query = static::where('slug', $slug);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
            $count++;
        }

        return $slug;
    }

    /**
     * Compute read time in minutes based on word count.
     */
    public static function calculateReadTime(string $content): int
    {
        $words = str_word_count(strip_tags($content));
        return max(1, (int) ceil($words / 180));
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
     * Standardized YouTube embed URL Attribute.
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

    /**
     * Standardized YouTube embed URL getter for direct access or classic accessor support.
     */
    public function getYoutubeEmbedUrlAttribute(): ?string
    {
        $videoId = static::extractYoutubeId($this->youtube_url);
        return $videoId ? "https://www.youtube.com/embed/{$videoId}" : null;
    }
}

