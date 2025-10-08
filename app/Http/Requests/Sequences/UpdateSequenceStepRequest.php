<?php

namespace App\Http\Requests\Sequences;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSequenceStepRequest extends FormRequest
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
            'step_order' => ['sometimes', 'required', 'integer', 'min:1'],
            'action_type' => ['sometimes', 'required', 'string', 'in:email,task,wait'],
            'delay_hours' => ['sometimes', 'required', 'integer', 'min:0'],
            'email_template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'task_title' => ['nullable', 'string', 'max:255'],
            'task_description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'step_order.required' => 'The step order is required.',
            'step_order.integer' => 'The step order must be an integer.',
            'step_order.min' => 'The step order must be at least 1.',
            'action_type.required' => 'The action type is required.',
            'action_type.in' => 'The action type must be one of: email, task, wait.',
            'delay_hours.required' => 'The delay hours is required.',
            'delay_hours.integer' => 'The delay hours must be an integer.',
            'delay_hours.min' => 'The delay hours must be at least 0.',
            'email_template_id.exists' => 'The selected email template does not exist.',
            'task_title.max' => 'The task title may not be greater than 255 characters.',
            'task_description.max' => 'The task description may not be greater than 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $actionType = $this->input('action_type');
            
            if ($actionType === 'email' && !$this->input('email_template_id')) {
                $validator->errors()->add('email_template_id', 'Email template is required for email action type.');
            }
            
            if ($actionType === 'task' && !$this->input('task_title')) {
                $validator->errors()->add('task_title', 'Task title is required for task action type.');
            }
        });
    }
}
