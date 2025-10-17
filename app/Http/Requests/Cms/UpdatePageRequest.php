<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdatePageRequest extends FormRequest
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
        $pageId = $this->route('id');

        return [
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:cms_pages,slug,' . $pageId,
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|array',
            'meta_keywords.*' => 'string|max:100',
            'status' => 'sometimes|in:draft,published,scheduled,archived',
            'json_content' => 'sometimes|array',
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
            'slug.unique' => 'This slug is already in use.',
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
        // Auto-generate slug if title is being updated but slug is not provided
        if ($this->has('title') && (!$this->has('slug') || empty($this->slug))) {
            $this->merge([
                'slug' => Str::slug($this->title)
            ]);
        }

        // Clean meta keywords
        if ($this->has('meta_keywords') && is_array($this->meta_keywords)) {
            $keywords = array_filter($this->meta_keywords, fn($keyword) => !empty(trim($keyword)));
            $this->merge(['meta_keywords' => array_values($keywords)]);
        }

        // Set published_at if status is being changed to published
        if ($this->status === 'published' && !$this->has('published_at')) {
            $this->merge(['published_at' => now()]);
        }
    }
}
