<?php

namespace App\Http\Requests\Help;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleFeedbackRequest extends FormRequest
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
            'feedback' => ['required', 'string', Rule::in(['helpful', 'not_helpful'])],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'feedback.required' => 'Feedback is required.',
            'feedback.in' => 'Feedback must be either "helpful" or "not_helpful".',
            'contact_email.email' => 'Contact email must be a valid email address.',
            'contact_email.max' => 'Contact email may not be greater than 255 characters.',
            'contact_name.max' => 'Contact name may not be greater than 255 characters.',
        ];
    }
}
