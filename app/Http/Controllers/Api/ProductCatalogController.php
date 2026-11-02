<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ProductCatalogController extends Controller
{
    // ─── LIST ──────────────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/products',
        summary: 'Browse active products with bilingual names, variants/sizes, and images for storefront',
        tags: ['Product Catalog'],
        parameters: [
            new OA\Parameter(
                name: 'category_slug',
                in: 'query',
                description: 'Filter by category slug (e.g. honey, oils)',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'flag',
                in: 'query',
                description: 'Showcase flag: featured | best_seller | trending | new_arrival',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['featured', 'best_seller', 'trending', 'new_arrival'])
            ),
            new OA\Parameter(
                name: 'sort',
                in: 'query',
                description: 'Sort: latest (default), price_low, price_high, rating',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['latest', 'price_low', 'price_high', 'rating'], default: 'latest')
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                description: 'Search by English or Arabic product name',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                description: 'Products per page (default: 16)',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 16)
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                description: 'Page number',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated product list formatted for storefront ProductCard'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::active()
            ->with([
                'category:id,name_en,name_ar,slug,image',
                'variants' => fn ($q) => $q->orderBy('price'),
                'images'   => fn ($q) => $q->orderBy('sort_order'),
            ]);

        // Filter by category slug
        if ($request->filled('category_slug')) {
            $categorySlug = $request->query('category_slug');
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Filter by showcase flag
        $flag = $request->query('flag');
        match ($flag) {
            'featured'    => $query->featured(),
            'best_seller' => $query->bestSeller(),
            'trending'    => $query->trending(),
            'new_arrival' => $query->newArrival(),
            default       => null,
        };

        // Keyword Search
        if ($request->filled('search')) {
            $term = trim($request->query('search'));
            $query->where(function ($q) use ($term) {
                $q->where('name_en', 'like', "%{$term}%")
                  ->orWhere('name_ar', 'like', "%{$term}%")
                  ->orWhere('description_en', 'like', "%{$term}%")
                  ->orWhere('description_ar', 'like', "%{$term}%");
            });
        }

        // Sorting
        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'price_low'  => $query->orderBy('rating')->latest('id'), // Or order by variants price
            'price_high' => $query->orderByDesc('rating')->latest('id'),
            'rating'     => $query->orderByDesc('rating')->latest('id'),
            default      => $query->latest('id'),
        };

        $perPage = max(1, min(100, (int) $request->query('per_page', 16)));
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    // ─── SINGLE ────────────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/products/{slug}',
        summary: 'Get full product details with all sizes/variants formatted for storefront ProductCard and Detail view',
        tags: ['Product Catalog'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product detail matching storefront Product interface'),
            new OA\Response(response: 404, description: 'Product not found or inactive'),
        ]
    )]
    public function show(string $slug): JsonResponse
    {
        $product = Product::active()
            ->with([
                'category:id,name_en,name_ar,slug,image',
                'variants' => fn ($q) => $q->orderBy('price'),
                'images'   => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->where(function ($q) use ($slug) {
                if (is_numeric($slug)) {
                    $q->where('id', $slug)->orWhere('slug', $slug);
                } else {
                    $q->where('slug', $slug);
                }
            })
            ->first();

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or currently unavailable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $resource = new ProductResource($product);

        return response()->json([
            'data'    => $resource,
            'product' => $resource, // backwards compatibility if direct property accessed
        ], Response::HTTP_OK);
    }
}
