<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Commerce\CommercePaymentLink::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'quote_id' => 'nullable|exists:quotes,id',
            'order_id' => 'nullable|exists:commerce_orders,id',
            'expires_at' => 'nullable|date|after:now',
            'create_stripe_session' => 'boolean',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'quote_id.exists' => 'The selected quote does not exist.',
            'order_id.exists' => 'The selected order does not exist.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->quote_id && !$this->order_id) {
                $validator->errors()->add('quote_id', 'Either quote_id or order_id is required.');
            }
        });
    }
}
