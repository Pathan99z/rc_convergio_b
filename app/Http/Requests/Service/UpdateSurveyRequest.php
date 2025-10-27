<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveyRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => ['sometimes', Rule::in(['csat', 'nps', 'post_ticket', 'general'])],
            'is_active' => 'boolean',
            'auto_send' => 'boolean',
            'settings' => 'nullable|array',
            'questions' => 'sometimes|array|min:1',
            'questions.*.question' => 'required_with:questions|string|max:500',
            'questions.*.type' => ['required_with:questions', Rule::in(['text', 'rating', 'multiple_choice', 'yes_no'])],
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
            'name.string' => 'Survey name must be a string',
            'type.in' => 'Survey type must be one of: csat, nps, post_ticket, general',
            'questions.min' => 'At least one question is required',
            'questions.*.question.required_with' => 'Question text is required',
            'questions.*.type.required_with' => 'Question type is required',
            'questions.*.type.in' => 'Question type must be one of: text, rating, multiple_choice, yes_no',
        ];
    }
}