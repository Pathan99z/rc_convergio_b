<?php

namespace App\Http\Requests\Campaigns;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'type' => ['nullable', 'string', 'in:email,sms'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'settings' => ['nullable', 'array'],
            'owner_id' => ['nullable', 'exists:users,id'],
            // Additive fields for new design (stored under settings)
            'recipient_mode' => ['nullable', 'string', 'in:manual,segment'],
            'recipient_contact_ids' => ['nullable', 'array'],
            'recipient_contact_ids.*' => ['integer', 'exists:contacts,id'],
            'segment_id' => ['nullable', 'integer', 'exists:lists,id'],
            'is_template' => ['nullable', 'boolean'],
        ];
    }
}
