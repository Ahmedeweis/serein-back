<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class AdminProductController extends Controller
{
    // ─── LOOKUP (lightweight list for dropdowns) ───────────────────────────────

    public function lookup(Request $request): JsonResponse
    {
        $query = Product::with(['variants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('price')])
            ->where('is_active', true);

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('name_en', 'like', "%{$s}%")
                  ->orWhere('name_ar', 'like', "%{$s}%");
            });
        }

        $products = $query->orderBy('name_en')->get()->map(function (Product $p) {
            $defaultVariant = $p->variants->firstWhere('is_default', true) ?? $p->variants->first();
            return [
                'id'        => $p->id,
                'name_en'   => $p->name_en,
                'name_ar'   => $p->name_ar,
                'slug'      => $p->slug,
                'image_url' => $p->image_url,
                'variants'  => $p->variants->map(fn ($v) => [
                    'id'         => $v->id,
                    'name_en'    => $v->name_en,
                    'name_ar'    => $v->name_ar,
                    'price'      => (float) $v->price,
                    'stock'      => (int) $v->stock,
                    'is_default' => (bool) $v->is_default,
                ]),
                // Convenience defaults from the default variant
                'price'     => $defaultVariant ? (float) $defaultVariant->price : 0,
                'stock'     => $defaultVariant ? (int) $defaultVariant->stock : 0,
            ];
        });

        return response()->json(['data' => $products]);
    }

    // ─── LIST ──────────────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/admin/products',
        summary: 'Paginated list of all products (active + inactive) with category and variant summary',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search',      in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active',   in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page',    in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated product list'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'variants', 'images'])->withCount('variants');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $products = $query->latest('id')->paginate($perPage);

        return response()->json($products);
    }

    // ─── STORE ─────────────────────────────────────────────────────────────────

    #[OA\Post(
        path: '/api/admin/products',
        summary: 'Create a new product with image upload and variants (multipart/form-data)',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name_en', 'category_id', 'image', 'variants'],
                    properties: [
                        new OA\Property(property: 'name_en',        type: 'string',  example: 'Raw Sidr Honey'),
                        new OA\Property(property: 'name_ar',        type: 'string',  example: 'عسل سدر خام'),
                        new OA\Property(property: 'category_id',    type: 'integer', example: 1),
                        new OA\Property(property: 'description_en', type: 'string'),
                        new OA\Property(property: 'description_ar', type: 'string'),
                        new OA\Property(property: 'unit_type',      type: 'string',  enum: ['weight', 'volume', 'unit']),
                        new OA\Property(property: 'image',          type: 'string',  format: 'binary'),
                        new OA\Property(property: 'is_active',      type: 'boolean'),
                        new OA\Property(property: 'is_featured',    type: 'boolean'),
                        new OA\Property(property: 'is_best_seller', type: 'boolean'),
                        new OA\Property(property: 'is_trending',    type: 'boolean'),
                        new OA\Property(property: 'is_new_arrival', type: 'boolean'),
                        new OA\Property(property: 'variants',       type: 'string',  description: 'JSON-encoded array of variant objects'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created successfully'),
            new OA\Response(response: 422, description: 'Validation Error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($request, $validated) {
            // 1. Upload product image
            $imagePath = $request->file('image')->store('products', 'public');

            // 2. Generate unique slug
            $slug = $this->generateUniqueSlug($validated['name_en']);

            // 3. Create parent product
            $product = Product::create([
                'category_id'    => $validated['category_id'],
                'name_en'        => $validated['name_en'],
                'name_ar'        => $validated['name_ar'] ?? null,
                'slug'           => $slug,
                'description_en' => $validated['description_en'] ?? null,
                'description_ar' => $validated['description_ar'] ?? null,
                'image'          => $imagePath,
                'youtube_url'    => $validated['youtube_url'] ?? null,
                'unit_type'      => $validated['unit_type'] ?? 'weight',
                'is_active'      => $validated['is_active'] ?? true,
                'is_featured'    => $validated['is_featured'] ?? false,
                'is_best_seller' => $validated['is_best_seller'] ?? false,
                'is_trending'    => $validated['is_trending'] ?? false,
                'is_new_arrival' => $validated['is_new_arrival'] ?? true,
            ]);

            // 4. Create variants — ensure exactly one is_default
            $this->syncVariants($product, $validated['variants']);

            // 5. Store optional gallery images
            if ($request->hasFile('gallery_images')) {
                foreach ($request->file('gallery_images') as $index => $file) {
                    $path = $file->store('products/gallery', 'public');
                    $product->images()->create([
                        'image_path' => $path,
                        'sort_order' => $index,
                    ]);
                }
            }

            return response()->json([
                'message' => 'تم إنشاء المنتج بنجاح',
                'product' => $product->load(['category', 'variants', 'images']),
            ], Response::HTTP_CREATED);
        });
    }

    // ─── SHOW ──────────────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/admin/products/{id}',
        summary: 'Get full product data including all variants for editing',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product details'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'variants', 'images'])->find($id);

        if (! $product) {
            return response()->json(['message' => 'المنتج غير موجود'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['product' => $product]);
    }

    // ─── UPDATE ────────────────────────────────────────────────────────────────

    #[OA\Post(
        path: '/api/admin/products/{id}',
        summary: 'Update product (POST with _method=PUT, multipart/form-data)',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: '_method',        type: 'string',  example: 'PUT'),
                        new OA\Property(property: 'name_en',        type: 'string'),
                        new OA\Property(property: 'name_ar',        type: 'string'),
                        new OA\Property(property: 'category_id',    type: 'integer'),
                        new OA\Property(property: 'image',          type: 'string',  format: 'binary'),
                        new OA\Property(property: 'unit_type',      type: 'string',  enum: ['weight', 'volume', 'unit']),
                        new OA\Property(property: 'is_active',      type: 'boolean'),
                        new OA\Property(property: 'variants',       type: 'string',  description: 'JSON-encoded variants array'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Product updated successfully'),
            new OA\Response(response: 422, description: 'Validation Error'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'المنتج غير موجود'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($request, $product, $validated) {
            // Update basic fields
            if (isset($validated['name_en']) && $validated['name_en'] !== $product->name_en) {
                $product->name_en = $validated['name_en'];
                $product->slug = $this->generateUniqueSlug($validated['name_en'], $product->id);
            }

            $fillableMap = [
                'name_ar', 'category_id', 'description_en', 'description_ar',
                'unit_type', 'is_active', 'is_featured', 'is_best_seller',
                'is_trending', 'is_new_arrival', 'youtube_url',
            ];

            foreach ($fillableMap as $field) {
                if (array_key_exists($field, $validated)) {
                    $product->$field = $validated[$field];
                }
            }

            // Replace primary image if a new one is uploaded
            if ($request->hasFile('image')) {
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }
                $product->image = $request->file('image')->store('products', 'public');
            }

            // Remove deleted gallery images
            if ($request->has('deleted_gallery_image_ids')) {
                $deletedIds = $request->input('deleted_gallery_image_ids');
                if (is_string($deletedIds)) {
                    $deletedIds = json_decode($deletedIds, true);
                }
                if (is_array($deletedIds) && count($deletedIds) > 0) {
                    $imagesToDelete = $product->images()->whereIn('id', $deletedIds)->get();
                    foreach ($imagesToDelete as $img) {
                        if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                            Storage::disk('public')->delete($img->image_path);
                        }
                        $img->delete();
                    }
                }
            }

            // Upload new gallery images
            if ($request->hasFile('gallery_images')) {
                $maxSort = (int) $product->images()->max('sort_order');
                foreach ($request->file('gallery_images') as $index => $file) {
                    $path = $file->store('products/gallery', 'public');
                    $product->images()->create([
                        'image_path' => $path,
                        'sort_order' => $maxSort + 1 + $index,
                    ]);
                }
            }

            // Reorder gallery images if order specified
            if ($request->has('gallery_images_order')) {
                $orderData = $request->input('gallery_images_order');
                if (is_string($orderData)) {
                    $orderData = json_decode($orderData, true);
                }
                if (is_array($orderData)) {
                    foreach ($orderData as $order => $imageId) {
                        $product->images()->where('id', $imageId)->update(['sort_order' => (int) $order]);
                    }
                }
            }

            $product->save();

            // Sync variants if provided
            if (isset($validated['variants'])) {
                $this->syncVariants($product, $validated['variants']);
            }

            return response()->json([
                'message' => 'تم تحديث المنتج بنجاح',
                'product' => $product->fresh()->load(['category', 'variants', 'images']),
            ]);
        });
    }

    // ─── DESTROY ───────────────────────────────────────────────────────────────

    #[OA\Delete(
        path: '/api/admin/products/{id}',
        summary: 'Delete a product and its images. Variants and product images cascade-delete automatically.',
        tags: ['Admin Products'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product deleted'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $product = Product::with('images')->find($id);

        if (! $product) {
            return response()->json(['message' => 'المنتج غير موجود'], Response::HTTP_NOT_FOUND);
        }

        // Remove physical main image from disk
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        // Remove physical gallery images from disk
        foreach ($product->images as $img) {
            if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
        }

        // Product images and variants are deleted automatically via DB cascade
        $product->delete();

        return response()->json(['message' => 'تم حذف المنتج بنجاح']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Sync variants: update existing (by ID), create new, delete removed.
     * Guarantees exactly one variant has is_default=true.
     */
    protected function syncVariants(Product $product, array $variantsData): void
    {
        $incomingIds = array_filter(array_column($variantsData, 'id'));

        // Delete variants that are no longer in the payload
        $product->variants()->whereNotIn('id', $incomingIds)->delete();

        // Ensure exactly one is_default — if none supplied, default the first row
        $hasDefault = collect($variantsData)->contains(fn ($v) => ! empty($v['is_default']));

        foreach ($variantsData as $index => $variantData) {
            $isDefault = ! $hasDefault ? ($index === 0) : ! empty($variantData['is_default']);

            $val = $variantData['value'] ?? null;
            if ($val === null || $val === '') {
                preg_match('/[0-9]+(?:\.[0-9]+)?/', $variantData['name_en'] ?? '', $matches);
                $val = isset($matches[0]) ? (float) $matches[0] : 0;
            }

            $data = [
                'product_id'       => $product->id,
                'name_en'          => $variantData['name_en'],
                'name_ar'          => $variantData['name_ar'] ?? null,
                'unit'             => $variantData['unit'],
                'value'            => $val,
                'price'            => $variantData['price'],
                'compare_at_price' => $variantData['compare_at_price'] ?? null,
                'stock'            => $variantData['stock'] ?? 0,
                'sku'              => $variantData['sku'] ?? null,
                'is_default'       => $isDefault,
            ];

            if (! empty($variantData['id'])) {
                ProductVariant::where('id', $variantData['id'])
                              ->where('product_id', $product->id)
                              ->update($data);
            } else {
                ProductVariant::create($data);
            }
        }
    }

    /**
     * Generate a unique slug from an English name, excluding a given product ID.
     */
    protected function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Product::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
