<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateReviewRequest;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class AdminReviewController extends Controller
{
    // ─── LIST ALL REVIEWS (ADMIN) ─────────────────────────────────────────────

    #[OA\Get(
        path: '/api/admin/reviews',
        summary: 'List all reviews across products with moderation filters',
        tags: ['Admin Reviews'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'product_id',
                in: 'query',
                description: 'Filter by product ID',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                description: 'Filter by review status: pending, approved, rejected',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'])
            ),
            new OA\Parameter(
                name: 'rating',
                in: 'query',
                description: 'Filter by star rating: 1 to 5',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 5)
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                description: 'Search in reviewer name, email, or comment text',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                description: 'Number of items per page (default: 15)',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 15)
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
                description: 'Paginated reviews retrieved successfully'
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized - Admin only'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));

        $query = Review::with([
            'product:id,name_en,name_ar,slug,image,rating,reviews_count',
            'user:id,first_name,last_name,email',
        ]);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->query('product_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->query('rating'));
        }

        if ($request->filled('search')) {
            $term = trim($request->query('search'));
            $query->where(function ($q) use ($term) {
                $q->where('reviewer_name', 'like', "%{$term}%")
                  ->orWhere('reviewer_email', 'like', "%{$term}%")
                  ->orWhere('comment', 'like', "%{$term}%");
            });
        }

        $reviews = $query->latest('id')->paginate($perPage);

        $stats = [
            'total'      => Review::count(),
            'pending'    => Review::where('status', 'pending')->count(),
            'approved'   => Review::where('status', 'approved')->count(),
            'rejected'   => Review::where('status', 'rejected')->count(),
            'avg_rating' => round((float) (Review::where('status', 'approved')->avg('rating') ?: 5.0), 1),
        ];

        return response()->json([
            'success' => true,
            'data'    => $reviews->items(),
            'meta'    => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'per_page'     => $reviews->perPage(),
                'total'        => $reviews->total(),
            ],
            'stats'   => $stats,
        ], Response::HTTP_OK);
    }

    // ─── SHOW SINGLE REVIEW (ADMIN) ───────────────────────────────────────────

    #[OA\Get(
        path: '/api/admin/reviews/{id}',
        summary: 'Get details of a single review',
        tags: ['Admin Reviews'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Review details'),
            new OA\Response(response: 404, description: 'Review not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $review = Review::with([
            'product:id,name_en,name_ar,slug,image,rating,reviews_count',
            'user:id,first_name,last_name,email',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $review,
        ], Response::HTTP_OK);
    }

    // ─── UPDATE / MODERATE REVIEW (ADMIN) ─────────────────────────────────────

    #[OA\Put(
        path: '/api/admin/reviews/{id}',
        summary: 'Moderate status or edit content of a review',
        tags: ['Admin Reviews'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status',               type: 'string',  enum: ['pending', 'approved', 'rejected'], example: 'approved'),
                    new OA\Property(property: 'reviewer_name',        type: 'string',  example: 'Ahmed Eweis'),
                    new OA\Property(property: 'reviewer_email',       type: 'string',  example: 'ahmed@example.com'),
                    new OA\Property(property: 'rating',               type: 'integer', minimum: 1, maximum: 5, example: 5),
                    new OA\Property(property: 'comment',              type: 'string',  example: 'Updated moderation comments.'),
                    new OA\Property(property: 'is_verified_purchase', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Review updated successfully'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 404, description: 'Review not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function update(UpdateReviewRequest $request, int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->update($request->validated());

        // Refresh model with relations to return updated state
        $review->load([
            'product:id,name_en,name_ar,slug,image,rating,reviews_count',
            'user:id,first_name,last_name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully.',
            'data'    => $review,
        ], Response::HTTP_OK);
    }

    // ─── QUICK STATUS UPDATE (ADMIN) ──────────────────────────────────────────

    #[OA\Patch(
        path: '/api/admin/reviews/{id}/status',
        summary: 'Quick approve or reject review moderation status',
        tags: ['Admin Reviews'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'approved'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Review status updated successfully'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 404, description: 'Review not found'),
        ]
    )]
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,approved,rejected',
        ]);

        $review = Review::findOrFail($id);
        $review->update(['status' => $validated['status']]);

        $review->load([
            'product:id,name_en,name_ar,slug,image,rating,reviews_count',
            'user:id,first_name,last_name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Review status updated to {$validated['status']}.",
            'data'    => $review,
        ], Response::HTTP_OK);
    }

    // ─── DELETE REVIEW (ADMIN) ────────────────────────────────────────────────

    #[OA\Delete(
        path: '/api/admin/reviews/{id}',
        summary: 'Permanently remove a review and recalculate product ratings',
        tags: ['Admin Reviews'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Review deleted successfully'),
            new OA\Response(response: 404, description: 'Review not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully.',
        ], Response::HTTP_OK);
    }
}
