<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignRecipientResource extends JsonResource
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
            'campaign_id' => $this->campaign_id,
            'contact_id' => $this->contact_id,
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'sent_at' => $this->sent_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'opened_at' => $this->opened_at?->toISOString(),
            'clicked_at' => $this->clicked_at?->toISOString(),
            'bounced_at' => $this->bounced_at?->toISOString(),
            'bounce_reason' => $this->bounce_reason,
            'message_id' => $this->message_id,
            'metadata' => $this->metadata,
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'contact' => new ContactResource($this->whenLoaded('contact')),
        ];
    }
}
