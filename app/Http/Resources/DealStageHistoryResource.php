<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealStageHistoryResource extends JsonResource
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
            'deal_id' => $this->deal_id,
            'from_stage' => $this->whenLoaded('fromStage', function () {
                return [
                    'id' => $this->fromStage->id,
                    'name' => $this->fromStage->name,
                    'color' => $this->fromStage->color,
                ];
            }),
            'to_stage' => $this->whenLoaded('toStage', function () {
                return [
                    'id' => $this->toStage->id,
                    'name' => $this->toStage->name,
                    'color' => $this->toStage->color,
                ];
            }),
            'reason' => $this->reason,
            'moved_by' => $this->whenLoaded('movedBy', function () {
                return [
                    'id' => $this->movedBy->id,
                    'name' => $this->movedBy->name,
                    'email' => $this->movedBy->email,
                ];
            }),
            'moved_at' => $this->created_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
