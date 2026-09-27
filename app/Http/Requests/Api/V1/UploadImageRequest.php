<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadImageRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required_without_all:image,file', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'image' => ['required_without_all:avatar,file', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'file' => ['required_without_all:avatar,image', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    /**
     * Get the uploaded image file regardless of field key used.
     */
    public function getImageFile(): UploadedFile
    {
        return $this->file('avatar') ?? $this->file('image') ?? $this->file('file');
    }
}
