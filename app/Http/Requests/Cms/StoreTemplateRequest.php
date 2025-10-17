<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreTemplateRequest extends FormRequest
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
            'slug' => 'nullable|string|max:255|unique:cms_templates,slug',
            'type' => 'required|in:page,landing,blog,email,popup',
            'description' => 'nullable|string|max:1000',
            'json_structure' => 'required|array',
            'thumbnail' => 'nullable|string|max:500',
            'is_system' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'settings' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required.',
            'slug.unique' => 'This slug is already in use.',
            'type.required' => 'Template type is required.',
            'type.in' => 'Invalid template type selected.',
            'json_structure.required' => 'Template structure is required.',
            'json_structure.array' => 'Template structure must be in valid JSON format.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate slug if not provided
        if (!$this->has('slug') || empty($this->slug)) {
            $this->merge([
                'slug' => Str::slug($this->name ?? 'template-' . time())
            ]);
        }

        // Set defaults
        if (!$this->has('is_system')) {
            $this->merge(['is_system' => false]);
        }

        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
