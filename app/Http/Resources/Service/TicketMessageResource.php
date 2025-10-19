<?php

namespace App\Http\Resources\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'author_id' => $this->author_id,
            'author_type' => $this->author_type,
            'body' => $this->body,
            'type' => $this->type,
            'direction' => $this->direction,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Computed attributes
            'is_from_customer' => $this->isFromCustomer(),
            'is_from_agent' => $this->isFromAgent(),
            'is_public' => $this->isPublic(),
            'is_internal' => $this->isInternal(),

            // Author information
            'author' => $this->whenLoaded('author', function () {
                return [
                    'id' => $this->author->id,
                    'name' => $this->author->name ?? 'Unknown',
                    'email' => $this->author->email ?? null,
                    'type' => class_basename($this->author_type),
                ];
            }),

            // Ticket information (minimal)
            'ticket' => $this->whenLoaded('ticket', function () {
                return [
                    'id' => $this->ticket->id,
                    'subject' => $this->ticket->subject,
                    'status' => $this->ticket->status,
                ];
            }),

            // Links
            'links' => [
                'self' => url("/api/service/tickets/{$this->ticket_id}/messages/{$this->id}"),
                'ticket' => url("/api/service/tickets/{$this->ticket_id}"),
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
