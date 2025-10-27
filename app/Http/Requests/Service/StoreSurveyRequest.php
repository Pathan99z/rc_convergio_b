<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurveyRequest extends FormRequest
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
            'description' => 'nullable|string|max:1000',
            'type' => ['required', Rule::in(['csat', 'nps', 'post_ticket', 'general'])],
            'is_active' => 'boolean',
            'auto_send' => 'boolean',
            'settings' => 'nullable|array',
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'required|string|max:500',
            'questions.*.type' => ['required', Rule::in(['text', 'rating', 'multiple_choice', 'yes_no'])],
            'questions.*.options' => 'nullable|array',
            'questions.*.is_required' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Survey name is required',
            'type.required' => 'Survey type is required',
            'type.in' => 'Survey type must be one of: csat, nps, post_ticket, general',
            'questions.required' => 'At least one question is required',
            'questions.*.question.required' => 'Question text is required',
            'questions.*.type.required' => 'Question type is required',
            'questions.*.type.in' => 'Question type must be one of: text, rating, multiple_choice, yes_no',
        ];
    }
}