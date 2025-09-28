<?php

namespace App\Http\Requests\Meetings;

use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'scheduled_at' => 'sometimes|date',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'location' => 'nullable|string|max:255',
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(Meeting::getAvailableStatuses()))
            ],
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Meeting title cannot exceed 255 characters.',
            'scheduled_at.date' => 'Please provide a valid date and time.',
            'duration_minutes.min' => 'Meeting duration must be at least 15 minutes.',
            'duration_minutes.max' => 'Meeting duration cannot exceed 8 hours (480 minutes).',
            'status.in' => 'Invalid meeting status selected.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scheduled_at' => 'meeting date and time',
            'duration_minutes' => 'duration',
        ];
    }
}
