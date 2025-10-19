<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Commerce\CommerceOrder::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'deal_id' => 'nullable|exists:deals,id',
            'quote_id' => 'nullable|exists:quotes,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'customer_snapshot' => 'nullable|array',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'string|max:3|in:USD,EUR,GBP,CAD,AUD',
            'status' => 'string|in:pending,paid,failed,refunded,cancelled',
            'payment_method' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required for the order.',
            'items.*.name.required' => 'Item name is required.',
            'items.*.quantity.required' => 'Item quantity is required.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',
            'items.*.unit_price.required' => 'Item unit price is required.',
            'items.*.unit_price.min' => 'Item unit price must be greater than 0.',
            'total_amount.required' => 'Total amount is required.',
            'total_amount.min' => 'Total amount must be greater than 0.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'items.*.name' => 'item name',
            'items.*.quantity' => 'item quantity',
            'items.*.unit_price' => 'item unit price',
            'items.*.discount' => 'item discount',
            'items.*.tax_rate' => 'item tax rate',
        ];
    }
}
