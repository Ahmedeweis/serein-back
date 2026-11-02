<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        // Handle 'name' splitting if first_name/last_name not provided
        if ($this->filled('name') && !$this->filled('first_name')) {
            $parts = explode(' ', trim($this->input('name')), 2);
            $merge['first_name'] = $parts[0];
            $merge['last_name'] = $parts[1] ?? $parts[0];
        }

        // Normalize Egyptian phone format
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

        if (!$this->filled('country')) {
            $merge['country'] = 'Egypt';
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'first_name'  => ['required', 'string', 'max:100'],
            'last_name'   => ['nullable', 'string', 'max:100'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'       => ['required', 'string', 'regex:/^01[0125][0-9]{8}$/', 'unique:users,phone'],
            'password'    => ['required', 'string', 'confirmed', 'min:6'],
            'country'     => ['nullable', 'string'],
            'governorate' => ['required', 'string', 'max:100'],
            'address'     => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex'   => 'رقم الهاتف يجب أن يكون رقم مصري صحيح (مثال: 01012345678).',
            'email.unique'  => 'البريد الإلكتروني مسجل بالفعل.',
            'phone.unique'  => 'رقم الهاتف مسجل بالفعل.',
            'password.min'  => 'كلمة المرور يجب أن لا تقل عن 6 أحرف.',
        ];
    }

}
