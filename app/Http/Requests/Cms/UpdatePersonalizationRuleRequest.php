<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonalizationRuleRequest extends FormRequest
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
        return [
            'page_id' => 'sometimes|integer|exists:cms_pages,id',
            'section_id' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'conditions' => 'sometimes|array|min:1',
            'conditions.*.field' => 'required_with:conditions|string',
            'conditions.*.operator' => 'required_with:conditions|string|in:equals,not_equals,contains,not_contains,starts_with,ends_with,in,not_in,greater_than,less_than,between',
            'conditions.*.value' => 'required_with:conditions',
            'variant_data' => 'sometimes|array',
            'priority' => 'sometimes|integer|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'page_id.exists' => 'Selected page does not exist.',
            'conditions.min' => 'At least one condition is required.',
            'conditions.*.field.required_with' => 'Condition field is required.',
            'conditions.*.operator.required_with' => 'Condition operator is required.',
            'conditions.*.operator.in' => 'Invalid condition operator.',
            'conditions.*.value.required_with' => 'Condition value is required.',
            'variant_data.array' => 'Variant data must be in valid JSON format.',
        ];
    }
}
