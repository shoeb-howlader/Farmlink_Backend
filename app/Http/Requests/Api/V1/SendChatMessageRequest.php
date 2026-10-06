<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'Please provide a message or attach an image.',
            'attachment.image' => 'The attachment must be an image file (JPG, PNG, WebP).',
            'attachment.max' => 'The attachment size may not exceed 5MB.',
        ];
    }
}
