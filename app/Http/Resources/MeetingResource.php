<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
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
            'contact_id' => $this->contact_id,
            'user_id' => $this->user_id,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'end_time' => $this->end_time?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'location' => $this->location,
            'status' => $this->status,
            'integration_provider' => $this->integration_provider,
            'integration_data' => $this->integration_data,
            'attendees' => $this->attendees,
            'notes' => $this->notes,
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Computed fields
            'meeting_link' => $this->getMeetingLink(),
            'meeting_id' => $this->getMeetingId(),
            'duration_formatted' => $this->getDurationFormatted(),
            'summary' => $this->getSummary(),
            'is_upcoming' => $this->isUpcoming(),
            'is_in_progress' => $this->isInProgress(),
            'is_completed' => $this->isCompleted(),

            // Relationships
            'contact' => $this->whenLoaded('contact', function () {
                return [
                    'id' => $this->contact->id,
                    'first_name' => $this->contact->first_name,
                    'last_name' => $this->contact->last_name,
                    'email' => $this->contact->email,
                    'full_name' => $this->contact->first_name . ' ' . $this->contact->last_name,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
        ];
    }
}
