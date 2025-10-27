<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class CommerceSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', \App\Models\Commerce\CommerceSetting::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'stripe_public_key' => 'nullable|string|max:255',
            'stripe_secret_key' => 'nullable|string|max:255',
            'mode' => 'string|in:test,live',
            'webhook_secret' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'stripe_public_key.max' => 'Stripe public key must not exceed 255 characters.',
            'stripe_secret_key.max' => 'Stripe secret key must not exceed 255 characters.',
            'mode.in' => 'Mode must be either test or live.',
            'webhook_secret.max' => 'Webhook secret must not exceed 255 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->stripe_public_key && !str_starts_with($this->stripe_public_key, 'pk_')) {
                $validator->errors()->add('stripe_public_key', 'Invalid Stripe public key format. Must start with "pk_".');
            }

            if ($this->stripe_secret_key && !str_starts_with($this->stripe_secret_key, 'sk_')) {
                $validator->errors()->add('stripe_secret_key', 'Invalid Stripe secret key format. Must start with "sk_".');
            }

            if ($this->webhook_secret && !str_starts_with($this->webhook_secret, 'whsec_')) {
                $validator->errors()->add('webhook_secret', 'Invalid webhook secret format. Must start with "whsec_".');
            }
        });
    }
}
