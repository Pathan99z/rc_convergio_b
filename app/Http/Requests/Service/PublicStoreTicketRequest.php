<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicStoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint - no authentication required
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'tenant_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'contact_email.required' => 'Your email address is required.',
            'contact_email.email' => 'Please provide a valid email address.',
            'contact_name.required' => 'Your name is required.',
            'contact_name.max' => 'Your name may not be greater than 255 characters.',
            'company_name.max' => 'Company name may not be greater than 255 characters.',
            'subject.required' => 'The subject field is required.',
            'subject.max' => 'The subject may not be greater than 255 characters.',
            'description.required' => 'The description field is required.',
            'description.max' => 'The description may not be greater than 10000 characters.',
            'priority.in' => 'The priority must be one of: low, medium, high, urgent.',
            'tenant_id.exists' => 'The specified tenant does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'contact_email' => 'email address',
            'contact_name' => 'name',
            'company_name' => 'company name',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default priority if not provided
        if (!$this->has('priority')) {
            $this->merge(['priority' => 'medium']);
        }
    }
}
