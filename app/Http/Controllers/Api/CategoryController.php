<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/api/categories',
        summary: 'Get public catalog categories list with active products count',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'lang',
                in: 'query',
                description: 'Preferred language (ar or en)',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'ar', enum: ['ar', 'en'])
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                description: 'Filter by status — only admin may use "all"; public always receives active only',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'active', enum: ['active', 'all'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Categories retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'عسل جبلي'),
                                    new OA\Property(property: 'name_en', type: 'string', example: 'Mountain Honey'),
                                    new OA\Property(property: 'name_ar', type: 'string', example: 'عسل جبلي'),
                                    new OA\Property(property: 'slug', type: 'string', example: 'mountain-honey'),
                                    new OA\Property(property: 'image_url', type: 'string', nullable: true, example: 'http://localhost:8000/storage/categories/xyz.jpg'),
                                    new OA\Property(property: 'products_count', type: 'integer', example: 12),
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $lang = $request->query('lang', $request->header('Accept-Language', 'ar'));
        $lang = in_array(strtolower($lang), ['ar', 'en']) ? strtolower($lang) : 'ar';

        // ── Fix 1: Zero-Trust Status Filtering ────────────────────────────────
        // Only authenticated admins may pass ?status=all to see inactive categories.
        // Any public visitor or regular user always receives active categories only.
        $status  = $request->query('status', 'active');
        $user    = auth('sanctum')->user();
        $isAdmin = $user && $user->role === 'admin';

        // Non-admins always get active-only, regardless of the ?status query param
        $showAll = $isAdmin && $status === 'all';

        // ── Fix 3: Cache Layer (1-hour TTL) ───────────────────────────────────
        // Categories rarely change; caching avoids repeated DB queries on every
        // storefront pageview. Cache is keyed by lang + visibility scope.
        $cacheKey = $showAll
            ? "admin_categories_{$lang}_all"
            : "public_categories_{$lang}_active";

        $categories = Cache::remember($cacheKey, 3600, function () use ($showAll, $lang) {
            $query = Category::query();

            if (!$showAll) {
                $query->where('is_active', true);
            }

            // ── Fix 2: Remove Schema::hasTable() — use withCount() directly ──
            // The application is fully migrated; the products table always exists.
            // Schema::hasTable() performs a costly information_schema query on every
            // request. Removing it eliminates the unnecessary system-table overhead.
            $query->withCount(['products' => function ($q) {
                $q->where('is_active', true);
            }]);

            return $query->orderBy('name_en')->get()->map(function (Category $category) use ($lang) {
                return [
                    'id'             => $category->id,
                    'slug'           => $category->slug,
                    'name'           => [
                        'en' => $category->name_en,
                        'ar' => $category->name_ar ?: $category->name_en,
                    ],
                    'name_en'        => $category->name_en,
                    'name_ar'        => $category->name_ar,
                    'image'          => $category->image_url ?? $category->image,
                    'image_url'      => $category->image_url,
                    'is_active'      => $category->is_active,
                    'products_count' => (int) ($category->products_count ?? 0),
                ];
            })->values()->all();
        });

        return response()->json([
            'data' => $categories,
        ]);
    }
}
