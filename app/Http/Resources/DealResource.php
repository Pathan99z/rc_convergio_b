<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
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
            'value' => $this->value,
            'currency' => $this->currency,
            'status' => $this->status,
            'expected_close_date' => $this->expected_close_date?->toISOString(),
            'closed_date' => $this->closed_date?->toISOString(),
            'close_reason' => $this->close_reason,
            'probability' => $this->probability,
            'tags' => $this->tags,
            'pipeline_id' => $this->pipeline_id,
            'stage_id' => $this->stage_id,
            'owner_id' => $this->owner_id,
            'contact_id' => $this->contact_id,
            'company_id' => $this->company_id,
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'pipeline' => new PipelineResource($this->whenLoaded('pipeline')),
            'stage' => new StageResource($this->whenLoaded('stage')),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }
}
