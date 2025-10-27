<?php

namespace App\Http\Requests\Quotes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteRequest extends FormRequest
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
            'currency' => ['sometimes', 'string', 'size:3'],
            'valid_until' => ['sometimes', 'date', 'after:today'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.min' => 'At least one item is required.',
            'items.*.name.required' => 'Item name is required.',
            'items.*.quantity.required' => 'Item quantity is required.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',
            'items.*.unit_price.required' => 'Item unit price is required.',
            'items.*.unit_price.min' => 'Item unit price must be greater than or equal to 0.',
            'items.*.discount.min' => 'Item discount must be greater than or equal to 0.',
            'items.*.tax_rate.min' => 'Item tax rate must be greater than or equal to 0.',
            'items.*.tax_rate.max' => 'Item tax rate must be less than or equal to 100.',
            'valid_until.after' => 'Valid until date must be in the future.',
        ];
    }
}
