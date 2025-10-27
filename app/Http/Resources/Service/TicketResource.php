<?php

namespace App\Http\Resources\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'contact_id' => $this->contact_id,
            'company_id' => $this->company_id,
            'assignee_id' => $this->assignee_id,
            'team_id' => $this->team_id,
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'sla_due_at' => $this->sla_due_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Computed attributes
            'sla_status' => $this->sla_status,
            'is_on_track' => $this->isOnTrack(),
            'has_breached_sla' => $this->hasBreachedSla(),
            'messages_count' => $this->when(isset($this->messages_count), $this->messages_count),

            // Relationships
            'contact' => $this->whenLoaded('contact', function () {
                return [
                    'id' => $this->contact->id,
                    'name' => $this->contact->name,
                    'email' => $this->contact->email,
                    'phone' => $this->contact->phone,
                ];
            }),

            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                    'domain' => $this->company->domain,
                ];
            }),

            'assignee' => $this->whenLoaded('assignee', function () {
                return [
                    'id' => $this->assignee->id,
                    'name' => $this->assignee->name,
                    'email' => $this->assignee->email,
                ];
            }),

            'team' => $this->whenLoaded('team', function () {
                return [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ];
            }),

            'messages' => $this->whenLoaded('messages', function () {
                return TicketMessageResource::collection($this->messages);
            }),

            'latest_message' => $this->whenLoaded('messages', function () {
                $latestMessage = $this->messages->first();
                return $latestMessage ? new TicketMessageResource($latestMessage) : null;
            }),

            // Links
            'links' => [
                'self' => url("/api/service/tickets/{$this->id}"),
                'messages' => url("/api/service/tickets/{$this->id}/messages"),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}
