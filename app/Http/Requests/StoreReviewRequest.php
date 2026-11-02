<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer_name'  => 'required|string|min:2|max:100',
            'reviewer_email' => 'nullable|email|max:150',
            'rating'         => 'required|integer|min:1|max:5',
            'comment'        => 'required|string|min:5|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'reviewer_name.required' => 'Please provide your name.',
            'rating.required'        => 'A numerical rating between 1 and 5 is required.',
            'rating.min'             => 'Rating must be at least 1 star.',
            'rating.max'             => 'Rating cannot exceed 5 stars.',
            'comment.required'       => 'Please write a review comment.',
            'comment.min'            => 'Comment must be at least 5 characters.',
        ];
    }
}
