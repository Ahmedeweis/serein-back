<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\BlogCategory;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    /**
     * Get paginated published articles.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Post::with(['category', 'tags'])
            ->published()
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at');

        // Filter by Category (slug or name)
        if ($cat = $request->query('category')) {
            $query->whereHas('category', function ($q) use ($cat) {
                $q->where('slug', $cat)
                  ->orWhere('name_en', $cat)
                  ->orWhere('name_ar', $cat);
            });
        }

        // Filter by Tag
        if ($tag = $request->query('tag')) {
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('slug', $tag)
                  ->orWhere('name', $tag);
            });
        }

        // Filter by Featured
        if ($request->has('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        // Search query
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%")
                  ->orWhere('excerpt_en', 'like', "%{$search}%")
                  ->orWhere('excerpt_ar', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 12);
        $posts = $query->paginate($perPage);

        return PostResource::collection($posts);
    }

    /**
     * Get single published article by slug and safely increment views.
     */
    public function show(string $slug): JsonResponse
    {
        $post = Post::with(['category', 'tags'])
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment views count atomically
        Post::where('id', $post->id)->increment('views_count');
        $post->views_count++;

        // Fetch up to 2 related posts
        $related = Post::with(['category', 'tags'])
            ->published()
            ->where('id', '!=', $post->id)
            ->where(function ($q) use ($post) {
                if ($post->blog_category_id) {
                    $q->where('blog_category_id', $post->blog_category_id);
                }
            })
            ->latest('published_at')
            ->limit(2)
            ->get();

        if ($related->isEmpty()) {
            $related = Post::with(['category', 'tags'])
                ->published()
                ->where('id', '!=', $post->id)
                ->latest('published_at')
                ->limit(2)
                ->get();
        }

        return response()->json([
            'data'    => new PostResource($post),
            'related' => PostResource::collection($related),
        ]);
    }

    /**
     * Get the featured article.
     */
    public function featured(): JsonResponse
    {
        $post = Post::with(['category', 'tags'])
            ->published()
            ->where('is_featured', true)
            ->latest('published_at')
            ->first();

        if (!$post) {
            $post = Post::with(['category', 'tags'])
                ->published()
                ->latest('published_at')
                ->first();
        }

        return response()->json([
            'data' => $post ? new PostResource($post) : null,
        ]);
    }

    /**
     * Get categories with post count.
     */
    public function categories(): JsonResponse
    {
        $categories = BlogCategory::whereHas('publishedPosts')
            ->withCount(['publishedPosts as posts_count'])
            ->orderBy('name_en')
            ->get();

        return response()->json([
            'data' => $categories->map(fn ($c) => [
                'id'          => $c->id,
                'slug'        => $c->slug,
                'name_en'     => $c->name_en,
                'name_ar'     => $c->name_ar,
                'posts_count' => $c->posts_count,
            ]),
        ]);
    }
}
