<?php

namespace App\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (int) (optional($this->user())->tenant_id ?? $this->user()->id);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:open,won,lost,closed'],
            'expected_close_date' => ['nullable', 'date', 'after_or_equal:today'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
            'pipeline_id' => [
                'required',
                Rule::exists('pipelines', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'stage_id' => [
                'required',
                Rule::exists('stages', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'owner_id' => ['nullable', 'exists:users,id'],
            'contact_id' => [
                'nullable',
                Rule::exists('contacts', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'company_id' => [
                'nullable',
                Rule::exists('companies', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
        ];
    }
}
