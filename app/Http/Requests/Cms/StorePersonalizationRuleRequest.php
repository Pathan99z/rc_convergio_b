<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonalizationRuleRequest extends FormRequest
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
            'page_id' => 'required|integer|exists:cms_pages,id',
            'section_id' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string|in:equals,not_equals,contains,not_contains,starts_with,ends_with,in,not_in,greater_than,less_than,between',
            'conditions.*.value' => 'required',
            'variant_data' => 'required|array',
            'priority' => 'nullable|integer|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'page_id.required' => 'Page is required.',
            'page_id.exists' => 'Selected page does not exist.',
            'section_id.required' => 'Section ID is required.',
            'name.required' => 'Rule name is required.',
            'conditions.required' => 'At least one condition is required.',
            'conditions.min' => 'At least one condition is required.',
            'conditions.*.field.required' => 'Condition field is required.',
            'conditions.*.operator.required' => 'Condition operator is required.',
            'conditions.*.operator.in' => 'Invalid condition operator.',
            'conditions.*.value.required' => 'Condition value is required.',
            'variant_data.required' => 'Variant data is required.',
            'variant_data.array' => 'Variant data must be in valid JSON format.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default priority if not provided
        if (!$this->has('priority')) {
            $this->merge(['priority' => 0]);
        }

        // Set default is_active if not provided
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
