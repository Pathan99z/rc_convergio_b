<?php

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (int) $this->header('X-Tenant-ID');
        $contactId = (int) $this->route('id');

        return [
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('contacts', 'email')
                    ->ignore($contactId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->whereNull('deleted_at'),
            ],
            'phone' => [
                'nullable',
                'string',
                'regex:/^\+?[1-9]\d{1,14}$/',
                Rule::unique('contacts', 'phone')
                    ->ignore($contactId)
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->whereNull('deleted_at'),
            ],
            'owner_id' => ['required', 'exists:users,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
            'lifecycle_stage' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
        ];
    }
}


