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
            'photo' => ['required_without_all:avatar,image,file', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'],
            'avatar' => ['required_without_all:photo,image,file', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'],
            'image' => ['required_without_all:photo,avatar,file', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'],
            'file' => ['required_without_all:photo,avatar,image', 'nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'],
        ];
    }

    /**
     * Get the uploaded image file regardless of field key used.
     */
    public function getImageFile(): UploadedFile
    {
        return $this->file('photo') ?? $this->file('avatar') ?? $this->file('image') ?? $this->file('file');
    }
}
