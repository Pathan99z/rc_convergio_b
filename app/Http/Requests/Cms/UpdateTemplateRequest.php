<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateTemplateRequest extends FormRequest
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
        $templateId = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:cms_templates,slug,' . $templateId,
            'type' => 'sometimes|in:page,landing,blog,email,popup',
            'description' => 'nullable|string|max:1000',
            'json_structure' => 'sometimes|array',
            'thumbnail' => 'nullable|string|max:500',
            'is_system' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'settings' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'This slug is already in use.',
            'type.in' => 'Invalid template type selected.',
            'json_structure.array' => 'Template structure must be in valid JSON format.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate slug if name is being updated but slug is not provided
        if ($this->has('name') && (!$this->has('slug') || empty($this->slug))) {
            $this->merge([
                'slug' => Str::slug($this->name)
            ]);
        }
    }
}
