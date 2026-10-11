<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($this->input('type') === 'direct') {
            return $user->hasAnyRole(['admin', 'data_entry_operator', 'deo']);
        }

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
            'type' => ['required', 'string', 'in:support,practitioner,direct'],
            'target_user_id' => ['required_if:type,direct', 'nullable', 'integer', 'exists:users,id'],
            'context_type' => ['nullable', 'string', 'in:order,service_request'],
            'context_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
        ];
    }
}
