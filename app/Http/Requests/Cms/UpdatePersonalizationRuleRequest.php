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
            'section_id' => 'sometimes|nullable|string|max:255',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'conditions' => 'sometimes|array|min:1',
            'conditions.*.field' => 'nullable|string', // Accept both 'field' and 'attribute'
            'conditions.*.attribute' => 'nullable|string', // Accept both 'field' and 'attribute'
            'conditions.*.operator' => 'required_with:conditions|string|in:equals,not_equals,contains,not_contains,starts_with,ends_with,in,not_in,greater_than,less_than,between',
            'conditions.*.value' => 'required_with:conditions',
            'variant_data' => 'sometimes|nullable|array',
            'variant_content' => 'nullable', // Accept variant_content as alias
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
            'conditions.*.operator.required_with' => 'Condition operator is required.',
            'conditions.*.operator.in' => 'Invalid condition operator.',
            'conditions.*.value.required_with' => 'Condition value is required.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Map frontend field names to backend names
        $data = [];

        // Map 'attribute' to 'field' in conditions (frontend uses 'attribute', backend uses 'field')
        if ($this->has('conditions') && is_array($this->conditions)) {
            $conditions = [];
            foreach ($this->conditions as $condition) {
                if (isset($condition['attribute']) && !isset($condition['field'])) {
                    $condition['field'] = $condition['attribute'];
                }
                $conditions[] = $condition;
            }
            $data['conditions'] = $conditions;
        }

        // Map 'variant_content' to 'variant_data' (frontend compatibility)
        if ($this->has('variant_content') && !$this->has('variant_data')) {
            $variantContent = $this->input('variant_content');
            // If it's a string, wrap it in an array
            if (is_string($variantContent)) {
                $data['variant_data'] = ['content' => $variantContent];
            } else {
                $data['variant_data'] = $variantContent;
            }
        }

        // Merge the prepared data
        if (!empty($data)) {
            $this->merge($data);
        }
    }
}


