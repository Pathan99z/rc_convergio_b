<?php

namespace App\Services\Service;

use App\Models\Service\Ticket;
use App\Models\Service\TicketMessage;
use App\Models\Service\Survey;
use App\Models\User;
use App\Models\Contact;
use App\Models\Company;
use App\Models\Team;
use App\Services\AssignmentService;
use App\Services\TeamAccessService;
use App\Services\Service\TicketMailerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketService
{
    public function __construct(
        private AssignmentService $assignmentService,
        private TeamAccessService $teamAccessService,
        private TicketMailerService $ticketMailerService
    ) {}

    /**
     * Get paginated tickets with filters
     */
    public function getTickets(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->forTenant($filters['tenant_id'] ?? null)
            ->with(['contact', 'company', 'assignee', 'team', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->withCount('messages');

        // Apply filters
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        if (!empty($filters['assignee_id'])) {
            $query->byAssignee($filters['assignee_id']);
        }

        if (!empty($filters['team_id'])) {
            $query->byTeam($filters['team_id']);
        }

        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['sla_status'])) {
            $query->bySlaStatus($filters['sla_status']);
        }

        // Handle search query
        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('subject', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        return $query->paginate($perPage);
    }

    /**
     * Create a new ticket
     */
    public function createTicket(array $data, int $tenantId, ?int $createdBy = null): Ticket
    {
        return DB::transaction(function () use ($data, $tenantId, $createdBy) {
            // Set SLA due date based on priority
            $slaDueAt = $this->calculateSlaDueDate($data['priority'] ?? 'medium');

            // Create the ticket
            $ticket = Ticket::create([
                'tenant_id' => $tenantId,
                'contact_id' => $data['contact_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? 'medium',
                'status' => $data['status'] ?? 'open',
                'sla_due_at' => $slaDueAt,
            ]);

            // Auto-assign if no assignee specified
            if (!$ticket->assignee_id) {
                $this->autoAssignTicket($ticket);
            }

            // Create initial message if provided
            if (!empty($data['initial_message'])) {
                $this->addMessage($ticket, [
                    'body' => $data['initial_message'],
                    'author_id' => $createdBy,
                    'type' => 'public',
                    'direction' => 'outbound',
                ]);
            }

            Log::info('Ticket created', [
                'ticket_id' => $ticket->id,
                'tenant_id' => $tenantId,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
            ]);

            return $ticket;
        });
    }

    /**
     * Update a ticket
     */
    public function updateTicket(Ticket $ticket, array $data): bool
    {
        return DB::transaction(function () use ($ticket, $data) {
            $oldStatus = $ticket->status;
            $oldAssignee = $ticket->assignee_id;

            // Update ticket
            $updated = $ticket->update($data);

            // Handle status changes
            if (isset($data['status']) && $data['status'] !== $oldStatus) {
                $this->handleStatusChange($ticket, $oldStatus, $data['status']);
            }

            // Handle assignee changes
            if (isset($data['assignee_id']) && $data['assignee_id'] !== $oldAssignee) {
                $this->handleAssigneeChange($ticket, $oldAssignee, $data['assignee_id']);
            }

            Log::info('Ticket updated', [
                'ticket_id' => $ticket->id,
                'changes' => $data,
            ]);

            return $updated;
        });
    }

    /**
     * Assign ticket to user or team
     */
    public function assignTicket(Ticket $ticket, ?int $assigneeId = null, ?int $teamId = null): bool
    {
        return DB::transaction(function () use ($ticket, $assigneeId, $teamId) {
            $oldAssignee = $ticket->assignee_id;
            $oldTeam = $ticket->team_id;

            $ticket->update([
                'assignee_id' => $assigneeId,
                'team_id' => $teamId,
            ]);

            // Log assignment change
            if ($oldAssignee !== $assigneeId || $oldTeam !== $teamId) {
                Log::info('Ticket assigned', [
                    'ticket_id' => $ticket->id,
                    'old_assignee' => $oldAssignee,
                    'new_assignee' => $assigneeId,
                    'old_team' => $oldTeam,
                    'new_team' => $teamId,
                ]);
            }

            return true;
        });
    }

    /**
     * Close a ticket
     */
    public function closeTicket(Ticket $ticket, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($ticket, $reason) {
            $ticket->markAsClosed();

            // Add closure message if reason provided
            if ($reason) {
                $this->addMessage($ticket, [
                    'body' => "Ticket closed. Reason: {$reason}",
                    'author_id' => auth()->id() ?? $ticket->tenant_id, // Fallback to tenant if no auth
                    'type' => 'internal',
                    'direction' => 'outbound',
                ]);
            }

            // Send post-ticket survey if available
            $this->sendPostTicketSurvey($ticket);

            Log::info('Ticket closed', [
                'ticket_id' => $ticket->id,
                'reason' => $reason,
            ]);

            return true;
        });
    }

    /**
     * Add a message to a ticket
     */
    public function addMessage(Ticket $ticket, array $data): TicketMessage
    {
        return TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $data['author_id'],
            'author_type' => $data['author_type'] ?? 'App\Models\User',
            'body' => $data['body'],
            'type' => $data['type'] ?? 'public',
            'direction' => $data['direction'] ?? 'outbound',
        ]);
    }

    /**
     * Get ticket statistics
     */
    public function getTicketStats(int $tenantId): array
    {
        $query = Ticket::forTenant($tenantId);

        // Apply team filtering if enabled
        $this->teamAccessService->applyTeamFilter($query);

        return [
            'total' => $query->count(),
            'open' => (clone $query)->byStatus('open')->count(),
            'in_progress' => (clone $query)->byStatus('in_progress')->count(),
            'resolved' => (clone $query)->byStatus('resolved')->count(),
            'closed' => (clone $query)->byStatus('closed')->count(),
            'sla_breached' => (clone $query)->bySlaStatus('breached')->count(),
            'sla_on_track' => (clone $query)->bySlaStatus('on_track')->count(),
        ];
    }

    /**
     * Calculate SLA due date based on priority
     */
    private function calculateSlaDueDate(string $priority): \Carbon\Carbon
    {
        return match ($priority) {
            'urgent' => now()->addHours(2), // 2 hours for urgent
            'high' => now()->addHours(4),   // 4 hours for high
            'medium' => now()->addHours(8), // 8 hours for medium
            'low' => now()->addHours(24),   // 24 hours for low
            default => now()->addHours(8),
        };
    }

    /**
     * Auto-assign ticket based on assignment rules
     */
    private function autoAssignTicket(Ticket $ticket): void
    {
        try {
            $assignedUserId = $this->assignmentService->assignOwnerForRecord(
                $ticket,
                'ticket',
                ['tenant_id' => $ticket->tenant_id]
            );

            if ($assignedUserId) {
                $ticket->update(['assignee_id' => $assignedUserId]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to auto-assign ticket', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle status change
     */
    private function handleStatusChange(Ticket $ticket, string $oldStatus, string $newStatus): void
    {
        if ($newStatus === 'resolved' && $oldStatus !== 'resolved') {
            $ticket->markAsResolved();
        }

        // Add status change message
        $this->addMessage($ticket, [
            'body' => "Status changed from {$oldStatus} to {$newStatus}",
            'author_id' => auth()->id() ?? $ticket->tenant_id, // Fallback to tenant if no auth
            'type' => 'internal',
            'direction' => 'outbound',
        ]);
    }

    /**
     * Handle assignee change
     */
    private function handleAssigneeChange(Ticket $ticket, ?int $oldAssignee, ?int $newAssignee): void
    {
        $oldAssigneeName = $oldAssignee ? User::find($oldAssignee)?->name : 'Unassigned';
        $newAssigneeName = $newAssignee ? User::find($newAssignee)?->name : 'Unassigned';

        // Add assignment change message
        $this->addMessage($ticket, [
            'body' => "Assigned from {$oldAssigneeName} to {$newAssigneeName}",
            'author_id' => auth()->id() ?? $ticket->tenant_id, // Fallback to tenant if no auth
            'type' => 'internal',
            'direction' => 'outbound',
        ]);
    }

    /**
     * Send post-ticket survey if available
     */
    private function sendPostTicketSurvey(Ticket $ticket): void
    {
        try {
            // Find active post-ticket survey for this tenant
            $survey = Survey::forTenant($ticket->tenant_id)
                ->where('type', 'post_ticket')
                ->where('is_active', true)
                ->first();

            if (!$survey) {
                Log::info('No active post-ticket survey found for tenant', [
                    'tenant_id' => $ticket->tenant_id,
                    'ticket_id' => $ticket->id,
                ]);
                return;
            }

            // Get customer email
            $contactEmail = null;
            if ($ticket->contact && $ticket->contact->email) {
                $contactEmail = $ticket->contact->email;
            } else {
                // Extract email from ticket description for public tickets
                $contactEmail = $this->extractEmailFromTicketDescription($ticket);
            }

            if (!$contactEmail) {
                Log::warning('Cannot send post-ticket survey - no contact email found', [
                    'ticket_id' => $ticket->id,
                    'survey_id' => $survey->id,
                ]);
                return;
            }

            // Send the survey email
            $this->ticketMailerService->sendPostTicketSurvey($ticket, $survey, $contactEmail);

            Log::info('Post-ticket survey sent successfully', [
                'ticket_id' => $ticket->id,
                'survey_id' => $survey->id,
                'contact_email' => $contactEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send post-ticket survey', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Extract email address from ticket description for public tickets
     */
    private function extractEmailFromTicketDescription(Ticket $ticket): ?string
    {
        $description = $ticket->description;
        
        // Look for email pattern in the description
        // Common patterns: "Customer: Name (email@domain.com)" or "email@domain.com"
        $emailPatterns = [
            '/Customer:\s*[^(]*\(([^)]+@[^)]+)\)/',  // Customer: Name (email@domain.com)
            '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',  // Standard email pattern
        ];
        
        foreach ($emailPatterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                $email = trim($matches[1]);
                // Validate the extracted email
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }
        
        return null;
    }
}
