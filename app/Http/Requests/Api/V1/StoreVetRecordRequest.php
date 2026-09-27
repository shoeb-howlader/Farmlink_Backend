<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVetRecordRequest extends FormRequest
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
            'visit_date' => ['required', 'date'],
            'findings' => ['required', 'string'],
            'treatment' => ['required', 'string'],
            'medicine_given' => ['nullable', 'string'],
            'next_follow_up' => ['nullable', 'date'],
            'service_request_id' => ['nullable', 'integer', 'exists:service_requests,id'],
        ];
    }
}
