<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        return $this->user()->can('reply', $ticket);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'string', Rule::in(['public', 'internal'])],
            'direction' => ['nullable', 'string', Rule::in(['inbound', 'outbound'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'body.required' => 'The message body is required.',
            'body.max' => 'The message body may not be greater than 5000 characters.',
            'type.in' => 'The message type must be either public or internal.',
            'direction.in' => 'The message direction must be either inbound or outbound.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'body' => 'message body',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default type if not provided
        if (!$this->has('type')) {
            $this->merge(['type' => 'public']);
        }

        // Set default direction if not provided
        if (!$this->has('direction')) {
            $this->merge(['direction' => 'outbound']);
        }
    }
}
