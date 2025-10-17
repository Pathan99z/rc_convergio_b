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
            'variant_a_id' => 'required|integer|exists:cms_pages,id',
            'variant_b_id' => 'required|integer|exists:cms_pages,id|different:variant_a_id',
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
            'variant_a_id.required' => 'Variant A page is required.',
            'variant_a_id.exists' => 'Variant A page does not exist.',
            'variant_b_id.required' => 'Variant B page is required.',
            'variant_b_id.exists' => 'Variant B page does not exist.',
            'variant_b_id.different' => 'Variant B must be different from Variant A.',
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
        // Set defaults
        if (!$this->has('traffic_split')) {
            $this->merge(['traffic_split' => 50]);
        }

        if (!$this->has('min_sample_size')) {
            $this->merge(['min_sample_size' => 1000]);
        }

        if (!$this->has('confidence_level')) {
            $this->merge(['confidence_level' => 95.0]);
        }
    }
}
