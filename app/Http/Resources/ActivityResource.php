<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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
            'type' => $this->type,
            'title' => $this->subject, // Map subject back to title for frontend
            'description' => $this->description,
            'metadata' => $this->metadata,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'status' => $this->status,
            'owner_id' => $this->owner_id,
            'tenant_id' => $this->tenant_id,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Extract metadata fields for frontend compatibility
            'duration' => $this->metadata['duration_minutes'] ?? null,
            'notes' => $this->metadata['notes'] ?? null,
            'tags' => $this->metadata['tags'] ?? null,
            
            // Relationships
            'owner' => new UserResource($this->whenLoaded('owner')),
            'related' => $this->whenLoaded('related'),
        ];
    }
}
