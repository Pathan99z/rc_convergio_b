<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $formId = $this->route('id');
        $form = \App\Models\Form::find($formId);
        $rules = [];

        if ($form && $form->fields) {
            foreach ($form->fields as $field) {
                $fieldRules = [];
                
                if ($field['required'] ?? false) {
                    $fieldRules[] = 'required';
                } else {
                    $fieldRules[] = 'nullable';
                }

                // Add type-specific validation
                switch ($field['type']) {
                    case 'email':
                        $fieldRules[] = 'email';
                        break;
                    case 'phone':
                        $fieldRules[] = 'regex:/^\+?[1-9]\d{1,14}$/';
                        break;
                    case 'select':
                    case 'radio':
                        if (isset($field['options']) && is_array($field['options'])) {
                            $fieldRules[] = 'in:' . implode(',', $field['options']);
                        }
                        break;
                    default:
                        $fieldRules[] = 'string';
                        $fieldRules[] = 'max:1000';
                }

                $rules[$field['name']] = $fieldRules;
            }
        }

        // Add consent validation if required
        if ($form && $form->consent_required) {
            $rules['consent'] = ['required', 'accepted'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $formId = $this->route('id');
        $form = \App\Models\Form::find($formId);
        $messages = [];

        if ($form && $form->fields) {
            foreach ($form->fields as $field) {
                $fieldName = $field['name'];
                $fieldLabel = $field['label'] ?? $field['name'];

                if ($field['required'] ?? false) {
                    $messages["{$fieldName}.required"] = "The {$fieldLabel} field is required.";
                }

                switch ($field['type']) {
                    case 'email':
                        $messages["{$fieldName}.email"] = "The {$fieldLabel} must be a valid email address.";
                        break;
                    case 'phone':
                        $messages["{$fieldName}.regex"] = "The {$fieldLabel} must be a valid phone number.";
                        break;
                    case 'select':
                    case 'radio':
                        if (isset($field['options']) && is_array($field['options'])) {
                            $options = implode(', ', $field['options']);
                            $messages["{$fieldName}.in"] = "The {$fieldLabel} must be one of: {$options}.";
                        }
                        break;
                }
            }
        }

        if ($form && $form->consent_required) {
            $messages['consent.required'] = 'You must accept the terms and conditions.';
            $messages['consent.accepted'] = 'You must accept the terms and conditions.';
        }

        return $messages;
    }
}
