<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'active', 'completed', 'expired', 'cancelled'])],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'metadata' => ['sometimes', 'array'],
            'metadata.*' => ['string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'description.string' => 'The description must be a string.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            'status.in' => 'The status must be one of: draft, active, completed, expired, cancelled.',
            'expires_at.date' => 'The expiration date must be a valid date.',
            'expires_at.after' => 'The expiration date must be in the future.',
            'metadata.array' => 'The metadata must be an array.',
        ];
    }
}
