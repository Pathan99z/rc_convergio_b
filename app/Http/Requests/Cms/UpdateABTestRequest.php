<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateABTestRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'traffic_split' => 'sometimes|integer|min:10|max:90',
            'goals' => 'sometimes|array',
            'goals.*.type' => 'required_with:goals|string|in:click,form_submit,page_view,custom',
            'goals.*.target' => 'required_with:goals|string',
            'goals.*.value' => 'nullable|numeric',
            'min_sample_size' => 'sometimes|integer|min:100|max:100000',
            'confidence_level' => 'sometimes|numeric|min:80|max:99.9',
            'status' => 'sometimes|in:draft,running,paused,completed,archived',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'traffic_split.min' => 'Traffic split must be at least 10%.',
            'traffic_split.max' => 'Traffic split cannot exceed 90%.',
            'min_sample_size.min' => 'Minimum sample size must be at least 100.',
            'confidence_level.min' => 'Confidence level must be at least 80%.',
            'status.in' => 'Invalid test status.',
        ];
    }
}



