<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventAttendeeResource extends JsonResource
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
            'event_id' => $this->event_id,
            'contact_id' => $this->contact_id,
            'rsvp_status' => $this->rsvp_status,
            'attended' => $this->attended,
            'rsvp_at' => $this->rsvp_at?->toISOString(),
            'attended_at' => $this->attended_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'contact' => $this->whenLoaded('contact', function () {
                return [
                    'id' => $this->contact->id,
                    'name' => $this->contact->name,
                    'email' => $this->contact->email,
                    'first_name' => $this->contact->first_name,
                    'last_name' => $this->contact->last_name,
                ];
            }),
            
            'event' => $this->whenLoaded('event', function () {
                return [
                    'id' => $this->event->id,
                    'name' => $this->event->name,
                    'type' => $this->event->type,
                    'scheduled_at' => $this->event->scheduled_at?->toISOString(),
                ];
            }),
        ];
    }
}












