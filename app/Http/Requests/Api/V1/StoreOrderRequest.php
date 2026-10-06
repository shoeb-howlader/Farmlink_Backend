<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'farm_id' => ['nullable', 'integer', 'exists:farms,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'upazila_id' => ['nullable', 'integer', 'exists:upazilas,id'],
            'union_id' => ['nullable', 'integer', 'exists:unions,id'],
            'pourashava_id' => ['nullable', 'integer', 'exists:pourashavas,id'],
            'district' => ['nullable', 'string', 'max:255'],
            'upazila' => ['nullable', 'string', 'max:255'],
            'union' => ['nullable', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'payment_mode' => ['nullable', 'string', 'max:50'],
        ];
    }
}
