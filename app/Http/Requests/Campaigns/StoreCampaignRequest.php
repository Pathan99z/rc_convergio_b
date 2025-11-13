<?php

namespace App\Http\Requests\Campaigns;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:email,sms'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'settings' => ['nullable', 'array'],
            'owner_id' => ['nullable', 'exists:users,id'],
            // Additive fields for new design (stored under settings)
            'recipient_mode' => ['nullable', 'string', 'in:manual,segment,csv'],
            'recipient_contact_ids' => ['nullable', 'array'],
            'recipient_contact_ids.*' => ['integer', 'exists:contacts,id'],
            'segment_id' => ['nullable', 'integer', 'exists:lists,id'],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'], // 10MB max for CSV files
            'is_template' => ['nullable', 'boolean'],
        ];
    }
}
