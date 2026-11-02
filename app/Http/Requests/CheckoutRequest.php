<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Public checkout is available to both guests and authenticated users.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Customer contact and delivery details
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'email'            => ['required', 'email', 'max:255'],
            'phone'            => ['required', 'string', 'max:30'],
            'address'          => ['required', 'string', 'max:500'],
            'city'             => ['required', 'string', 'max:100'],
            'country'          => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:1000'],

            // Shipping & Payment
            'shipping_zone_id' => ['nullable', 'integer', 'exists:shipping_zones,id'],
            'payment_method'   => ['nullable', 'string', 'max:100'],

            // Coupon Code (Validation of existence & discount is strictly done against DB)
            'coupon_code'      => ['nullable', 'string', 'max:50'],

            // Zero-Trust input notice:
            // Any client-sent shipping_cost, discount_amount, subtotal, or total are ignored in business logic.
            'shipping_cost'    => ['nullable', 'numeric', 'min:0'],
            'discount_amount'  => ['nullable', 'numeric', 'min:0'],
            'total'            => ['nullable', 'numeric'],
            'subtotal'         => ['nullable', 'numeric'],

            // Items Array
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.product_id'      => ['nullable', 'integer', 'exists:products,id'],
            'items.*.variant_id'      => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.size_id'         => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
            'items.*.product_name_en' => ['nullable', 'string', 'max:255'],
            'items.*.product_name_ar' => ['nullable', 'string', 'max:255'],
            'items.*.product_image'   => ['nullable', 'string'],
            'items.*.variant_label'   => ['nullable', 'string', 'max:100'],
            // Client may send price, but server will NEVER trust it
            'items.*.price'           => ['nullable', 'numeric'],
        ];
    }

    /**
     * Custom validation messages for CheckoutRequest.
     */
    public function messages(): array
    {
        return [
            'first_name.required'       => 'Please enter your first name.',
            'last_name.required'        => 'Please enter your last name.',
            'email.required'            => 'Please enter a valid email address.',
            'phone.required'            => 'Please enter a phone number.',
            'address.required'          => 'Please enter your delivery address.',
            'city.required'             => 'Please select a governorate/city.',
            'items.required'            => 'Your cart is empty.',
            'items.min'                 => 'Your cart must contain at least one item.',
            'items.*.quantity.required' => 'Item quantity is required.',
            'items.*.quantity.integer'  => 'Item quantity must be a valid whole number.',
            'items.*.quantity.min'      => 'Item quantity must be at least 1.',
            'items.*.product_id.exists' => 'The selected product does not exist.',
            'items.*.variant_id.exists' => 'The selected product variant does not exist.',
            'shipping_zone_id.exists'   => 'The selected shipping zone does not exist.',
        ];
    }
}
