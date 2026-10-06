<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $cleaned = preg_replace('/\D/', '', (string) $this->input('phone'));
            if (str_starts_with($cleaned, '8801') && strlen($cleaned) === 13) {
                $cleaned = substr($cleaned, 2);
            }
            $this->merge(['phone' => $cleaned]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
            'district' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', 'string', 'in:male,female,unspecified'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number must be a valid 11-digit Bangladeshi mobile number starting with 01 (e.g. 01712345678).',
        ];
    }
}
