<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SequenceStepResource extends JsonResource
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
            'step_order' => $this->step_order,
            'action_type' => $this->action_type,
            'delay_hours' => $this->delay_hours,
            'email_template_id' => $this->email_template_id,
            'task_title' => $this->task_title,
            'task_description' => $this->task_description,
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
            'email_template' => $this->whenLoaded('emailTemplate', function () {
                return [
                    'id' => $this->emailTemplate->id,
                    'name' => $this->emailTemplate->name,
                    'subject' => $this->emailTemplate->subject,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
