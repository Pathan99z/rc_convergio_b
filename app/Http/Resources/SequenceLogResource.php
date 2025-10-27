<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SequenceLogResource extends JsonResource
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
            'enrollment_id' => $this->enrollment_id,
            'step_id' => $this->step_id,
            'action_performed' => $this->action_performed,
            'performed_at' => $this->performed_at?->toISOString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'tenant_id' => $this->tenant_id,
            'created_by' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'enrollment' => $this->whenLoaded('enrollment', function () {
                return [
                    'id' => $this->enrollment->id,
                    'sequence_id' => $this->enrollment->sequence_id,
                    'target_type' => $this->enrollment->target_type,
                    'target_id' => $this->enrollment->target_id,
                ];
            }),
            'step' => $this->whenLoaded('step', function () {
                return [
                    'id' => $this->step->id,
                    'step_order' => $this->step->step_order,
                    'action_type' => $this->step->action_type,
                    'delay_hours' => $this->step->delay_hours,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
