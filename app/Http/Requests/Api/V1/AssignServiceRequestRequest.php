<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignServiceRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'practitioner_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $practitioner = User::find($value);
                    if (! $practitioner) {
                        return;
                    }

                    $serviceRequest = $this->route('serviceRequest') ?? $this->route('id');
                    if (is_numeric($serviceRequest)) {
                        $serviceRequest = \App\Models\ServiceRequest::find($serviceRequest);
                    }

                    if ($serviceRequest) {
                        $expectedRole = $serviceRequest->type === 'vet' ? 'veterinary_doctor' : 'consultant';
                        if (! $practitioner->hasRole($expectedRole)) {
                            $fail("The selected practitioner must have the role '{$expectedRole}'.");
                        }
                    }
                },
            ],
        ];
    }
}
