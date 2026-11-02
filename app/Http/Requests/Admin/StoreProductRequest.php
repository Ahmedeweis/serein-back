<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Cast boolean-like string fields coming from multipart/form-data
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
        return [
            'name_en'        => ['required', 'string', 'max:255'],
            'name_ar'        => ['nullable', 'string', 'max:255'],
            'category_id'    => ['required', 'integer', 'exists:categories,id'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'unit_type'      => ['nullable', 'string', 'in:weight,volume,unit'],
            'image'          => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['file', 'image', 'mimes:jpeg,png,webp,jpg', 'max:2048'],
            'youtube_url'    => ['nullable', 'string', 'url', 'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i'],
            'is_active'      => ['nullable', 'boolean'],
            'is_featured'    => ['nullable', 'boolean'],
            'is_best_seller' => ['nullable', 'boolean'],
            'is_trending'    => ['nullable', 'boolean'],
            'is_new_arrival' => ['nullable', 'boolean'],

            // Variants array — at least one variant is required
            'variants'              => ['required', 'array', 'min:1'],
            'variants.*.name_en'    => ['required', 'string', 'max:100'],
            'variants.*.name_ar'    => ['nullable', 'string', 'max:100'],
            'variants.*.unit'       => ['required', 'string', 'max:20'],
            'variants.*.value'      => ['nullable', 'numeric', 'min:0'],
            'variants.*.price'      => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock'      => ['required', 'integer', 'min:0'],
            'variants.*.sku'        => ['nullable', 'string', 'max:100', 'distinct', 'unique:product_variants,sku'],
            'variants.*.is_default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name_en.required'          => 'اسم المنتج باللغة الإنجليزية مطلوب.',
            'category_id.required'      => 'يجب اختيار قسم للمنتج.',
            'category_id.exists'        => 'القسم المحدد غير موجود.',
            'image.required'            => 'صورة المنتج مطلوبة.',
            'image.image'               => 'الملف يجب أن يكون صورة.',
            'image.mimes'               => 'الصيغ المدعومة: jpeg, png, jpg, webp.',
            'image.max'                 => 'الحد الأقصى لحجم الصورة 2 ميجابايت.',
            'gallery_images.array'      => 'صور المعرض يجب أن تكون قائمة ملفات.',
            'gallery_images.*.image'    => 'كل ملف في المعرض يجب أن يكون صورة صالحة.',
            'gallery_images.*.mimes'    => 'صيغ صور المعرض المدعومة: jpeg, png, webp, jpg.',
            'gallery_images.*.max'      => 'الحد الأقصى لحجم كل صورة في المعرض 2 ميجابايت.',
            'youtube_url.url'           => 'رابط يوتيوب غير صالح.',
            'youtube_url.regex'         => 'يجب إدخال رابط يوتيوب صحيح (youtube.com أو youtu.be).',
            'variants.required'         => 'يجب إضافة نوع/حجم واحد على الأقل للمنتج.',
            'variants.min'              => 'يجب إضافة نوع/حجم واحد على الأقل للمنتج.',
            'variants.*.price.required' => 'سعر كل نوع/حجم مطلوب.',
            'variants.*.price.min'      => 'يجب أن يكون السعر أكبر من أو يساوي صفر.',
            'variants.*.stock.required' => 'كمية المخزون مطلوبة لكل نوع/حجم.',
            'variants.*.sku.unique'     => 'رمز SKU مستخدم بالفعل لنوع/حجم آخر.',
            'variants.*.sku.distinct'   => 'رموز SKU يجب أن تكون فريدة داخل نفس الطلب.',
        ];
    }
}
