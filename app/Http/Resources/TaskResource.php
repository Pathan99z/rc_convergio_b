<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => $this->due_date?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'tags' => $this->tags,
            'owner_id' => $this->owner_id,
            'assigned_to' => $this->assigned_to,
            'tenant_id' => $this->tenant_id,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'owner' => new UserResource($this->whenLoaded('owner')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'related' => $this->whenLoaded('related'),
        ];
    }
}
