<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed,cancelled'],
            'due_date' => ['nullable', 'date'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'related_type' => ['nullable', 'string', 'in:App\Models\Contact,App\Models\Company,App\Models\Deal'],
            'related_id' => [
                'nullable',
                'required_with:related_type',
                'integer',
            ],
        ];
    }
}
