<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConsultantRecordRequest extends FormRequest
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
            'recommendation' => ['required', 'string'],
            'next_follow_up' => ['nullable', 'date'],
            'service_request_id' => ['nullable', 'integer', 'exists:service_requests,id'],
            'parent_record_id' => ['nullable', 'integer', 'exists:consultant_records,id'],
            'prescription_items' => ['nullable', 'array'],
            'prescription_items.*.medicine_name' => ['nullable', 'string', 'max:255'],
            'prescription_items.*.item_name' => ['nullable', 'string', 'max:255'],
            'prescription_items.*.type' => ['nullable', 'string', 'max:50'],
            'prescription_items.*.dosage' => ['nullable', 'string', 'max:255'],
            'prescription_items.*.frequency' => ['nullable', 'string', 'max:255'],
            'prescription_items.*.duration' => ['nullable', 'string', 'max:255'],
            'prescription_items.*.instructions' => ['nullable', 'string'],
            'prescription_items.*.reasoning' => ['nullable', 'string'],
            'prescription_items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'recommendations' => ['nullable', 'array'],
            'recommendations.*.item_name' => ['required_with:recommendations', 'string', 'max:255'],
            'recommendations.*.medicine_name' => ['nullable', 'string', 'max:255'],
            'recommendations.*.type' => ['nullable', 'string', 'max:50'],
            'recommendations.*.reasoning' => ['nullable', 'string'],
            'recommendations.*.instructions' => ['nullable', 'string'],
            'recommendations.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'test_results' => ['nullable', 'array'],
            'test_results.*.parameter' => ['required_with:test_results', 'string', 'max:255'],
            'test_results.*.value' => ['required_with:test_results', 'string', 'max:255'],
            'test_results.*.unit' => ['nullable', 'string', 'max:50'],
            'test_results.*.reference_range' => ['nullable', 'string', 'max:100'],
            'test_results.*.flag' => ['nullable', 'string', 'max:50'],
            'photos' => ['nullable', 'array'],
            'photos.*.photo_path' => ['required_with:photos', 'string', 'max:500'],
            'photos.*.caption' => ['nullable', 'string', 'max:255'],
            'photos.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}
