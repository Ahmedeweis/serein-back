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
    // ─── IMPORT / EXPORT (spreadsheet: one row per variant) ─────────────────────

    /** All spreadsheet columns (export order) */
    public const SHEET_COLUMNS = [
        'slug', 'name_en', 'name_ar', 'category', 'description_en', 'description_ar', 'unit_type', 'image_url',
        'is_active', 'is_featured', 'is_best_seller', 'is_trending', 'is_new_arrival',
        'variant_name_en', 'variant_name_ar', 'variant_unit', 'variant_value', 'variant_price',
        'variant_compare_at_price', 'variant_stock', 'variant_sku', 'variant_is_default',
    ];

    /**
     * Same essentials as the "Add Product" form, except the image: imported products may have none
     * (a placeholder is shown until an image is uploaded from Edit). Every other column may be left empty.
     */
    public const REQUIRED_COLUMNS = ['name_en', 'category', 'variant_name_en', 'variant_unit', 'variant_price', 'variant_stock'];

    private const BOOLEAN_COLUMNS = ['is_active', 'is_featured', 'is_best_seller', 'is_trending', 'is_new_arrival', 'variant_is_default'];

    private const MAX_IMPORT_ROWS = 5000;

    /** All products flattened to spreadsheet rows (same columns as the import) */
    public function export(): JsonResponse
    {
        $rows = [];
        $yesNo = fn ($v) => $v ? 'yes' : 'no';

        Product::with(['category:id,slug', 'variants' => fn ($q) => $q->orderByDesc('is_default')->orderBy('id')])
            ->orderBy('id')
            ->chunk(200, function ($products) use (&$rows, $yesNo) {
                foreach ($products as $p) {
                    foreach ($p->variants as $v) {
                        $rows[] = [
                            'slug'                     => $p->slug,
                            'name_en'                  => $p->name_en,
                            'name_ar'                  => $p->name_ar,
                            'category'                 => $p->category?->slug,
                            'description_en'           => $p->description_en,
                            'description_ar'           => $p->description_ar,
                            'unit_type'                => $p->unit_type,
                            'image_url'                => $p->image_url,
                            'is_active'                => $yesNo($p->is_active),
                            'is_featured'              => $yesNo($p->is_featured),
                            'is_best_seller'           => $yesNo($p->is_best_seller),
                            'is_trending'              => $yesNo($p->is_trending),
                            'is_new_arrival'           => $yesNo($p->is_new_arrival),
                            'variant_name_en'          => $v->name_en,
                            'variant_name_ar'          => $v->name_ar,
                            'variant_unit'             => $v->unit,
                            'variant_value'            => $v->value,
                            'variant_price'            => $v->price,
                            'variant_compare_at_price' => $v->compare_at_price,
                            'variant_stock'            => $v->stock,
                            'variant_sku'              => $v->sku,
                            'variant_is_default'       => $yesNo($v->is_default),
                        ];
                    }
                }
            });

        return response()->json(['columns' => self::SHEET_COLUMNS, 'data' => $rows]);
    }

    /**
     * Create/update products from spreadsheet rows.
     * Payload: { rows: [{ row: <sheet row number>, slug: …, …SHEET_COLUMNS }] }
     * Rows sharing a slug are variants of one product (product fields are read from its first row).
     * An empty slug is generated from name_en. Only REQUIRED_COLUMNS must be filled, like the product form.
     * Nothing is saved unless every row is valid; errors list the sheet row and column.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'rows'   => ['required', 'array', 'min:1', 'max:' . self::MAX_IMPORT_ROWS],
            'rows.*' => ['array'],
        ]);

        $errors = [];
        $addError = function (int $row, string $field, string $message) use (&$errors) {
            $errors[] = ['row' => $row, 'field' => $field, 'message' => $message];
        };

        $categories = DB::table('categories')->pluck('id', 'slug')->mapWithKeys(fn ($id, $slug) => [Str::lower($slug) => $id]);
        $groups     = [];
        $skusInFile = [];

        foreach ($request->input('rows') as $index => $raw) {
            $rowNumber = (int) ($raw['row'] ?? $index + 2);
            $row = [];

            // 1. Required columns (same as the product form); the rest may be empty
            foreach (self::SHEET_COLUMNS as $col) {
                $value = is_scalar($raw[$col] ?? null) ? trim((string) $raw[$col]) : '';
                if ($value === '' && in_array($col, self::REQUIRED_COLUMNS, true)) {
                    $addError($rowNumber, $col, 'This field is required.');
                }
                $row[$col] = $value;
            }
            if ($row['slug'] === '' && $row['name_en'] !== '') {
                $row['slug'] = Str::slug($row['name_en']);
                if ($row['slug'] === '') {
                    $addError($rowNumber, 'slug', 'Could not build a slug from name_en — please fill the slug column.');
                }
            }

            // 2. Formats
            if ($row['slug'] !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $row['slug'])) {
                $addError($rowNumber, 'slug', 'Use lowercase English letters, numbers and dashes only (e.g. sidr-honey).');
            }
            if ($row['category'] !== '' && !$categories->has(Str::lower($row['category']))) {
                $addError($rowNumber, 'category', "Category \"{$row['category']}\" does not exist. Use the category slug.");
            }
            if ($row['unit_type'] !== '' && !in_array($row['unit_type'], ['weight', 'volume', 'unit'], true)) {
                $addError($rowNumber, 'unit_type', 'Must be weight, volume or unit.');
            }
            if ($row['image_url'] !== '' && !filter_var($row['image_url'], FILTER_VALIDATE_URL)) {
                $addError($rowNumber, 'image_url', 'Must be a full image link (https://…).');
            }
            foreach (self::BOOLEAN_COLUMNS as $col) {
                if ($row[$col] === '') {
                    $row[$col] = null; // empty = default (new product) / unchanged (existing product)
                    continue;
                }
                $bool = $this->parseSheetBoolean($row[$col]);
                if ($bool === null) {
                    $addError($rowNumber, $col, 'Must be yes or no.');
                }
                $row[$col] = $bool;
            }
            foreach (['variant_value', 'variant_price', 'variant_compare_at_price'] as $col) {
                if ($row[$col] !== '' && (!is_numeric($row[$col]) || (float) $row[$col] < 0)) {
                    $addError($rowNumber, $col, 'Must be a number of 0 or more.');
                }
            }
            if ($row['variant_stock'] !== '' && !ctype_digit($row['variant_stock'])) {
                $addError($rowNumber, 'variant_stock', 'Must be a whole number of 0 or more.');
            }
            foreach (['name_en' => 255, 'name_ar' => 255, 'variant_name_en' => 100, 'variant_name_ar' => 100, 'variant_unit' => 20, 'variant_sku' => 100] as $col => $max) {
                if (mb_strlen($row[$col]) > $max) {
                    $addError($rowNumber, $col, "Must be at most {$max} characters.");
                }
            }
            if ($row['variant_sku'] !== '') {
                $skuKey = Str::lower($row['variant_sku']);
                if (isset($skusInFile[$skuKey])) {
                    $addError($rowNumber, 'variant_sku', "Duplicate SKU — already used on row {$skusInFile[$skuKey]}.");
                }
                $skusInFile[$skuKey] = $rowNumber;
            }

            $row['_row'] = $rowNumber;
            if ($row['slug'] !== '') {
                $groups[$row['slug']][] = $row;
            }
        }

        // 3. Per-product checks: at most one default variant; SKUs not owned by another product
        $existing = Product::whereIn('slug', array_keys($groups))->pluck('id', 'slug');
        $skuValues = collect($groups)->flatten(1)->pluck('variant_sku')->filter()->unique()->values()->all();
        $skuOwners = ProductVariant::whereIn('sku', $skuValues)
            ->get(['id', 'product_id', 'sku'])
            ->keyBy(fn ($v) => Str::lower($v->sku));

        foreach ($groups as $slug => $rows) {
            $defaults = array_filter($rows, fn ($r) => $r['variant_is_default'] === true);
            if (count($defaults) > 1) {
                $addError($rows[0]['_row'], 'variant_is_default', "Product \"{$slug}\" has " . count($defaults) . ' default sizes — only one can be yes (or leave all empty to use the first).');
            }
            foreach ($rows as $r) {
                if ($r['variant_sku'] === '') {
                    continue;
                }
                $owner = $skuOwners->get(Str::lower($r['variant_sku']));
                if ($owner && $owner->product_id !== ($existing[$slug] ?? null)) {
                    $addError($r['_row'], 'variant_sku', "SKU \"{$r['variant_sku']}\" already belongs to another product.");
                }
            }
        }

        if ($errors) {
            usort($errors, fn ($a, $b) => [$a['row'], array_search($a['field'], self::SHEET_COLUMNS)] <=> [$b['row'], array_search($b['field'], self::SHEET_COLUMNS)]);

            return response()->json([
                'message' => 'The file has ' . count($errors) . ' error(s). Nothing was imported.',
                'errors'  => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 4. Save everything in one transaction
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($groups, $categories, $skuOwners, &$created, &$updated) {
            foreach ($groups as $slug => $rows) {
                $first   = $rows[0];
                $product = Product::where('slug', $slug)->first();

                // Empty settings: keep the current value, or use the "Add Product" form default for new products
                $setting = fn (string $col, mixed $default) => $first[$col] !== null && $first[$col] !== ''
                    ? $first[$col]
                    : ($product ? $product->{$col} : $default);

                $attributes = [
                    'category_id'    => $categories[Str::lower($first['category'])],
                    'name_en'        => $first['name_en'],
                    'name_ar'        => $first['name_ar'] ?: null,
                    'description_en' => $first['description_en'] ?: null,
                    'description_ar' => $first['description_ar'] ?: null,
                    'unit_type'      => $setting('unit_type', 'weight'),
                    'image'          => $first['image_url'] ?: null,
                    'is_active'      => $setting('is_active', true),
                    'is_featured'    => $setting('is_featured', false),
                    'is_best_seller' => $setting('is_best_seller', false),
                    'is_trending'    => $setting('is_trending', false),
                    'is_new_arrival' => $setting('is_new_arrival', true),
                ];

                if ($product) {
                    // Keep the current image when the cell is empty (images are managed from Edit)
                    // or holds the same image's public URL (keeps the stored file path)
                    if ($attributes['image'] === null || $attributes['image'] === $product->image_url) {
                        unset($attributes['image']);
                    }
                    $product->update($attributes);
                    $updated++;
                } else {
                    $product = Product::create($attributes + ['slug' => $slug]);
                    $created++;
                }

                // Match rows to existing variants (by SKU, then by English name) so variant IDs stay stable
                $unmatched = $product->variants()->get(['id', 'sku', 'name_en'])->keyBy('id');
                $variants = array_map(function ($r) use ($skuOwners, $product, $unmatched) {
                    $owner = $r['variant_sku'] !== '' ? $skuOwners->get(Str::lower($r['variant_sku'])) : null;
                    $id = $owner && $owner->product_id === $product->id ? $owner->id : null;
                    $id ??= $unmatched->first(fn ($v) => Str::lower((string) $v->name_en) === Str::lower($r['variant_name_en']))?->id;
                    if ($id) {
                        $unmatched->forget($id);
                    }
                    $compare = (float) $r['variant_compare_at_price'];

                    return [
                        'id'               => $id,
                        'name_en'          => $r['variant_name_en'],
                        'name_ar'          => $r['variant_name_ar'] ?: null,
                        'unit'             => $r['variant_unit'],
                        'value'            => $r['variant_value'] !== '' ? (float) $r['variant_value'] : null, // empty = read from size name
                        'price'            => (float) $r['variant_price'],
                        'compare_at_price' => $compare > 0 ? $compare : null, // empty or 0 = no discount
                        'stock'            => (int) $r['variant_stock'],
                        'sku'              => $r['variant_sku'] ?: null,
                        'is_default'       => (bool) $r['variant_is_default'], // none set = first size
                    ];
                }, $rows);

                $this->syncVariants($product, $variants);
            }
        });

        return response()->json([
            'message' => "Import complete: {$created} product(s) created, {$updated} updated.",
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    private function parseSheetBoolean(string $value): ?bool
    {
        return match (Str::lower(trim($value))) {
            'yes', 'y', 'true', '1', 'نعم' => true,
            'no', 'n', 'false', '0', 'لا'  => false,
            default                        => null,
        };
    }

    // ─── SHOWCASE SECTIONS ORDER (best sellers, bundles, new arrivals, trending) ─

    /** Products of a showcase section in their storefront display order */
    public function showcase(string $section): JsonResponse
    {
        abort_unless(isset(Product::SHOWCASE_SECTIONS[$section]), 404, 'Unknown section.');
        [$flag, $positionColumn] = Product::SHOWCASE_SECTIONS[$section];

        $products = Product::where($flag, true)
            ->orderByShowcasePosition($section)
            ->latest('id')
            ->get(['id', 'name_en', 'name_ar', 'slug', 'image', 'is_active', $positionColumn])
            ->map(fn (Product $p) => [
                'id'        => $p->id,
                'name_en'   => $p->name_en,
                'name_ar'   => $p->name_ar,
                'slug'      => $p->slug,
                'image_url' => $p->image_url,
                'is_active' => $p->is_active,
                'position'  => $p->{$positionColumn},
            ]);

        return response()->json(['data' => $products]);
    }

    /** Save a section's order: ids[0] gets position 1, ids[1] position 2, … */
    public function reorderShowcase(Request $request, string $section): JsonResponse
    {
        abort_unless(isset(Product::SHOWCASE_SECTIONS[$section]), 404, 'Unknown section.');
        [, $positionColumn] = Product::SHOWCASE_SECTIONS[$section];

        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        DB::transaction(function () use ($validated, $positionColumn) {
            // Clear old positions so products no longer in the list fall to the end
            Product::whereNotNull($positionColumn)->update([$positionColumn => null]);

            foreach ($validated['ids'] as $index => $id) {
                Product::whereKey($id)->update([$positionColumn => $index + 1]);
            }
        });

        return response()->json(['message' => 'Order saved successfully.']);
    }

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
