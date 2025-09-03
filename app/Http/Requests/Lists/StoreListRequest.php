<?php

namespace App\Http\Requests\Lists;

use Illuminate\Foundation\Http\FormRequest;

class StoreListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * Normalize 'rules' field to 'rule' for backward compatibility.
     */
    protected function prepareForValidation()
    {
        if ($this->has('rules') && !$this->has('rule')) {
            $this->merge([
                'rule' => $this->input('rules')
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', 'in:static,dynamic'],
            'rule' => ['nullable', 'array', 'required_if:type,dynamic', 'min:1'],
            'rule.*.field' => ['required_with:rule', 'string'],
            'rule.*.operator' => ['required_with:rule', 'string', 'in:equals,not_equals,contains,starts_with,ends_with,greater_than,less_than'],
            'rule.*.value' => ['required_with:rule', 'string', 'max:1000'],
            'contacts' => ['nullable', 'array', 'required_if:type,static'],
            'contacts.*' => ['integer', 'exists:contacts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'List name is required.',
            'type.required' => 'List type is required.',
            'type.in' => 'List type must be either static or dynamic.',
            'rule.required_if' => 'Rules are required for dynamic lists.',
            'rule.min' => 'At least one rule is required for dynamic lists.',
            'rule.*.field.required_with' => 'Field is required for each rule.',
            'rule.*.operator.required_with' => 'Operator is required for each rule.',
            'rule.*.operator.in' => 'Invalid operator. Allowed operators: equals, not_equals, contains, starts_with, ends_with, greater_than, less_than.',
            'rule.*.value.required_with' => 'Value is required for each rule.',
            'rule.*.value.max' => 'Rule value cannot exceed 1000 characters.',
            'contacts.required_if' => 'Contacts are required for static lists.',
            'contacts.array' => 'Contacts must be an array.',
            'contacts.*.integer' => 'Each contact ID must be an integer.',
            'contacts.*.exists' => 'One or more selected contacts do not exist.',
        ];
    }
}
