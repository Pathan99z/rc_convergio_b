<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:10000'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'status' => ['sometimes', 'string', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'contact_id.exists' => 'The selected contact does not exist.',
            'company_id.exists' => 'The selected company does not exist.',
            'assignee_id.exists' => 'The selected assignee does not exist.',
            'team_id.exists' => 'The selected team does not exist.',
            'subject.max' => 'The subject may not be greater than 255 characters.',
            'description.max' => 'The description may not be greater than 10000 characters.',
            'priority.in' => 'The priority must be one of: low, medium, high, urgent.',
            'status.in' => 'The status must be one of: open, in_progress, resolved, closed.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'contact_id' => 'contact',
            'company_id' => 'company',
            'assignee_id' => 'assignee',
            'team_id' => 'team',
        ];
    }
}
