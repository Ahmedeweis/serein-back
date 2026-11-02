<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class AdminCategoryController extends Controller
{
    #[OA\Get(
        path: '/api/admin/categories',
        summary: 'List all categories with pagination, search, and active product counts',
        tags: ['Admin Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                description: 'Filter by English or Arabic name',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'is_active',
                in: 'query',
                description: 'Filter by active status (1 or 0)',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                description: 'Items per page (default: 15)',
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
                description: 'Paginated category list retrieved successfully'
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized - Administrative privileges required')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        // Search filter (name_en, name_ar, slug)
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Active status filter
        if ($request->has('is_active') && $request->is_active !== '') {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        // Always load product counts — Schema::hasTable() removed (migrations guarantee table exists)
        $query->withCount(['products as active_products_count' => function ($q) {
            $q->where('is_active', true);
        }]);
        $query->withCount('products as total_products_count');

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $categories = $query->latest('id')->paginate($perPage);

        return response()->json($categories);
    }

    #[OA\Post(
        path: '/api/admin/categories',
        summary: 'Create a new category with optional image upload',
        tags: ['Admin Categories'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name_en', 'name_ar'],
                    properties: [
                        new OA\Property(property: 'name_en', type: 'string', example: 'Mountain Honey'),
                        new OA\Property(property: 'name_ar', type: 'string', example: 'عسل جبلي'),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', description: 'Image file (jpeg,png,jpg,webp, max 2MB)'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category created successfully'),
            new OA\Response(response: 422, description: 'Validation Error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized - Administrative privileges required')
        ]
    )]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Generate unique slug from English name
        $slug = $this->generateUniqueSlug($validated['name_en']);

        // Handle image upload to public disk
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create([
            'name_en'   => $validated['name_en'],
            'name_ar'   => $validated['name_ar'],
            'slug'      => $slug,
            'image'     => $imagePath,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Bust the public categories cache so storefront reflects the new category
        Cache::forget('public_categories_ar_active');
        Cache::forget('public_categories_en_active');
        Cache::forget('admin_categories_ar_all');
        Cache::forget('admin_categories_en_all');

        return response()->json([
            'message'  => 'تم إنشاء القسم بنجاح',
            'category' => $category,
        ], Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/admin/categories/{id}',
        summary: 'Get details of a single category for editing',
        tags: ['Admin Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'Category ID',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category details returned successfully'),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized - Administrative privileges required')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $category = Category::withCount([
            'products as active_products_count' => function ($q) {
                $q->where('is_active', true);
            },
            'products as total_products_count',
        ])->find($id);

        if (! $category) {
            return response()->json([
                'message' => 'القسم غير موجود'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'category' => $category
        ]);
    }

    #[OA\Post(
        path: '/api/admin/categories/{id}',
        summary: 'Update category (supports POST with multipart/form-data or PUT with _method=PUT)',
        tags: ['Admin Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'Category ID',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name_en', type: 'string', example: 'Raw Sidr Honey'),
                        new OA\Property(property: 'name_ar', type: 'string', example: 'عسل سدر خام'),
                        new OA\Property(property: 'image', type: 'string', format: 'binary'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                        new OA\Property(property: '_method', type: 'string', example: 'PUT'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Category updated successfully'),
            new OA\Response(response: 422, description: 'Validation Error'),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized - Administrative privileges required')
        ]
    )]
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'message' => 'القسم غير موجود'
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        // Update English name and regenerate slug if changed
        if (isset($validated['name_en']) && $validated['name_en'] !== $category->name_en) {
            $category->name_en = $validated['name_en'];
            $category->slug = $this->generateUniqueSlug($validated['name_en'], $category->id);
        }

        // Update Arabic name
        if (isset($validated['name_ar'])) {
            $category->name_ar = $validated['name_ar'];
        }

        // Update is_active
        if (array_key_exists('is_active', $validated) && $validated['is_active'] !== null) {
            $category->is_active = $validated['is_active'];
        }

        // Handle image replacement
        if ($request->hasFile('image')) {
            // Delete old physical image file from storage if present
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }

            // Save new image to public/categories
            $category->image = $request->file('image')->store('categories', 'public');
        }

        $category->save();

        // Bust public cache on any mutation
        Cache::forget('public_categories_ar_active');
        Cache::forget('public_categories_en_active');
        Cache::forget('admin_categories_ar_all');
        Cache::forget('admin_categories_en_all');

        return response()->json([
            'message'  => 'تم تحديث القسم بنجاح',
            'category' => $category->fresh(),
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/categories/{id}',
        summary: 'Delete category with safety guard against orphan products',
        tags: ['Admin Categories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'Category ID',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category deleted successfully'),
            new OA\Response(
                response: 409,
                description: 'Conflict - Category has active products associated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Cannot delete category: There are currently 3 active product(s) assigned to this category.'),
                        new OA\Property(property: 'products_count', type: 'integer', example: 3),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized - Administrative privileges required')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'message' => 'القسم غير موجود'
            ], Response::HTTP_NOT_FOUND);
        }

        // Deletion Safety Guard: check for associated products
        $productsCount = $category->products()->count();

        if ($productsCount > 0) {
            return response()->json([
                'message'        => "Cannot delete category: There are currently {$productsCount} active product(s) assigned to this category.",
                'products_count' => $productsCount,
            ], Response::HTTP_CONFLICT);
        }

        // Remove associated image file from disk
        if ($category->image && Storage::disk('public')->exists($category->image)) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        // Bust public cache after deletion
        Cache::forget('public_categories_ar_active');
        Cache::forget('public_categories_en_active');
        Cache::forget('admin_categories_ar_all');
        Cache::forget('admin_categories_en_all');

        return response()->json([
            'message' => 'تم حذف القسم بنجاح'
        ]);
    }

    /**
     * Generate a unique slug for category based on name_en.
     */
    protected function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Category::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
