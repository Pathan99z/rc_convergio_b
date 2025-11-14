<?php

namespace App\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveDealRequest extends FormRequest
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
            'stage_id' => [
                'required',
                Rule::exists('stages', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:1000'
            ],
        ];
    }
}
