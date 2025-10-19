<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreTicketMessageRequest;
use App\Http\Resources\Service\TicketMessageResource;
use App\Models\Service\Ticket;
use App\Models\Service\TicketMessage;
use App\Services\Service\TicketService;
use App\Services\Service\TicketMailerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class TicketMessageController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private TicketMailerService $ticketMailerService
    ) {}

    /**
     * Display a listing of messages for a ticket.
     */
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        $query = $ticket->messages()
            ->with('author')
            ->orderBy('created_at', 'desc');

        // Filter by message type if user doesn't have permission to view internal messages
        if (!$request->user()->can('viewInternal', $ticket)) {
            $query->where('type', 'public');
        }

        $messages = $query->paginate($request->get('per_page', 15));

        return TicketMessageResource::collection($messages);
    }

    /**
     * Store a newly created message for a ticket.
     */
    public function store(StoreTicketMessageRequest $request, Ticket $ticket): JsonResource
    {
        $this->authorize('reply', $ticket);

        $data = $request->validated();
        $data['author_id'] = $request->user()->id;

        $message = $this->ticketService->addMessage($ticket, $data);

        // Send notification email for public messages from agents
        if ($message->type === 'public' && $message->direction === 'outbound') {
            try {
                $this->ticketMailerService->sendReplyNotification($message);
            } catch (\Exception $e) {
                Log::warning('Failed to send reply notification', [
                    'ticket_id' => $ticket->id,
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new TicketMessageResource($message->load('author'));
    }

    /**
     * Display the specified message.
     */
    public function show(Ticket $ticket, TicketMessage $message): JsonResource
    {
        $this->authorize('view', $ticket);

        // Ensure message belongs to the ticket
        if ($message->ticket_id !== $ticket->id) {
            abort(404, 'Message not found for this ticket.');
        }

        // Check if user can view internal messages
        if ($message->type === 'internal' && !$this->authorize('viewInternal', $ticket)) {
            abort(403, 'You do not have permission to view internal messages.');
        }

        return new TicketMessageResource($message->load('author'));
    }

    /**
     * Update the specified message.
     */
    public function update(Request $request, Ticket $ticket, TicketMessage $message): JsonResource
    {
        $this->authorize('view', $ticket);

        // Ensure message belongs to the ticket
        if ($message->ticket_id !== $ticket->id) {
            abort(404, 'Message not found for this ticket.');
        }

        // Only allow the author to update their own messages
        if ($message->author_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            abort(403, 'You can only update your own messages.');
        }

        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message->update([
            'body' => $request->get('body'),
        ]);

        return new TicketMessageResource($message->load('author'));
    }

    /**
     * Remove the specified message.
     */
    public function destroy(Ticket $ticket, TicketMessage $message): JsonResponse
    {
        $this->authorize('view', $ticket);

        // Ensure message belongs to the ticket
        if ($message->ticket_id !== $ticket->id) {
            abort(404, 'Message not found for this ticket.');
        }

        // Only allow the author to delete their own messages, or admin
        if ($message->author_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You can only delete your own messages.');
        }

        $message->delete();

        return response()->json([
            'message' => 'Message deleted successfully.',
        ]);
    }

    /**
     * Mark message as read (for tracking purposes).
     */
    public function markAsRead(Ticket $ticket, TicketMessage $message): JsonResponse
    {
        $this->authorize('view', $ticket);

        // Ensure message belongs to the ticket
        if ($message->ticket_id !== $ticket->id) {
            abort(404, 'Message not found for this ticket.');
        }

        // Here you could implement read tracking logic
        // For now, just return success
        return response()->json([
            'message' => 'Message marked as read.',
        ]);
    }

    /**
     * Get message statistics for a ticket.
     */
    public function stats(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $stats = [
            'total_messages' => $ticket->messages()->count(),
            'public_messages' => $ticket->messages()->where('type', 'public')->count(),
            'internal_messages' => $ticket->messages()->where('type', 'internal')->count(),
            'inbound_messages' => $ticket->messages()->where('direction', 'inbound')->count(),
            'outbound_messages' => $ticket->messages()->where('direction', 'outbound')->count(),
            'latest_message_at' => $ticket->messages()->latest()->first()?->created_at?->toISOString(),
        ];

        return response()->json([
            'data' => $stats,
            'meta' => [
                'ticket_id' => $ticket->id,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }
}
