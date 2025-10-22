<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePageRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_pages,slug',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|array',
            'meta_keywords.*' => 'string|max:100',
            'status' => 'required|in:draft,published,scheduled,archived',
            'json_content' => 'required|array',
            'template_id' => 'nullable|integer|exists:cms_templates,id',
            'domain_id' => 'nullable|integer|exists:cms_domains,id',
            'language_id' => 'nullable|integer|exists:cms_languages,id',
            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date|after:now',
            'settings' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Page title is required.',
            'slug.unique' => 'This slug is already in use.',
            'json_content.required' => 'Page content is required.',
            'json_content.array' => 'Page content must be in valid JSON format.',
            'scheduled_at.after' => 'Scheduled time must be in the future.',
            'template_id.exists' => 'Selected template does not exist.',
            'domain_id.exists' => 'Selected domain does not exist.',
            'language_id.exists' => 'Selected language does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Accept 'content' as an alias for 'json_content' (frontend compatibility)
        if ($this->has('content') && !$this->has('json_content')) {
            $this->merge(['json_content' => $this->input('content')]);
        }

        // Auto-generate slug if not provided
        if (!$this->has('slug') || empty($this->slug)) {
            $this->merge([
                'slug' => Str::slug($this->title ?? 'page-' . time())
            ]);
        }

        // Clean meta keywords
        if ($this->has('meta_keywords') && is_array($this->meta_keywords)) {
            $keywords = array_filter($this->meta_keywords, fn($keyword) => !empty(trim($keyword)));
            $this->merge(['meta_keywords' => array_values($keywords)]);
        }

        // Map frontend field names to backend field names
        if ($this->has('seo_title') && !$this->has('meta_title')) {
            $this->merge(['meta_title' => $this->input('seo_title')]);
        }
        if ($this->has('seo_description') && !$this->has('meta_description')) {
            $this->merge(['meta_description' => $this->input('seo_description')]);
        }
        if ($this->has('seo_keywords') && !$this->has('meta_keywords')) {
            $this->merge(['meta_keywords' => $this->input('seo_keywords')]);
        }

        // Set published_at if status is published and not already set
        if ($this->status === 'published' && !$this->has('published_at')) {
            $this->merge(['published_at' => now()]);
        }
    }
}


