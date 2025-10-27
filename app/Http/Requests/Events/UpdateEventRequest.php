<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255|regex:/^[a-zA-Z0-9\s\-_.,!?()]+$/',
            'description' => 'nullable|string|max:5000',
            'type' => 'sometimes|in:webinar,demo,workshop,conference,meeting',
            'scheduled_at' => 'sometimes|date',
            'location' => 'nullable|string|max:500|url',
            'settings' => 'nullable|array',
            'settings.max_attendees' => 'nullable|integer|min:1|max:10000',
            'settings.recording_enabled' => 'nullable|boolean',
            'settings.auto_reminder' => 'nullable|boolean',
            'settings.waiting_room' => 'nullable|boolean',
            'settings.duration' => 'nullable|integer|min:15|max:480',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Event name contains invalid characters.',
            'type.in' => 'Invalid event type selected.',
            'location.url' => 'Location must be a valid URL.',
            'settings.max_attendees.min' => 'Maximum attendees must be at least 1.',
            'settings.max_attendees.max' => 'Maximum attendees cannot exceed 10,000.',
            'settings.duration.min' => 'Event duration must be at least 15 minutes.',
            'settings.duration.max' => 'Event duration cannot exceed 8 hours.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize input to prevent XSS
        if ($this->has('name')) {
            $this->merge(['name' => strip_tags($this->name)]);
        }
        
        if ($this->has('description')) {
            $this->merge(['description' => strip_tags($this->description)]);
        }
        
        if ($this->has('location')) {
            $this->merge(['location' => filter_var($this->location, FILTER_SANITIZE_URL)]);
        }
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Ensure tenant isolation - prevent tampering
        if (isset($validated['tenant_id'])) {
            unset($validated['tenant_id']);
        }
        
        if (isset($validated['created_by'])) {
            unset($validated['created_by']);
        }

        return $validated;
    }
}












