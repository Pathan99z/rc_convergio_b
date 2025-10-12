<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentRuleResource extends JsonResource
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
            'priority' => $this->priority,
            'criteria' => $this->criteria,
            'action' => $this->action,
            'active' => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'audits_count' => $this->when(isset($this->audits_count), $this->audits_count),
            'recent_audits' => $this->whenLoaded('audits', function () {
                return $this->audits->map(function ($audit) {
                    return [
                        'id' => $audit->id,
                        'record_type' => $audit->record_type,
                        'record_id' => $audit->record_id,
                        'assigned_to' => $audit->assigned_to,
                        'assignment_type' => $audit->assignment_type,
                        'created_at' => $audit->created_at,
                    ];
                });
            }),
        ];
    }
}
