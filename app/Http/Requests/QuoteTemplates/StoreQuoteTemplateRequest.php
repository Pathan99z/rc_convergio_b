<?php

namespace App\Http\Requests\QuoteTemplates;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteTemplateRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'layout' => 'required|in:default,classic,modern,minimal',
            'is_default' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required.',
            'layout.required' => 'Layout is required.',
            'layout.in' => 'Layout must be one of: default, classic, modern, minimal.',
        ];
    }
}
