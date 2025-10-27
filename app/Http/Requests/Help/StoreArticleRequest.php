<?php

namespace App\Http\Requests\Help;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Help\Article::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id ?? $this->user()->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('help_articles')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'summary' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('help_categories', 'id')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Article title is required.',
            'title.max' => 'Article title may not be greater than 255 characters.',
            'slug.regex' => 'Slug may only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This slug is already taken for this tenant.',
            'summary.max' => 'Summary may not be greater than 1000 characters.',
            'content.required' => 'Article content is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'status.required' => 'Article status is required.',
            'status.in' => 'Status must be one of: draft, published, archived.',
            'published_at.date' => 'Published date must be a valid date.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('title') && !$this->has('slug')) {
            $this->merge([
                'slug' => \Illuminate\Support\Str::slug($this->title),
            ]);
        }
    }
}
