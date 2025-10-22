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
            'type' => 'nullable|in:page,landing,blog,email,popup', // Made nullable, will default to 'page'
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
        // Accept 'structure' or 'content' as an alias for 'json_structure' (frontend compatibility)
        if ($this->has('structure') && !$this->has('json_structure')) {
            $this->merge(['json_structure' => $this->input('structure')]);
        }
        if ($this->has('content') && !$this->has('json_structure') && !$this->has('structure')) {
            $this->merge(['json_structure' => $this->input('content')]);
        }

        // Accept 'template_type' or 'category' as alias for 'type'
        if ($this->has('template_type') && !$this->has('type')) {
            $this->merge(['type' => $this->input('template_type')]);
        }
        if ($this->has('category') && !$this->has('type') && !$this->has('template_type')) {
            $this->merge(['type' => $this->input('category')]);
        }

        // Set default type if not provided
        if (!$this->has('type') || empty($this->type)) {
            $this->merge(['type' => 'page']); // Default to 'page' type
        }

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


