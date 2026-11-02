<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ReviewController extends Controller
{
    // ─── LIST APPROVED REVIEWS FOR A PRODUCT ──────────────────────────────────

    #[OA\Get(
        path: '/api/products/{productId}/reviews',
        summary: 'List approved reviews for a specific product',
        tags: ['Product Reviews'],
        parameters: [
            new OA\Parameter(
                name: 'productId',
                in: 'path',
                description: 'Product ID or unique slug',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'sort',
                in: 'query',
                description: 'Sort order: newest (default), highest_rating, lowest_rating',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['newest', 'highest_rating', 'lowest_rating'], default: 'newest')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                description: 'Reviews per page (default: 10)',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 10)
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
            new OA\Response(
                response: 200,
                description: 'Paginated list of approved reviews'
            ),
            new OA\Response(
                response: 404,
                description: 'Product not found'
            ),
        ]
    )]
    public function index(Request $request, string $productId): JsonResponse
    {
        $product = is_numeric($productId)
            ? Product::findOrFail($productId)
            : Product::where('slug', $productId)->firstOrFail();

        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
        $sort    = $request->query('sort', 'newest');

        $query = $product->reviews()
            ->approved()
            ->with('user:id,first_name,last_name');

        match ($sort) {
            'highest_rating' => $query->orderByDesc('rating')->latest('id'),
            'lowest_rating'  => $query->orderBy('rating')->latest('id'),
            default          => $query->latest('id'),
        };

        $reviews = $query->paginate($perPage);

        $breakdown = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];

        $counts = $product->reviews()
            ->approved()
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        foreach ($counts as $stars => $c) {
            if (isset($breakdown[$stars])) {
                $breakdown[$stars] = (int) $c;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $reviews->items(),
            'meta'    => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'per_page'     => $reviews->perPage(),
                'total'        => $reviews->total(),
            ],
            'product_rating' => [
                'rating'        => $product->rating,
                'reviews_count' => $product->reviews_count,
                'breakdown'     => $breakdown,
            ],
        ], Response::HTTP_OK);
    }

    // ─── SUBMIT A NEW REVIEW ──────────────────────────────────────────────────

    #[OA\Post(
        path: '/api/products/{productId}/reviews',
        summary: 'Submit a new customer review for a product',
        tags: ['Product Reviews'],
        parameters: [
            new OA\Parameter(
                name: 'productId',
                in: 'path',
                description: 'Product ID or unique slug',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reviewer_name', 'rating', 'comment'],
                properties: [
                    new OA\Property(property: 'reviewer_name',  type: 'string',  example: 'Ahmed Eweis'),
                    new OA\Property(property: 'reviewer_email', type: 'string',  format: 'email', example: 'ahmed@example.com'),
                    new OA\Property(property: 'rating',         type: 'integer', minimum: 1, maximum: 5, example: 5),
                    new OA\Property(property: 'comment',        type: 'string',  example: 'Exceptional pure honey quality! Very fast shipping.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Review submitted successfully'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed'
            ),
            new OA\Response(
                response: 404,
                description: 'Product not found'
            ),
        ]
    )]
    public function store(StoreReviewRequest $request, string $productId): JsonResponse
    {
        $product = is_numeric($productId)
            ? Product::findOrFail($productId)
            : Product::where('slug', $productId)->firstOrFail();

        $validated = $request->validated();

        $userId = auth('sanctum')->id();

        // New reviews are submitted as 'pending' for admin moderation
        $review = Review::create([
            'product_id'           => $product->id,
            'user_id'              => $userId,
            'reviewer_name'        => $validated['reviewer_name'],
            'reviewer_email'       => $validated['reviewer_email'] ?? null,
            'rating'               => $validated['rating'],
            'comment'              => $validated['comment'],
            'status'               => 'pending',
            'is_verified_purchase' => $userId ? true : false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your review has been submitted successfully and will be visible once approved.',
            'data'    => $review,
        ], Response::HTTP_CREATED);
    }
}
