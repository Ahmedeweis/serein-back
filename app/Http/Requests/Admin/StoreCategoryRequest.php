<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name_en'   => ['required', 'string', 'max:255', 'unique:categories,name_en'],
            'name_ar'   => ['required', 'string', 'max:255', 'unique:categories,name_ar'],
            'image'     => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'name_en.required' => 'اسم القسم باللغة الإنجليزية مطلوب.',
            'name_en.unique'   => 'اسم القسم باللغة الإنجليزية مسجل بالفعل.',
            'name_ar.required' => 'اسم القسم باللغة العربية مطلوب.',
            'name_ar.unique'   => 'اسم القسم باللغة العربية مسجل بالفعل.',
            'image.image'      => 'الملف المرفوع يجب أن يكون صورة.',
            'image.mimes'      => 'الصيغ المدعومة للصور هي: jpeg, png, jpg, webp.',
            'image.max'        => 'الحد الأقصى لحجم الصورة هو 2 ميجابايت.',
        ];
    }
}
