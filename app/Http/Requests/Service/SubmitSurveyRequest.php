<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSurveyRequest extends FormRequest
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
            'responses' => 'required|array',
            'respondent_email' => 'nullable|email|max:255',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'ticket_id' => 'nullable|integer|exists:tickets,id',
            'feedback' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'responses.required' => 'Survey responses are required',
            'responses.array' => 'Survey responses must be an array',
            'respondent_email.email' => 'Please provide a valid email address',
            'contact_id.exists' => 'The specified contact does not exist',
            'ticket_id.exists' => 'The specified ticket does not exist',
            'feedback.max' => 'Feedback cannot exceed 1000 characters',
        ];
    }
}