<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,draft,inactive'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.name' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', 'in:text,email,phone,textarea,select,checkbox,radio'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['nullable', 'array', 'required_if:fields.*.type,select,radio'],
            'fields.*.options.*' => ['string', 'max:255'],
            'consent_required' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.required' => 'At least one field is required.',
            'fields.min' => 'At least one field is required.',
            'fields.*.name.required' => 'Field name is required.',
            'fields.*.type.required' => 'Field type is required.',
            'fields.*.type.in' => 'Invalid field type. Allowed types: text, email, phone, textarea, select, checkbox, radio.',
            'fields.*.label.required' => 'Field label is required.',
            'fields.*.options.required_if' => 'Options are required for select and radio fields.',
        ];
    }
}
