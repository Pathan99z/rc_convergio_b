<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Quote;

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
            
            // NEW: Customer selection is required
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            
            // NEW: Related entity type (deal, quote, other)
            'related_entity_type' => ['nullable', 'string', 'in:deal,quote,other'],
            
            // NEW: Related entity ID (required if type is deal or quote)
            'related_entity_id' => [
                'nullable',
                'required_if:related_entity_type,deal',
                'required_if:related_entity_type,quote',
                function ($attribute, $value, $fail) {
                    $relatedEntityType = $this->input('related_entity_type');
                    $contactId = $this->input('contact_id');
                    
                    // If related_entity_type is deal or quote, related_entity_id is required
                    if (in_array($relatedEntityType, ['deal', 'quote']) && empty($value)) {
                        $fail('The related entity ID is required when related entity type is ' . $relatedEntityType . '.');
                        return;
                    }
                    
                    // If related_entity_type is other or null, related_entity_id must be null
                    if (in_array($relatedEntityType, ['other', null]) && !empty($value)) {
                        $fail('The related entity ID must be empty when related entity type is ' . ($relatedEntityType ?? 'not specified') . '.');
                        return;
                    }
                    
                    // Validate deal belongs to contact (tenant check will be done in controller)
                    if ($relatedEntityType === 'deal' && !empty($value)) {
                        $deal = Deal::where('id', $value)
                            ->where('contact_id', $contactId)
                            ->first();
                        if (!$deal) {
                            $fail('The selected deal does not belong to the selected contact.');
                        }
                    }
                    
                    // Validate quote belongs to contact via deal (tenant check will be done in controller)
                    if ($relatedEntityType === 'quote' && !empty($value)) {
                        $quote = Quote::where('id', $value)
                            ->whereHas('deal', function ($q) use ($contactId) {
                                $q->where('contact_id', $contactId);
                            })
                            ->first();
                        if (!$quote) {
                            $fail('The selected quote does not belong to the selected contact.');
                        }
                    }
                },
            ],
            
            // Keep existing related_type for backward compatibility (will be mapped in controller)
            'related_type' => ['nullable', 'string', 'in:App\Models\Contact,App\Models\Company,App\Models\Deal,App\Models\Quote'],
            'related_id' => [
                'nullable',
                'required_with:related_type',
                'integer',
            ],
        ];
    }
}
