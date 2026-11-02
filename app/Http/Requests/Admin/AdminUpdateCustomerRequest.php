<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('name') && !$this->filled('first_name')) {
            $parts = explode(' ', trim($this->input('name')), 2);
            $merge['first_name'] = $parts[0];
            $merge['last_name'] = $parts[1] ?? '';
        }

        if ($this->filled('phone')) {
            $phone = preg_replace('/[\s\-\(\)]/', '', $this->input('phone'));
            if (str_starts_with($phone, '+20')) {
                $phone = '0' . substr($phone, 3);
            } elseif (str_starts_with($phone, '0020')) {
                $phone = '0' . substr($phone, 4);
            } elseif (str_starts_with($phone, '20') && strlen($phone) === 12) {
                $phone = '0' . substr($phone, 2);
            }
            $merge['phone'] = $phone;
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $customerId = (int) $this->route('id');

        return [
            'first_name'  => ['sometimes', 'required', 'string', 'max:100'],
            'last_name'   => ['nullable', 'string', 'max:100'],
            'email'       => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customerId)],
            'phone'       => ['sometimes', 'required', 'string', 'regex:/^01[0125][0-9]{8}$/', Rule::unique('users', 'phone')->ignore($customerId)],
            'password'    => ['nullable', 'string', 'min:6'],
            'country'     => ['nullable', 'string', 'max:100'],
            'governorate' => ['nullable', 'string', 'max:100'],
            'address'     => ['nullable', 'string', 'max:1000'],
        ];
    }
}
