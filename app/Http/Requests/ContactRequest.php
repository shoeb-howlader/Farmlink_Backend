<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25'],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'honeypot' => ['nullable', 'prohibited'],
            'website' => ['nullable', 'prohibited'],
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
            'name.required' => 'Please enter your full name.',
            'name.min' => 'Your name must be at least 2 characters long.',
            'name.max' => 'Your name cannot exceed 100 characters.',
            'email.required' => 'Please enter a valid email address.',
            'email.email' => 'Please enter a valid email address format.',
            'subject.required' => 'Please provide a subject for your inquiry.',
            'subject.min' => 'The subject must be at least 3 characters long.',
            'subject.max' => 'The subject cannot exceed 200 characters.',
            'message.required' => 'Please enter your message.',
            'message.min' => 'Your message must be at least 10 characters long.',
            'message.max' => 'Your message cannot exceed 5000 characters.',
            'honeypot.prohibited' => 'Spam bot submission rejected.',
            'website.prohibited' => 'Spam bot submission rejected.',
        ];
    }
}
