<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $boolFields = ['is_active', 'is_featured', 'is_best_seller', 'is_trending', 'is_new_arrival'];
        $merges = [];
        foreach ($boolFields as $field) {
            if ($this->has($field)) {
                $merges[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        // Decode variants JSON string if sent as a single JSON field
        if ($this->has('variants') && is_string($this->input('variants'))) {
            $decoded = json_decode($this->input('variants'), true);
            if (is_array($decoded)) {
                $merges['variants'] = $decoded;
            }
        }

        if ($this->has('deleted_gallery_image_ids') && is_string($this->input('deleted_gallery_image_ids'))) {
            $decoded = json_decode($this->input('deleted_gallery_image_ids'), true);
            if (is_array($decoded)) {
                $merges['deleted_gallery_image_ids'] = $decoded;
            }
        }

        if ($this->has('gallery_images_order') && is_string($this->input('gallery_images_order'))) {
            $decoded = json_decode($this->input('gallery_images_order'), true);
            if (is_array($decoded)) {
                $merges['gallery_images_order'] = $decoded;
            }
        }

        if (isset($merges['variants']) && is_array($merges['variants'])) {
            foreach ($merges['variants'] as &$v) {
                if (!isset($v['value']) || $v['value'] === '' || $v['value'] === null) {
                    preg_match('/[0-9]+(?:\.[0-9]+)?/', $v['name_en'] ?? '', $matches);
                    $v['value'] = isset($matches[0]) ? (float) $matches[0] : 0;
                }
            }
            unset($v);
        }

        if ($merges) {
            $this->merge($merges);
        }
    }

    public function rules(): array
    {
        // Collect existing variant IDs belonging to this product to exclude from unique check
        $productId = $this->route('id');

        return [
            'name_en'        => ['sometimes', 'string', 'max:255'],
            'name_ar'        => ['nullable', 'string', 'max:255'],
            'category_id'    => ['sometimes', 'integer', 'exists:categories,id'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'unit_type'      => ['nullable', 'string', 'in:weight,volume,unit'],
            'image'          => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['file', 'image', 'mimes:jpeg,png,webp,jpg', 'max:2048'],
            'deleted_gallery_image_ids'   => ['nullable', 'array'],
            'deleted_gallery_image_ids.*' => ['integer', 'exists:product_images,id'],
            'gallery_images_order'   => ['nullable', 'array'],
            'gallery_images_order.*' => ['integer'],
            'youtube_url'    => ['nullable', 'string', 'url', 'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i'],
            'is_active'      => ['nullable', 'boolean'],
            'is_featured'    => ['nullable', 'boolean'],
            'is_best_seller' => ['nullable', 'boolean'],
            'is_trending'    => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],

            // Variants — optional on update (partial update supported)
            'variants'                  => ['nullable', 'array'],
            'variants.*.id'             => ['nullable', 'integer'],
            'variants.*.name_en'        => ['required_with:variants', 'string', 'max:100'],
            'variants.*.name_ar'        => ['nullable', 'string', 'max:100'],
            'variants.*.unit'           => ['required_with:variants', 'string', 'max:20'],
            'variants.*.value'          => ['nullable', 'numeric', 'min:0'],
            'variants.*.price'          => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock'          => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.sku'            => [
                'nullable',
                'string',
                'max:100',
                'distinct',
                // Unique except for variants belonging to the same product
                Rule::unique('product_variants', 'sku')->where(function ($query) use ($productId) {
                    // Exclude all variants of the product being updated
                    return $query->whereNotIn(
                        'id',
                        \App\Models\ProductVariant::where('product_id', $productId)->pluck('id')
                    );
                }),
            ],
            'variants.*.is_default'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists'            => 'القسم المحدد غير موجود.',
            'image.image'                   => 'الملف يجب أن يكون صورة.',
            'image.mimes'                   => 'الصيغ المدعومة: jpeg, png, jpg, webp.',
            'image.max'                     => 'الحد الأقصى لحجم الصورة 2 ميجابايت.',
            'gallery_images.array'          => 'صور المعرض يجب أن تكون قائمة ملفات.',
            'gallery_images.*.image'        => 'كل ملف في المعرض يجب أن يكون صورة صالحة.',
            'gallery_images.*.mimes'        => 'صيغ صور المعرض المدعومة: jpeg, png, webp, jpg.',
            'gallery_images.*.max'          => 'الحد الأقصى لحجم كل صورة في المعرض 2 ميجابايت.',
            'youtube_url.url'               => 'رابط يوتيوب غير صالح.',
            'youtube_url.regex'             => 'يجب إدخال رابط يوتيوب صحيح (youtube.com أو youtu.be).',
            'variants.*.price.required_with' => 'سعر كل نوع/حجم مطلوب.',
            'variants.*.stock.required_with' => 'كمية المخزون مطلوبة لكل نوع/حجم.',
            'variants.*.sku.unique'         => 'رمز SKU مستخدم بالفعل.',
            'variants.*.sku.distinct'       => 'رموز SKU يجب أن تكون فريدة.',
        ];
    }
}
