<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'domain' => $this->domain,
            'website' => $this->website,
            'industry' => $this->industry,
            'size' => $this->size,
            'type' => $this->type,
            'address' => $this->address,
            'annual_revenue' => $this->annual_revenue,
            'timezone' => $this->timezone,
            'description' => $this->description,
            'linkedin_page' => $this->linkedin_page,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', function () {
                return [
                    'id' => $this->owner->id,
                    'name' => $this->owner->name,
                    'email' => $this->owner->email,
                ];
            }),
            'contacts_count' => $this->when(isset($this->contacts_count), $this->contacts_count),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
