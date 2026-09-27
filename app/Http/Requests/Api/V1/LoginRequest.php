<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required_without_all:phone,email', 'nullable', 'string'],
            'phone' => ['required_without_all:login,email', 'nullable', 'string'],
            'email' => ['required_without_all:login,phone', 'nullable', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Find the user by the provided login identifier (phone or email).
     */
    public function findUser(): ?User
    {
        $identifier = $this->input('login') ?? $this->input('phone') ?? $this->input('email');

        if (! $identifier) {
            return null;
        }

        return User::where('phone', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }
}
