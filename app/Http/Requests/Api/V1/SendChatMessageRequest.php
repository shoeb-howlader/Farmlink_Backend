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
            'attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'Please provide a message or attach an image or PDF.',
            'attachment.mimes' => 'The attachment must be an image (JPG, PNG, WebP) or a PDF document.',
            'attachment.max' => 'The attachment size may not exceed 10MB.',
        ];
    }
}
