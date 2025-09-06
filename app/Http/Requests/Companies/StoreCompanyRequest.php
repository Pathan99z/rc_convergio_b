<?php

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('companies')->where('tenant_id', optional($this->user())->tenant_id ?? $this->user()->id)
            ],
            'website' => 'nullable|url|max:255',
            'industry' => 'nullable|string|max:100',
            'size' => 'nullable|integer|min:1',
            'type' => 'nullable|string|max:50',
            'address' => 'nullable|array',
            'address.street' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:100',
            'address.state' => 'nullable|string|max:100',
            'address.postal_code' => 'nullable|string|max:20',
            'address.country' => 'nullable|string|max:100',
            'annual_revenue' => 'nullable|numeric|min:0',
            'timezone' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'linkedin_page' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'owner_id' => 'nullable|exists:users,id',
            'contact_id' => [
                'nullable',
                'integer',
                'exists:contacts,id'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'domain.unique' => 'This domain is already taken for your organization.',
            'website.url' => 'Please provide a valid website URL.',
            'linkedin_page.url' => 'Please provide a valid LinkedIn page URL.',
            'owner_id.exists' => 'The selected owner does not exist.',
        ];
    }
}
