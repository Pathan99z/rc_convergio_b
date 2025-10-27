<?php

namespace App\Http\Requests\Sequences;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSequenceRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_type' => ['sometimes', 'required', 'string', 'in:contact,deal,company'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['sometimes', 'array'],
            'steps.*.action_type' => ['required_with:steps', 'string', 'in:email,task,wait'],
            'steps.*.delay_hours' => ['nullable', 'integer', 'min:0'],
            'steps.*.delay_days' => ['nullable', 'integer', 'min:0'],
            'steps.*.delay_minutes' => ['nullable', 'integer', 'min:0'],
            'steps.*.email_template_id' => ['required_if:steps.*.action_type,email', 'nullable', 'integer'],
            'steps.*.task_title' => ['required_if:steps.*.action_type,task', 'nullable', 'string', 'max:255'],
            'steps.*.task_description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The sequence name is required.',
            'name.max' => 'The sequence name may not be greater than 255 characters.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            'target_type.required' => 'The target type is required.',
            'target_type.in' => 'The target type must be one of: contact, deal, company.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Custom validation for delay fields
            if (isset($this->steps)) {
                foreach ($this->steps as $index => $step) {
                    $hasDelayHours = isset($step['delay_hours']) && $step['delay_hours'] > 0;
                    $hasDelayDays = isset($step['delay_days']) && $step['delay_days'] > 0;
                    $hasDelayMinutes = isset($step['delay_minutes']) && $step['delay_minutes'] > 0;
                    
                    // At least one delay field should have a value > 0
                    if (!$hasDelayHours && !$hasDelayDays && !$hasDelayMinutes) {
                        $validator->errors()->add(
                            "steps.{$index}.delay_hours",
                            'At least one delay field (days, hours, or minutes) must be greater than 0.'
                        );
                    }
                }
            }
        });
    }
}
