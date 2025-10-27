<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

class AddAttendeeRequest extends FormRequest
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
            'contact_id' => 'required|exists:contacts,id',
            'rsvp_status' => 'required|in:going,interested,declined',
            'metadata' => 'nullable|array',
            'metadata.source' => 'nullable|string|max:255',
            'metadata.utm_campaign' => 'nullable|string|max:255',
            'metadata.registration_date' => 'nullable|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'contact_id.required' => 'Contact ID is required.',
            'contact_id.exists' => 'The selected contact does not exist.',
            'rsvp_status.required' => 'RSVP status is required.',
            'rsvp_status.in' => 'Invalid RSVP status selected.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize metadata to prevent XSS
        if ($this->has('metadata')) {
            $metadata = $this->metadata;
            if (is_array($metadata)) {
                foreach ($metadata as $key => $value) {
                    if (is_string($value)) {
                        $metadata[$key] = strip_tags($value);
                    }
                }
                $this->merge(['metadata' => $metadata]);
            }
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
        
        if (isset($validated['event_id'])) {
            unset($validated['event_id']);
        }

        return $validated;
    }
}












