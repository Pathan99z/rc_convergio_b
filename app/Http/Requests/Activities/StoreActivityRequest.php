<?php

namespace App\Http\Requests\Activities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:call,email,meeting,note,task'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'metadata.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'metadata.notes' => ['nullable', 'string'],
            'metadata.tags' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:scheduled,completed,cancelled'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'related_type' => ['nullable', 'string', 'in:App\Models\Contact,App\Models\Company,App\Models\Deal'],
            'related_id' => [
                'nullable',
                'integer',
            ],
        ];
    }
}
