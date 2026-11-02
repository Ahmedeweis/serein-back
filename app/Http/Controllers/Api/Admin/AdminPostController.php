<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminPostController extends Controller
{
    /**
     * List all posts with filters and search for admin.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Post::with(['category', 'tags'])->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($category = $request->query('category')) {
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('slug', $category)
                  ->orWhere('name_en', $category)
                  ->orWhere('name_ar', $category);
            });
        }

        if ($request->has('featured') && $request->query('featured') !== '') {
            $val = $request->query('featured');
            if ($val === 'featured' || $val === '1' || $val === 'true') {
                $query->where('is_featured', true);
            } elseif ($val === 'not_featured' || $val === '0' || $val === 'false') {
                $query->where('is_featured', false);
            }
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 50);
        return PostResource::collection($query->paginate($perPage));
    }

    /**
     * Fetch single article for editing.
     */
    public function show(int $id): JsonResponse
    {
        $post = Post::with(['category', 'tags'])->findOrFail($id);
        return response()->json(['data' => new PostResource($post)]);
    }

    /**
     * Create new blog post.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Extract title
        $titleEn = $validated['title_en'] ?? ($validated['title']['en'] ?? '');
        $titleAr = $validated['title_ar'] ?? ($validated['title']['ar'] ?? null);

        // Generate slug
        $slug = !empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Post::generateUniqueSlug($titleEn);

        // Extract excerpt
        $excerptEn = $validated['excerpt_en'] ?? ($validated['excerpt']['en'] ?? null);
        $excerptAr = $validated['excerpt_ar'] ?? ($validated['excerpt']['ar'] ?? null);

        // Handle sections and content compilation
        $sections = $validated['sections'] ?? null;
        $contentEn = $validated['content_en'] ?? ($validated['content']['en'] ?? null);
        $contentAr = $validated['content_ar'] ?? ($validated['content']['ar'] ?? null);

        if (!empty($sections) && is_array($sections)) {
            $contentEn = Post::compileHtmlFromSections($sections, 'en');
            $contentAr = Post::compileHtmlFromSections($sections, 'ar');
        }

        // Handle cover image
        $coverImage = $validated['cover_image'] ?? ($validated['coverImage'] ?? null);
        if ($request->hasFile('image')) {
            $coverImage = $request->file('image')->store('blogs', 'public');
        }

        // Handle category
        $categoryId = $validated['category_id'] ?? null;
        if (!$categoryId && !empty($validated['category'])) {
            $catVal = $validated['category'];
            if (is_array($catVal)) {
                $catNameEn = $catVal['en'] ?? $catVal['name_en'] ?? 'General';
                $catNameAr = $catVal['ar'] ?? $catVal['name_ar'] ?? $catNameEn;
                $catSlug = Str::slug($catNameEn);
                $cat = BlogCategory::firstOrCreate(
                    ['slug' => $catSlug],
                    ['name_en' => $catNameEn, 'name_ar' => $catNameAr]
                );
                $categoryId = $cat->id;
            } elseif (is_string($catVal)) {
                $cat = BlogCategory::firstOrCreate(
                    ['slug' => Str::slug($catVal)],
                    ['name_en' => $catVal, 'name_ar' => $catVal]
                );
                $categoryId = $cat->id;
            }
        }

        // Handle author
        $authorName = $validated['author_name'] ?? ($validated['author'] ?? 'Serein Team');
        $authorRole = $validated['author_role'] ?? ($validated['authorRole'] ?? null);
        $authorRoleEn = is_array($authorRole) ? ($authorRole['en'] ?? null) : (is_string($authorRole) ? $authorRole : null);
        $authorRoleAr = is_array($authorRole) ? ($authorRole['ar'] ?? null) : null;

        // Read time
        $readTime = $validated['read_time_minutes'] ?? ($validated['readTimeMinutes'] ?? null);
        if (!$readTime && $contentEn) {
            $readTime = Post::calculateReadTime($contentEn);
        }

        $post = Post::create([
            'blog_category_id'  => $categoryId,
            'title_en'          => $titleEn,
            'title_ar'          => $titleAr,
            'slug'              => $slug,
            'excerpt_en'        => $excerptEn,
            'excerpt_ar'        => $excerptAr,
            'sections'          => $sections,
            'content_en'        => $contentEn,
            'content_ar'        => $contentAr,
            'cover_image'       => $coverImage,
            'youtube_url'       => $validated['youtube_url'] ?? ($validated['youtubeUrl'] ?? null),
            'author_name'       => $authorName,
            'author_avatar'     => $validated['author_avatar'] ?? null,
            'author_role_en'    => $authorRoleEn,
            'author_role_ar'    => $authorRoleAr,
            'is_featured'       => $validated['is_featured'] ?? ($validated['isFeatured'] ?? false),
            'status'            => $validated['status'] ?? 'draft',
            'published_at'      => $validated['published_at'] ?? ($validated['publishedAt'] ?? now()),
            'read_time_minutes' => $readTime ?? 5,
        ]);

        // Sync tags
        if (!empty($validated['tags']) && is_array($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                if (trim($tagName) === '') continue;
                $tag = BlogTag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => trim($tagName)]
                );
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);
        }

        return response()->json(['data' => new PostResource($post->load(['category', 'tags']))], 201);
    }

    /**
     * Update existing article.
     */
    public function update(UpdatePostRequest $request, int $id): JsonResponse
    {
        $post = Post::with(['category', 'tags'])->findOrFail($id);
        $validated = $request->validated();

        // Extract title
        if (isset($validated['title_en']) || isset($validated['title']['en'])) {
            $post->title_en = $validated['title_en'] ?? $validated['title']['en'];
        }
        if (isset($validated['title_ar']) || isset($validated['title']['ar'])) {
            $post->title_ar = $validated['title_ar'] ?? $validated['title']['ar'];
        }

        // Slug
        if (!empty($validated['slug'])) {
            $post->slug = Post::generateUniqueSlug($validated['slug'], $post->id);
        }

        // Excerpt
        if (isset($validated['excerpt_en']) || isset($validated['excerpt']['en'])) {
            $post->excerpt_en = $validated['excerpt_en'] ?? $validated['excerpt']['en'];
        }
        if (isset($validated['excerpt_ar']) || isset($validated['excerpt']['ar'])) {
            $post->excerpt_ar = $validated['excerpt_ar'] ?? $validated['excerpt']['ar'];
        }

        // Sections & Content
        if (array_key_exists('sections', $validated) && is_array($validated['sections'])) {
            $post->sections = $validated['sections'];
            $post->content_en = Post::compileHtmlFromSections($validated['sections'], 'en');
            $post->content_ar = Post::compileHtmlFromSections($validated['sections'], 'ar');
        } else {
            if (isset($validated['content_en']) || isset($validated['content']['en'])) {
                $post->content_en = $validated['content_en'] ?? $validated['content']['en'];
            }
            if (isset($validated['content_ar']) || isset($validated['content']['ar'])) {
                $post->content_ar = $validated['content_ar'] ?? $validated['content']['ar'];
            }
        }

        // Handle cover image
        if ($request->hasFile('image')) {
            // Delete previous local image if exists
            if ($post->cover_image && !filter_var($post->cover_image, FILTER_VALIDATE_URL) && !str_starts_with($post->cover_image, '/')) {
                Storage::disk('public')->delete($post->cover_image);
            }
            $post->cover_image = $request->file('image')->store('blogs', 'public');
        } elseif (isset($validated['cover_image']) || isset($validated['coverImage'])) {
            $post->cover_image = $validated['cover_image'] ?? $validated['coverImage'];
        }

        // Handle YouTube URL
        if (array_key_exists('youtube_url', $validated) || array_key_exists('youtubeUrl', $validated)) {
            $post->youtube_url = $validated['youtube_url'] ?? ($validated['youtubeUrl'] ?? null);
        }

        // Author
        if (isset($validated['author_name']) || isset($validated['author'])) {
            $post->author_name = $validated['author_name'] ?? $validated['author'];
        }
        $authorRole = $validated['author_role'] ?? ($validated['authorRole'] ?? null);
        if ($authorRole !== null) {
            $post->author_role_en = is_array($authorRole) ? ($authorRole['en'] ?? null) : (is_string($authorRole) ? $authorRole : null);
            $post->author_role_ar = is_array($authorRole) ? ($authorRole['ar'] ?? null) : null;
        }

        // Category
        if (isset($validated['category_id'])) {
            $post->blog_category_id = $validated['category_id'];
        } elseif (!empty($validated['category'])) {
            $catVal = $validated['category'];
            if (is_array($catVal)) {
                $catNameEn = $catVal['en'] ?? $catVal['name_en'] ?? 'General';
                $catNameAr = $catVal['ar'] ?? $catVal['name_ar'] ?? $catNameEn;
                $cat = BlogCategory::firstOrCreate(
                    ['slug' => Str::slug($catNameEn)],
                    ['name_en' => $catNameEn, 'name_ar' => $catNameAr]
                );
                $post->blog_category_id = $cat->id;
            } elseif (is_string($catVal)) {
                $cat = BlogCategory::firstOrCreate(
                    ['slug' => Str::slug($catVal)],
                    ['name_en' => $catVal, 'name_ar' => $catVal]
                );
                $post->blog_category_id = $cat->id;
            }
        }

        // Featured & Status
        if (isset($validated['is_featured']) || isset($validated['isFeatured'])) {
            $post->is_featured = (bool) ($validated['is_featured'] ?? $validated['isFeatured']);
        }
        if (isset($validated['status'])) {
            $post->status = $validated['status'];
        }
        if (isset($validated['published_at']) || isset($validated['publishedAt'])) {
            $post->published_at = $validated['published_at'] ?? $validated['publishedAt'];
        }
        if (isset($validated['read_time_minutes']) || isset($validated['readTimeMinutes'])) {
            $post->read_time_minutes = $validated['read_time_minutes'] ?? $validated['readTimeMinutes'];
        }

        $post->save();

        // Sync tags if present
        if (array_key_exists('tags', $validated) && is_array($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                if (trim($tagName) === '') continue;
                $tag = BlogTag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => trim($tagName)]
                );
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);
        }

        return response()->json(['data' => new PostResource($post->fresh(['category', 'tags']))]);
    }

    /**
     * Quick status toggle (published / draft).
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $post = Post::with(['category', 'tags'])->findOrFail($id);
        $newStatus = $post->status === 'published' ? 'draft' : 'published';
        $post->status = $newStatus;

        if ($newStatus === 'published' && !$post->published_at) {
            $post->published_at = now();
        }

        $post->save();

        return response()->json(['data' => new PostResource($post)]);
    }

    /**
     * Delete post and unlink associated media.
     */
    public function destroy(int $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        // Delete cover image from local storage if applicable
        if ($post->cover_image && !filter_var($post->cover_image, FILTER_VALIDATE_URL) && !str_starts_with($post->cover_image, '/')) {
            Storage::disk('public')->delete($post->cover_image);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully.']);
    }
}
