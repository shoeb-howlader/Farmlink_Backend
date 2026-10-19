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
            'attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf,webm,mp4,m4a,ogg,wav,mp3,aac', 'max:15360'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'Please provide a message or attach an image, PDF document, or audio voice note.',
            'attachment.mimes' => 'The attachment must be an image (JPG, PNG, WebP), PDF document, or audio voice note (MP3, WAV, WebM, OGG, M4A).',
            'attachment.max' => 'The attachment size may not exceed 15MB.',
        ];
    }
}
