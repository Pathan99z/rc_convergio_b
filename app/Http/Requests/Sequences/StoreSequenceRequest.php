<?php

namespace App\Http\Requests\Sequences;

use Illuminate\Foundation\Http\FormRequest;

class StoreSequenceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_type' => ['required', 'string', 'in:contact,deal,company'],
            'is_active' => ['boolean'],
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
}
