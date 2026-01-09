<?php

namespace App\Http\Requests\Collaterals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCollateralRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $tenantId = (int) (optional($this->user())->tenant_id ?? $this->user()->id);

        return [
            'contact_id' => [
                'required',
                'integer',
                Rule::exists('contacts', 'id')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'collateral_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'collateral_ids.*' => [
                'required',
                'integer',
                Rule::exists('collaterals', 'id')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'message' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'contact_id.required' => 'Contact selection is required.',
            'contact_id.exists' => 'Selected contact does not exist or does not belong to your organization.',
            'collateral_ids.required' => 'At least one collateral must be selected.',
            'collateral_ids.array' => 'Collateral IDs must be an array.',
            'collateral_ids.min' => 'At least one collateral must be selected.',
            'collateral_ids.*.exists' => 'One or more selected collaterals do not exist or do not belong to your organization.',
            'message.max' => 'Message must not exceed 1000 characters.',
        ];
    }
}

