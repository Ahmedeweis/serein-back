<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_verified_purchase')) {
            $this->merge([
                'is_verified_purchase' => filter_var(
                    $this->input('is_verified_purchase'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? false,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reviewer_name'        => 'sometimes|string|min:2|max:100',
            'reviewer_email'       => 'nullable|email|max:150',
            'rating'               => 'sometimes|integer|min:1|max:5',
            'comment'              => 'sometimes|string|min:5|max:1000',
            'status'               => 'sometimes|in:pending,approved,rejected',
            'is_verified_purchase' => 'sometimes|boolean',
        ];
    }
}
