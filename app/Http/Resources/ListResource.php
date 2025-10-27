<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'rule' => $this->rule ?? [],
            'created_by' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'tenant_id' => $this->tenant_id,
            'contacts_count' => $this->contacts_count ?? 0,
            'contacts' => $this->when($this->type === 'dynamic', function () {
                // For dynamic segments, include the matching contacts
                return $this->getContacts()
                    ->with(['company:id,name,size', 'owner:id,name'])
                    ->limit(50) // Limit to first 50 contacts for performance
                    ->get()
                    ->map(function ($contact) {
                        return [
                            'id' => $contact->id,
                            'first_name' => $contact->first_name,
                            'last_name' => $contact->last_name,
                            'email' => $contact->email,
                            'phone' => $contact->phone,
                            'company' => $contact->company ? [
                                'id' => $contact->company->id,
                                'name' => $contact->company->name,
                                'size' => $contact->company->size,
                            ] : null,
                            'owner' => $contact->owner ? [
                                'id' => $contact->owner->id,
                                'name' => $contact->owner->name,
                            ] : null,
                            'created_at' => $contact->created_at,
                        ];
                    });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
