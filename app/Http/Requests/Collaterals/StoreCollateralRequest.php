<?php

namespace App\Http\Requests\Collaterals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollateralRequest extends FormRequest
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
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'file' => [
                'required',
                'file',
                'max:102400',
                'mimes:pdf,jpg,jpeg,png,gif,ppt,pptx',
            ],
            'collateral_type' => [
                'required',
                'string',
                Rule::in(['Brochures', 'PowerPoint Presentations', 'User Manuals', 'Infographics']),
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Product selection is required.',
            'product_id.exists' => 'Selected product does not exist or does not belong to your organization.',
            'name.required' => 'Collateral name is required.',
            'file.required' => 'File upload is required.',
            'file.mimes' => 'Only PDF, Images (JPG/PNG/GIF), and PowerPoint files are allowed.',
            'file.max' => 'File size must not exceed 100MB.',
            'collateral_type.required' => 'Collateral type is required.',
            'collateral_type.in' => 'Invalid collateral type selected.',
        ];
    }
}

