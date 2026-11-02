<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('code')) {
            $merge['code'] = strtoupper(trim((string) $this->code));
        }
        if ($this->has('is_active')) {
            $merge['is_active'] = filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'code'       => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'percentage' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active'  => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_uses'   => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'min_spend'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'       => 'Coupon code is required.',
            'code.unique'         => 'This coupon code already exists.',
            'percentage.required' => 'Discount percentage is required.',
            'percentage.integer'  => 'Discount percentage must be an integer.',
            'percentage.min'      => 'Discount percentage must be at least 1%.',
            'percentage.max'      => 'Discount percentage cannot exceed 100%.',
            'expires_at.date'     => 'Expiration date must be a valid date.',
            'expires_at.after'    => 'Expiration date must be a future date and time.',
            'max_uses.integer'    => 'Max uses must be a positive whole number.',
            'max_uses.min'        => 'Max uses must be at least 1.',
            'max_uses.max'        => 'Max uses cannot exceed 1,000,000.',
        ];
    }
}
