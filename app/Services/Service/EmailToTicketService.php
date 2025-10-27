<?php

namespace App\Services\Service;

use App\Models\Service\EmailIntegration;
use App\Models\Service\Ticket;
use App\Models\Service\TicketMessage;
use App\Models\Contact;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmailToTicketService
{
    public function __construct(
        private TicketService $ticketService,
        private TicketMailerService $ticketMailerService
    ) {
        // Ensure services are properly injected
    }

    /**
     * Process incoming email and create ticket.
     */
    public function processIncomingEmail(array $emailData, EmailIntegration $integration): ?Ticket
    {
        try {
            // Validate email data
            $validatedData = $this->validateEmailData($emailData);
            
            // Extract customer information
            $customerInfo = $this->extractCustomerInfo($validatedData);
            
            // Check for existing ticket (to avoid duplicates)
            $existingTicket = $this->findExistingTicket($validatedData, $integration);
            if ($existingTicket) {
                return $this->addMessageToExistingTicket($existingTicket, $validatedData);
            }
            
            // Create new ticket
            $ticket = $this->createTicketFromEmail($validatedData, $customerInfo, $integration);
            
            // Update integration statistics
            $integration->incrementTicketsCreated();
            $integration->updateLastSync();
            
            Log::info('Ticket created from email', [
                'ticket_id' => $ticket->id,
                'integration_id' => $integration->id,
                'from_email' => $validatedData['from_email'],
                'subject' => $validatedData['subject'],
            ]);
            
            return $ticket;
            
        } catch (\Exception $e) {
            Log::error('Failed to process incoming email', [
                'integration_id' => $integration->id,
                'email_data' => $emailData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return null;
        }
    }

    /**
     * Validate incoming email data.
     */
    private function validateEmailData(array $emailData): array
    {
        $validator = Validator::make($emailData, [
            'from_email' => 'required|email',
            'to_email' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'message_id' => 'nullable|string',
            'date' => 'nullable|date',
            'attachments' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid email data: ' . $validator->errors()->first());
        }

        return $validator->validated();
    }

    /**
     * Extract customer information from email.
     */
    private function extractCustomerInfo(array $emailData): array
    {
        $fromEmail = $emailData['from_email'];
        $body = $emailData['body'];
        
        // Try to extract name from email body or use email prefix
        $customerName = $this->extractCustomerName($body, $fromEmail);
        
        // Try to extract company from email domain or body
        $companyName = $this->extractCompanyName($fromEmail, $body);
        
        return [
            'email' => $fromEmail,
            'name' => $customerName,
            'company' => $companyName,
        ];
    }

    /**
     * Extract customer name from email body or email address.
     */
    private function extractCustomerName(string $body, string $email): string
    {
        // Look for common name patterns in email body
        $namePatterns = [
            '/Name:\s*([^\n\r]+)/i',
            '/From:\s*([^\n\r]+)/i',
            '/Contact:\s*([^\n\r]+)/i',
            '/My name is\s+([^\n\r]+)/i',
            '/I am\s+([^\n\r]+)/i',
        ];
        
        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $name = trim($matches[1]);
                if (strlen($name) > 2 && strlen($name) < 100) {
                    return $name;
                }
            }
        }
        
        // Fallback to email prefix
        $emailPrefix = explode('@', $email)[0];
        return ucwords(str_replace(['.', '_', '-'], ' ', $emailPrefix));
    }

    /**
     * Extract company name from email domain or body.
     */
    private function extractCompanyName(string $email, string $body): ?string
    {
        // Look for company patterns in email body
        $companyPatterns = [
            '/Company:\s*([^\n\r]+)/i',
            '/Organization:\s*([^\n\r]+)/i',
            '/I work at\s+([^\n\r]+)/i',
            '/From\s+([^\n\r]+)/i',
        ];
        
        foreach ($companyPatterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $company = trim($matches[1]);
                if (strlen($company) > 2 && strlen($company) < 100) {
                    return $company;
                }
            }
        }
        
        // Fallback to email domain (remove common email providers)
        $domain = explode('@', $email)[1] ?? '';
        $commonProviders = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com'];
        
        if (!in_array($domain, $commonProviders)) {
            return ucfirst(explode('.', $domain)[0]);
        }
        
        return null;
    }

    /**
     * Find existing ticket to avoid duplicates.
     */
    private function findExistingTicket(array $emailData, EmailIntegration $integration): ?Ticket
    {
        // Look for tickets with same subject and from email in last 24 hours
        return Ticket::forTenant($integration->tenant_id)
            ->where('subject', $emailData['subject'])
            ->where('description', 'like', '%' . $emailData['from_email'] . '%')
            ->where('created_at', '>=', now()->subDay())
            ->first();
    }

    /**
     * Add message to existing ticket.
     */
    private function addMessageToExistingTicket(Ticket $ticket, array $emailData): Ticket
    {
        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => null, // System message
            'author_type' => 'system',
            'body' => "New email received:\n\n" . $emailData['body'],
            'type' => 'public',
            'direction' => 'inbound',
        ]);
        
        // Update ticket status if it was closed
        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'open']);
        }
        
        Log::info('Message added to existing ticket', [
            'ticket_id' => $ticket->id,
            'message_id' => $message->id,
        ]);
        
        return $ticket;
    }

    /**
     * Create new ticket from email.
     */
    private function createTicketFromEmail(array $emailData, array $customerInfo, EmailIntegration $integration): Ticket
    {
        return DB::transaction(function () use ($emailData, $customerInfo, $integration) {
            // Create or find contact
            $contact = $this->createOrFindContact($customerInfo, $integration->tenant_id);
            
            // Create or find company
            $company = $this->createOrFindCompany($customerInfo, $integration->tenant_id);
            
            // Determine priority
            $priority = $this->determinePriority($emailData['subject'], $emailData['body']);
            
            // Prepare ticket data
            $ticketData = [
                'contact_id' => $contact?->id,
                'company_id' => $company?->id,
                'assignee_id' => $integration->default_assignee_id,
                'team_id' => $integration->default_team_id,
                'subject' => $emailData['subject'],
                'description' => $this->formatTicketDescription($emailData, $customerInfo),
                'priority' => $priority,
                'status' => 'open',
                'email_integration_id' => $integration->id,
            ];
            
            // Create ticket using existing service
            $ticket = $this->ticketService->createTicket($ticketData, $integration->tenant_id);
            
            // Send notification email
            try {
                $this->ticketMailerService->sendNewTicketNotification($ticket);
            } catch (\Exception $e) {
                Log::warning('Failed to send new ticket notification', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            return $ticket;
        });
    }

    /**
     * Create or find contact.
     */
    private function createOrFindContact(array $customerInfo, int $tenantId): ?Contact
    {
        if (empty($customerInfo['email'])) {
            return null;
        }
        
        $contact = Contact::forTenant($tenantId)
            ->where('email', $customerInfo['email'])
            ->first();
        
        if (!$contact) {
            $contact = Contact::create([
                'tenant_id' => $tenantId,
                'first_name' => $customerInfo['name'],
                'last_name' => 'Customer', // Required field
                'email' => $customerInfo['email'],
                'owner_id' => $tenantId, // Required field
                'source' => 'email_integration',
            ]);
        }
        
        return $contact;
    }

    /**
     * Create or find company.
     */
    private function createOrFindCompany(array $customerInfo, int $tenantId): ?Company
    {
        if (empty($customerInfo['company'])) {
            return null;
        }
        
        $company = Company::forTenant($tenantId)
            ->where('name', $customerInfo['company'])
            ->first();
        
        if (!$company) {
            $company = Company::create([
                'tenant_id' => $tenantId,
                'name' => $customerInfo['company'],
                'source' => 'email_integration',
            ]);
        }
        
        return $company;
    }

    /**
     * Determine ticket priority based on subject and content.
     */
    private function determinePriority(string $subject, string $body): string
    {
        $urgentKeywords = ['urgent', 'critical', 'emergency', 'asap', 'immediately', 'broken', 'down'];
        $highKeywords = ['important', 'priority', 'issue', 'problem', 'error', 'bug'];
        
        $text = strtolower($subject . ' ' . $body);
        
        foreach ($urgentKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 'urgent';
            }
        }
        
        foreach ($highKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 'high';
            }
        }
        
        return 'medium';
    }

    /**
     * Format ticket description with email information.
     */
    private function formatTicketDescription(array $emailData, array $customerInfo): string
    {
        $description = "Email received from: {$customerInfo['email']}";
        
        if ($customerInfo['name']) {
            $description .= " ({$customerInfo['name']})";
        }
        
        if ($customerInfo['company']) {
            $description .= "\nCompany: {$customerInfo['company']}";
        }
        
        $description .= "\n\nEmail Subject: {$emailData['subject']}";
        $description .= "\n\nEmail Content:\n{$emailData['body']}";
        
        if (!empty($emailData['attachments'])) {
            $description .= "\n\nAttachments: " . count($emailData['attachments']) . " file(s)";
        }
        
        return $description;
    }

    /**
     * Test email integration by sending a test email.
     */
    public function testIntegration(EmailIntegration $integration): array
    {
        try {
            // This would typically send a test email to the integration
            // For now, we'll just validate the configuration
            
            if (!$integration->isConnected()) {
                return [
                    'success' => false,
                    'message' => 'Integration is not properly connected',
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Integration test successful',
                'last_sync' => $integration->last_sync_at?->toISOString(),
                'tickets_created' => $integration->tickets_created_count,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Integration test failed: ' . $e->getMessage(),
            ];
        }
    }
}
