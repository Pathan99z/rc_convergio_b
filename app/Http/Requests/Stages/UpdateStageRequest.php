<?php

namespace App\Http\Requests\Stages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = (int) (optional($this->user())->tenant_id ?? $this->user()->id);
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $this->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'pipeline_id' => [
                'sometimes',
                'required',
                Rule::exists('pipelines', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
        ];
    }
}
