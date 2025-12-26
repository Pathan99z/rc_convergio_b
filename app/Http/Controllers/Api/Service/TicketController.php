<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreTicketRequest;
use App\Http\Requests\Service\UpdateTicketRequest;
use App\Http\Requests\Service\PublicStoreTicketRequest;
use App\Http\Resources\Service\TicketResource;
use App\Http\Resources\Service\TicketStatsResource;
use App\Models\Service\Ticket;
use App\Models\Contact;
use App\Models\Company;
use App\Services\Service\TicketService;
use App\Services\Service\TicketMailerService;
use App\Http\Controllers\Api\Service\Traits\ProvidesArticleSuggestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    use ProvidesArticleSuggestions;
    
    public function __construct(
        private TicketService $ticketService,
        private TicketMailerService $ticketMailerService
    ) {}

    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ticket::class);

        $tenantId = $request->user()->tenant_id ?? $request->user()->id; // Use user's tenant_id or fallback to user's ID

        $filters = [
            'tenant_id' => $tenantId,
            'status' => $request->get('status'),
            'priority' => $request->get('priority'),
            'assignee_id' => $request->get('assignee_id'),
            'team_id' => $request->get('team_id'),
            'contact_id' => $request->get('contact_id'),
            'company_id' => $request->get('company_id'),
            'sla_status' => $request->get('sla_status'),
            'q' => $request->get('q'),
            'sortBy' => $request->get('sortBy', 'created_at'),
            'sortOrder' => $request->get('sortOrder', 'desc'),
        ];

        $userId = $request->user()->id;
        
        // Create cache key with tenant and user isolation
        $cacheKey = "service_tickets_list_{$tenantId}_{$userId}_" . md5(serialize($filters + ['per_page' => $request->get('per_page', 15)]));
        
        // Cache tickets list for 5 minutes (300 seconds)
        $tickets = Cache::remember($cacheKey, 300, function () use ($filters, $request) {
            return $this->ticketService->getTickets($filters, $request->get('per_page', 15));
        });

        return TicketResource::collection($tickets);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $tenantId = $request->user()->tenant_id ?? $request->user()->id; // Use user's tenant_id or fallback to user's ID
        $createdBy = $request->user()->id;

        $data = $request->validated();
        $ticket = $this->ticketService->createTicket($data, $tenantId, $createdBy);

        // Clear cache after creating ticket
        $this->clearServiceTicketsCache($tenantId, $createdBy);

        // Get article suggestions based on ticket content
        $suggestions = $this->getArticleSuggestions($request, $data['subject'] ?? null, $data['description'] ?? null);

        // Send notification email
        try {
            $this->ticketMailerService->sendNewTicketNotification($ticket);
        } catch (\Exception $e) {
            Log::warning('Failed to send new ticket notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Return enhanced response with suggestions
        $response = $this->enhanceTicketResponseWithSuggestions(
            new TicketResource($ticket->load(['contact', 'company', 'assignee', 'team'])),
            $suggestions
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            ...$response
        ], 201);
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): JsonResource
    {
        $this->authorize('view', $ticket);

        $tenantId = $ticket->tenant_id;
        $userId = auth()->id();
        
        // Create cache key with tenant, user, and ticket ID isolation
        $cacheKey = "service_ticket_show_{$tenantId}_{$userId}_{$ticket->id}";
        
        // Cache ticket detail for 15 minutes (900 seconds)
        $ticket = Cache::remember($cacheKey, 900, function () use ($ticket) {
            return $ticket->load([
                'contact',
                'company',
                'assignee',
                'team',
                'messages.author'
            ]);
        });

        return new TicketResource($ticket);
    }

    /**
     * Update the specified ticket.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResource
    {
        $this->authorize('update', $ticket);

        $data = $request->validated();
        $this->ticketService->updateTicket($ticket, $data);

        // Clear cache after updating ticket
        $this->clearServiceTicketsCache($ticket->tenant_id, auth()->id());
        Cache::forget("service_ticket_show_{$ticket->tenant_id}_{$request->user()->id}_{$ticket->id}");

        return new TicketResource($ticket->fresh()->load(['contact', 'company', 'assignee', 'team']));
    }

    /**
     * Remove the specified ticket from storage.
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);

        $tenantId = $ticket->tenant_id;
        $userId = auth()->id();
        $ticketId = $ticket->id;

        $ticket->delete();

        // Clear cache after deleting ticket
        $this->clearServiceTicketsCache($tenantId, $userId);
        Cache::forget("service_ticket_show_{$tenantId}_{$userId}_{$ticketId}");

        return response()->json([
            'message' => 'Ticket deleted successfully.',
        ]);
    }

    /**
     * Assign ticket to user or team.
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', $ticket);

        $request->validate([
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ]);

        $this->ticketService->assignTicket(
            $ticket,
            $request->get('assignee_id'),
            $request->get('team_id')
        );

        // Clear cache after assigning ticket
        $this->clearServiceTicketsCache($ticket->tenant_id, auth()->id());
        Cache::forget("service_ticket_show_{$ticket->tenant_id}_{$request->user()->id}_{$ticket->id}");

        return response()->json([
            'message' => 'Ticket assigned successfully.',
            'ticket' => new TicketResource($ticket->fresh()->load(['assignee', 'team'])),
        ]);
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $request->validate([
            'status' => ['required', 'string', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
        ]);

        $oldStatus = $ticket->status;
        $newStatus = $request->get('status');

        // Update the ticket status
        $ticket->update(['status' => $newStatus]);

        // Send notification email if ticket is being closed
        if ($newStatus === 'closed' && $oldStatus !== 'closed') {
            try {
                $this->ticketMailerService->sendTicketClosedNotification($ticket);
            } catch (\Exception $e) {
                Log::warning('Failed to send ticket closed notification', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Clear cache after updating ticket status
        $this->clearServiceTicketsCache($ticket->tenant_id, auth()->id());
        Cache::forget("service_ticket_show_{$ticket->tenant_id}_{$request->user()->id}_{$ticket->id}");

        return response()->json([
            'message' => 'Ticket status updated successfully.',
            'ticket' => new TicketResource($ticket->fresh()->load(['contact', 'company', 'assignee', 'team'])),
        ]);
    }

    /**
     * Close the specified ticket.
     */
    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('close', $ticket);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->ticketService->closeTicket($ticket, $request->get('reason'));

        // Send notification email
        try {
            $this->ticketMailerService->sendTicketClosedNotification($ticket);
        } catch (\Exception $e) {
            Log::warning('Failed to send ticket closed notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Clear cache after closing ticket
        $this->clearServiceTicketsCache($ticket->tenant_id, auth()->id());
        Cache::forget("service_ticket_show_{$ticket->tenant_id}_{$request->user()->id}_{$ticket->id}");

        return response()->json([
            'message' => 'Ticket closed successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    /**
     * Get ticket statistics.
     */
    public function stats(Request $request): JsonResource
    {
        $this->authorize('viewAny', Ticket::class);

        $tenantId = $request->user()->tenant_id ?? $request->user()->id; // Use user's tenant_id or fallback to user's ID
        $userId = $request->user()->id;
        
        // Create cache key for ticket stats
        $cacheKey = "service_tickets_stats_{$tenantId}_{$userId}";
        
        // Cache ticket stats for 5 minutes (300 seconds)
        $stats = Cache::remember($cacheKey, 300, function () use ($tenantId) {
            return $this->ticketService->getTicketStats($tenantId);
        });

        return new TicketStatsResource($stats);
    }

    /**
     * Get article suggestions for a specific ticket.
     */
    public function getArticleSuggestionsForTicket(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $suggestions = $this->getArticleSuggestions($request, $ticket->subject, $ticket->description);

        return response()->json([
            'success' => true,
            'ticket_id' => $ticket->id,
            'suggested_articles' => $suggestions,
            'suggestions_count' => count($suggestions),
            'message' => count($suggestions) > 0 
                ? 'Found ' . count($suggestions) . ' relevant articles.' 
                : 'No relevant articles found for this ticket.',
        ]);
    }

    /**
     * Create a public ticket (for customers without authentication).
     */
    public function publicStore(PublicStoreTicketRequest $request): JsonResponse
    {
        // Skip authorization for public ticket creation
        // $this->authorize('publicCreate');

        $data = $request->validated();
        
        // Get tenant_id from authenticated user if available, otherwise extract from request
        $tenantId = $request->user() 
            ? ($request->user()->tenant_id ?? $request->user()->id)  // Use user's tenant_id or fallback to user's ID
            : $this->getTenantIdFromRequest($request); // Extract tenant from request data or referer

        // For public forms, we don't create CRM records immediately
        // We store the customer info directly in the ticket for now
        // CRM records can be created later when the ticket is processed by an agent
        
        // Create ticket data with customer info embedded in description
        $customerInfo = "Customer: {$data['contact_name']} ({$data['contact_email']})";
        if (!empty($data['company_name'])) {
            $customerInfo .= "\nCompany: {$data['company_name']}";
        }
        
        $fullDescription = $customerInfo . "\n\nIssue Description:\n" . $data['description'];
        
        $ticketData = [
            'contact_id' => null, // No CRM contact created yet
            'company_id' => null, // No CRM company created yet
            'subject' => $data['subject'],
            'description' => $fullDescription,
            'priority' => $data['priority'],
            'status' => 'open',
        ];

        $ticket = $this->ticketService->createTicket($ticketData, $tenantId);

        // Clear cache after creating public ticket (if user is authenticated)
        if ($request->user()) {
            $this->clearServiceTicketsCache($tenantId, $request->user()->id);
        }

        // Get article suggestions based on ticket content
        $suggestions = $this->getArticleSuggestions($request, $data['subject'] ?? null, $data['description'] ?? null);

        // Send notification email
        try {
            $this->ticketMailerService->sendNewTicketNotification($ticket);
        } catch (\Exception $e) {
            Log::warning('Failed to send new public ticket notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Return enhanced response with suggestions
        $response = $this->enhanceTicketResponseWithSuggestions(
            new TicketResource($ticket->load(['contact', 'company'])),
            $suggestions
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            ...$response
        ], 201);
    }

    /**
     * Bulk operations on tickets.
     */
    public function bulk(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $request->validate([
            'action' => ['required', 'string', 'in:assign,close,delete'],
            'ticket_ids' => ['required', 'array', 'min:1', 'max:50'],
            'ticket_ids.*' => ['integer', 'exists:tickets,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $tickets = Ticket::whereIn('id', $request->get('ticket_ids'))
            ->where('tenant_id', $request->user()->tenant_id ?? $request->user()->id)
            ->get();

        $action = $request->get('action');
        $processed = 0;

        foreach ($tickets as $ticket) {
            try {
                switch ($action) {
                    case 'assign':
                        if ($this->authorize('assign', $ticket)) {
                            $this->ticketService->assignTicket(
                                $ticket,
                                $request->get('assignee_id'),
                                $request->get('team_id')
                            );
                            $processed++;
                        }
                        break;

                    case 'close':
                        if ($this->authorize('close', $ticket)) {
                            $this->ticketService->closeTicket($ticket, $request->get('reason'));
                            $processed++;
                        }
                        break;

                    case 'delete':
                        if ($this->authorize('delete', $ticket)) {
                            $ticket->delete();
                            $processed++;
                        }
                        break;
                }
            } catch (\Exception $e) {
                Log::warning('Bulk operation failed for ticket', [
                    'ticket_id' => $ticket->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => "Bulk {$action} operation completed.",
            'processed' => $processed,
            'total' => count($request->get('ticket_ids')),
        ]);
    }

    /**
     * Get current user's tenant ID for form URL generation.
     */
    public function getCurrentUserTenantId(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated.',
                'error' => 'Please login to generate form URL.',
            ], 401);
        }

        $tenantId = $user->tenant_id ?? $user->id;
        
        return response()->json([
            'data' => [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'user_name' => $user->name ?? $user->email,
                'user_email' => $user->email,
                'organization_name' => $user->organization_name ?? 'N/A',
            ],
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Generate tenant-specific public form URL.
     */
    public function generateFormUrl(Request $request, int $tenantId): JsonResponse
    {
        // Validate that the tenant exists
        $tenant = \App\Models\User::find($tenantId);
        
        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found.',
                'error' => 'Invalid tenant ID provided.',
            ], 404);
        }

        // Generate the public form URL
        $baseUrl = config('app.frontend_url', 'http://localhost:5173');
        $formUrl = "{$baseUrl}/contact?tenant={$tenantId}";

        return response()->json([
            'data' => [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant->name ?? $tenant->email,
                'tenant_email' => $tenant->email,
                'organization_name' => $tenant->organization_name ?? 'N/A',
                'form_url' => $formUrl,
                'copy_instructions' => 'Copy this URL and share it with your customers. When they submit tickets via this form, they will automatically be assigned to your account.',
            ],
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get tenant ID from request (for public endpoints).
     */
    private function getTenantIdFromRequest(Request $request): int
    {
        // Try to get tenant from request data first
        $tenantId = $request->input('tenant_id');
        
        if ($tenantId && is_numeric($tenantId) && $tenantId > 0) {
            return (int) $tenantId;
        }

        // Try to extract from referer URL (for widget integration)
        $referer = $request->header('referer');
        if ($referer && preg_match('/tenant=(\d+)/', $referer, $matches)) {
            $tenantId = (int) $matches[1];
            if ($tenantId > 0) {
                return $tenantId;
            }
        }

        // Try to extract from Origin header (for widget integration)
        $origin = $request->header('origin');
        if ($origin && preg_match('/tenant=(\d+)/', $origin, $matches)) {
            $tenantId = (int) $matches[1];
            if ($tenantId > 0) {
                return $tenantId;
            }
        }

        // For widget integration, try to extract from the widget configuration
        // This is a fallback for when the widget doesn't include tenant in URL
        // We'll use a default tenant for now, but log this for debugging
        \Log::warning('Ticket creation without explicit tenant ID', [
            'referer' => $referer,
            'origin' => $origin,
            'user_agent' => $request->header('user-agent'),
            'ip' => $request->ip(),
        ]);

        // TEMPORARY: For testing purposes, use tenant 42 as fallback
        // TODO: Remove this fallback once widget is properly configured
        \Log::warning('Using fallback tenant 42 for ticket creation', [
            'referer' => $referer,
            'origin' => $origin,
            'user_agent' => $request->header('user-agent'),
            'ip' => $request->ip(),
        ]);
        
        return 42; // Fallback to tenant 42 for testing
    }

    /**
     * Get post-ticket survey for a specific ticket.
     */
    public function getTicketSurvey(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        try {
            // Find active post-ticket survey for this tenant
            $survey = \App\Models\Service\Survey::forTenant($ticket->tenant_id)
                ->where('type', 'post_ticket')
                ->where('is_active', true)
                ->with('questions')
                ->first();

            if (!$survey) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No active post-ticket survey found for this ticket'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => new \App\Http\Resources\Service\SurveyResource($survey)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get ticket survey', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve survey information'
            ], 500);
        }
    }

    /**
     * Get survey responses for a specific ticket.
     */
    public function getTicketSurveyResponses(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        try {
            $responses = \App\Models\Service\SurveyResponse::where('ticket_id', $ticket->id)
                ->with(['survey', 'contact'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => \App\Http\Resources\Service\SurveyResponseResource::collection($responses)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get ticket survey responses', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve survey responses'
            ], 500);
        }
    }

    /**
     * Check survey status for a specific ticket.
     */
    public function getTicketSurveyStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        try {
            // Check if survey was sent
            $surveySent = \App\Models\Service\Survey::forTenant($ticket->tenant_id)
                ->where('type', 'post_ticket')
                ->where('is_active', true)
                ->exists();

            // Check if response was received
            $responseReceived = \App\Models\Service\SurveyResponse::where('ticket_id', $ticket->id)
                ->exists();

            // Determine status
            $status = 'not_sent';
            if ($surveySent && $responseReceived) {
                $status = 'completed';
            } elseif ($surveySent && !$responseReceived) {
                $status = 'pending';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'ticket_id' => $ticket->id,
                    'status' => $status,
                    'survey_sent' => $surveySent,
                    'response_received' => $responseReceived,
                    'last_updated' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get ticket survey status', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve survey status'
            ], 500);
        }
    }

    /**
     * Clear service tickets cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearServiceTicketsCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for tickets list
            $commonParams = [
                '',
                md5(serialize(['status' => 'open', 'per_page' => 15])),
                md5(serialize(['status' => 'closed', 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("service_tickets_list_{$tenantId}_{$userId}_{$params}");
            }

            Log::info('Service tickets cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear service tickets cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
