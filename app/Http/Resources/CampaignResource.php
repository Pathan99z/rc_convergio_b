<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
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
            'subject' => $this->subject,
            'content' => $this->content,
            'status' => $this->status,
            'type' => $this->type,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'total_recipients' => $this->total_recipients,
            'sent_count' => $this->sent_count,
            'delivered_count' => $this->delivered_count,
            'opened_count' => $this->opened_count,
            'clicked_count' => $this->clicked_count,
            'bounced_count' => $this->bounced_count,
            'settings' => $this->settings,
            'tenant_id' => $this->tenant_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'recipients' => CampaignRecipientResource::collection($this->whenLoaded('recipients')),
        ];
    }
}
