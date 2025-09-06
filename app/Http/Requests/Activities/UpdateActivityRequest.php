<?php

namespace App\Http\Requests\Activities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (int) (optional($this->user())->tenant_id ?? $this->user()->id);

        return [
            'type' => ['sometimes', 'required', 'string', 'in:call,email,meeting,note,task'],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'metadata.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'metadata.notes' => ['nullable', 'string'],
            'metadata.tags' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:scheduled,completed,cancelled'],
            'owner_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'related_type' => ['nullable', 'string', 'in:App\Models\Contact,App\Models\Company,App\Models\Deal'],
            'related_id' => [
                'nullable',
                'integer',
                Rule::when($this->related_type === 'App\Models\Contact', [
                    Rule::exists('contacts', 'id')->where('tenant_id', $tenantId)
                ]),
                Rule::when($this->related_type === 'App\Models\Company', [
                    Rule::exists('companies', 'id')->where('tenant_id', $tenantId)
                ]),
                Rule::when($this->related_type === 'App\Models\Deal', [
                    Rule::exists('deals', 'id')->where('tenant_id', $tenantId)
                ]),
            ],
        ];
    }
}
