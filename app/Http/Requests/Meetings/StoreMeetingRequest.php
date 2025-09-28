<?php

namespace App\Http\Requests\Meetings;

use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'contact_id' => 'required|integer|exists:contacts,id',
            'user_id' => 'nullable|integer|exists:users,id',
            // Support both frontend format (start_time/end_time) and backend format (scheduled_at)
            'start_time' => 'required_without:scheduled_at|date|after:now',
            'end_time' => 'required_with:start_time|date|after:start_time',
            'scheduled_at' => 'required_without:start_time|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'location' => 'nullable|string|max:255',
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(Meeting::getAvailableStatuses()))
            ],
            'integration_provider' => [
                'nullable',
                'string',
                Rule::in(array_keys(Meeting::getAvailableProviders()))
            ],
            'provider' => [
                'nullable',
                'string',
                Rule::in(array_keys(Meeting::getAvailableProviders()))
            ],
            'integration_data' => 'nullable|array',
            'attendees' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
            'meeting_link' => 'nullable|string|max:500',
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
            'title.required' => 'Meeting title is required.',
            'title.max' => 'Meeting title cannot exceed 255 characters.',
            'contact_id.required' => 'Please select a contact for the meeting.',
            'contact_id.exists' => 'The selected contact does not exist.',
            'start_time.required' => 'Meeting start time is required.',
            'start_time.after' => 'Meeting must be scheduled for a future date and time.',
            'end_time.required' => 'Meeting end time is required.',
            'end_time.after' => 'End time must be after start time.',
            'scheduled_at.required' => 'Meeting date and time is required.',
            'scheduled_at.after' => 'Meeting must be scheduled for a future date and time.',
            'duration_minutes.min' => 'Meeting duration must be at least 15 minutes.',
            'duration_minutes.max' => 'Meeting duration cannot exceed 8 hours (480 minutes).',
            'integration_provider.in' => 'Invalid meeting provider selected.',
            'provider.in' => 'Invalid meeting provider selected.',
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
            'contact_id' => 'contact',
            'user_id' => 'organizer',
            'start_time' => 'start time',
            'end_time' => 'end time',
            'scheduled_at' => 'meeting date and time',
            'duration_minutes' => 'duration',
            'integration_provider' => 'meeting provider',
            'provider' => 'meeting provider',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();
        
        // Map provider to integration_provider
        if (isset($data['provider']) && !isset($data['integration_provider'])) {
            $this->merge(['integration_provider' => $data['provider']]);
        }

        // Handle start_time and end_time from frontend
        if (isset($data['start_time']) && isset($data['end_time'])) {
            try {
                // Parse the datetime strings
                $startTime = \Carbon\Carbon::parse($data['start_time']);
                $endTime = \Carbon\Carbon::parse($data['end_time']);
                
                // Calculate duration in minutes
                $durationMinutes = $startTime->diffInMinutes($endTime);
                
                // Ensure duration is at least 15 minutes
                if ($durationMinutes < 15) {
                    $durationMinutes = 15;
                }
                
                $this->merge([
                    'scheduled_at' => $startTime->toDateTimeString(),
                    'duration_minutes' => $durationMinutes
                ]);
                
            } catch (\Exception $e) {
                // If parsing fails, set default values
                $this->merge([
                    'scheduled_at' => $data['start_time'],
                    'duration_minutes' => 30
                ]);
            }
        } elseif (isset($data['start_time']) && !isset($data['scheduled_at'])) {
            // Only start_time provided, set scheduled_at and default duration
            $this->merge([
                'scheduled_at' => $data['start_time'],
                'duration_minutes' => 30
            ]);
        }
    }
}
