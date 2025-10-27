<?php

namespace App\Http\Requests\AssignmentDefaults;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssignmentDefaultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin') || $this->user()->can('manage_assignments');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'default_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'default_team_id' => ['nullable', 'integer'],
            'round_robin_enabled' => ['sometimes', 'boolean'],
            'enable_automatic_assignment' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'default_user_id.integer' => 'Default user ID must be a number.',
            'default_user_id.exists' => 'Selected default user does not exist.',
            'default_team_id.integer' => 'Default team ID must be a number.',
            'round_robin_enabled.boolean' => 'Round robin enabled must be true or false.',
            'enable_automatic_assignment.boolean' => 'Enable automatic assignment must be true or false.',
        ];
    }
}
