<?php

namespace App\Http\Requests\Sequences;

use Illuminate\Foundation\Http\FormRequest;

class EnrollSequenceRequest extends FormRequest
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
            'target_type' => ['required', 'string', 'in:contact,deal,company'],
            'target_id' => ['required', 'integer', 'min:1'],
            'start_now' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'target_type.required' => 'The target type is required.',
            'target_type.in' => 'The target type must be one of: contact, deal, company.',
            'target_id.required' => 'The target ID is required.',
            'target_id.integer' => 'The target ID must be an integer.',
            'target_id.min' => 'The target ID must be at least 1.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $targetType = $this->input('target_type');
            $targetId = $this->input('target_id');
            
            if ($targetType && $targetId) {
                // Check if the target exists and belongs to the tenant
                $modelClass = match($targetType) {
                    'contact' => \App\Models\Contact::class,
                    'deal' => \App\Models\Deal::class,
                    'company' => \App\Models\Company::class,
                    default => null,
                };
                
                if ($modelClass) {
                    $user = $this->user();
                    $tenantId = $user?->tenant_id ?? $user?->id;
                    
                    $exists = $modelClass::where('id', $targetId)
                        ->where('tenant_id', $tenantId)
                        ->exists();
                    
                    if (!$exists) {
                        $validator->errors()->add('target_id', "The selected {$targetType} does not exist or does not belong to your tenant.");
                    }
                }
            }
        });
    }
}
