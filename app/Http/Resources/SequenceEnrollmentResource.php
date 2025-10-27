<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SequenceEnrollmentResource extends JsonResource
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
            'sequence_id' => $this->sequence_id,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'tenant_id' => $this->tenant_id,
            'created_by' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'updated_by' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email,
                ];
            }),
            'sequence' => $this->whenLoaded('sequence', function () {
                return [
                    'id' => $this->sequence->id,
                    'name' => $this->sequence->name,
                    'target_type' => $this->sequence->target_type,
                ];
            }),
            'target' => $this->whenLoaded('target', function () {
                return [
                    'id' => $this->target->id,
                    'name' => $this->target->name ?? $this->target->title ?? 'Unknown',
                ];
            }),
            'logs' => SequenceLogResource::collection($this->whenLoaded('logs')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
