<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
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
            'type' => $this->type,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'location' => $this->location,
            'settings' => $this->settings ?? [],
            'zoom_meeting_id' => $this->settings['zoom_meeting_id'] ?? null,
            'zoom_password' => $this->settings['zoom_password'] ?? null,
            'zoom_start_url' => $this->settings['zoom_start_url'] ?? null,
            'is_active' => $this->is_active,
            'archived_at' => $this->archived_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'tenant_id' => $this->tenant_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            
            'attendees' => $this->whenLoaded('attendees', function () {
                return EventAttendeeResource::collection($this->attendees);
            }),
            
            'rsvp_stats' => $this->when($this->rsvp_stats, function () {
                return $this->rsvp_stats;
            }, function () {
                return $this->getRsvpStats();
            }),
            
            // Computed fields
            'is_upcoming' => $this->isUpcoming(),
            'is_past' => $this->isPast(),
            'attendee_count' => $this->whenLoaded('attendees', function () {
                return $this->attendees->count();
            }),
        ];
    }
}
