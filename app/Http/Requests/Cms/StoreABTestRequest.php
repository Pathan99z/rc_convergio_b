<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StoreABTestRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'page_id' => 'required|integer|exists:cms_pages,id',
            'variant_a_id' => 'nullable|integer|exists:cms_pages,id', // Made optional - will default to page_id
            'variant_b_id' => 'nullable|integer|exists:cms_pages,id', // Made optional - will be created or use page_id
            'traffic_split' => 'nullable|integer|min:10|max:90',
            'goals' => 'nullable|array',
            'goals.*.type' => 'required_with:goals|string|in:click,form_submit,page_view,custom',
            'goals.*.target' => 'required_with:goals|string',
            'goals.*.value' => 'nullable|numeric',
            'min_sample_size' => 'nullable|integer|min:100|max:100000',
            'confidence_level' => 'nullable|numeric|min:80|max:99.9',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Test name is required.',
            'page_id.required' => 'Page is required.',
            'page_id.exists' => 'Selected page does not exist.',
            'variant_a_id.exists' => 'Variant A page does not exist.',
            'variant_b_id.exists' => 'Variant B page does not exist.',
            'traffic_split.min' => 'Traffic split must be at least 10%.',
            'traffic_split.max' => 'Traffic split cannot exceed 90%.',
            'min_sample_size.min' => 'Minimum sample size must be at least 100.',
            'confidence_level.min' => 'Confidence level must be at least 80%.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Handle frontend field mapping
        $data = [];

        // Map various frontend field names to backend names
        if ($this->has('testName') && !$this->has('name')) {
            $data['name'] = $this->input('testName');
        }
        if ($this->has('test_name') && !$this->has('name')) {
            $data['name'] = $this->input('test_name');
        }

        // Map target page field
        if ($this->has('targetPage') && !$this->has('page_id')) {
            $data['page_id'] = $this->input('targetPage');
        }
        if ($this->has('target_page') && !$this->has('page_id')) {
            $data['page_id'] = $this->input('target_page');
        }

        // Map description fields
        if ($this->has('variant_description') && !$this->has('description')) {
            $data['description'] = $this->input('variant_description');
        }
        if ($this->has('variantDescription') && !$this->has('description')) {
            $data['description'] = $this->input('variantDescription');
        }

        // Handle goals - convert string to array format if needed
        if ($this->has('goals')) {
            $goals = $this->input('goals');
            if (is_string($goals) && !empty($goals)) {
                $data['goals'] = [
                    [
                        'type' => 'custom',
                        'target' => $goals,
                        'value' => 1
                    ]
                ];
            } elseif (is_array($goals)) {
                // Ensure each goal has required fields
                $processedGoals = [];
                foreach ($goals as $goal) {
                    if (is_array($goal)) {
                        // Ensure target field exists
                        if (!isset($goal['target']) && isset($goal['selector'])) {
                            $goal['target'] = $goal['selector'];
                        }
                        if (!isset($goal['target'])) {
                            $goal['target'] = '#default-target';
                        }
                        // Ensure type exists
                        if (!isset($goal['type'])) {
                            $goal['type'] = 'custom';
                        }
                        $processedGoals[] = $goal;
                    }
                }
                $data['goals'] = $processedGoals;
            }
        } else {
            // If no goals provided, set a default goal
            $data['goals'] = [
                [
                    'type' => 'page_view',
                    'target' => 'page',
                    'value' => 1
                ]
            ];
        }

        // Handle variant IDs - if not provided, use page_id for both variants
        if (!$this->has('variant_a_id')) {
            $data['variant_a_id'] = $this->input('page_id') ?? $this->input('targetPage') ?? $this->input('target_page');
        }

        if (!$this->has('variant_b_id')) {
            $data['variant_b_id'] = $this->input('page_id') ?? $this->input('targetPage') ?? $this->input('target_page');
        }

        // Set defaults for all optional fields
        $data['traffic_split'] = $this->input('traffic_split', 50);
        $data['min_sample_size'] = $this->input('min_sample_size', 1000);
        $data['confidence_level'] = $this->input('confidence_level', 95.0);

        // Ensure we have a description even if empty
        if (!$this->has('description') && !isset($data['description'])) {
            $data['description'] = 'A/B test created from CMS Hub';
        }

        // Merge the prepared data
        $this->merge($data);
    }
}



