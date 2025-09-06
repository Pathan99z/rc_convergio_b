<?php

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (int) (optional($this->user())->tenant_id ?? $this->user()->id);

        return [
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('contacts', 'email')->where(fn ($q) => $q->where('tenant_id', $tenantId))->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
            'lifecycle_stage' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
        ];
    }
}


