<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Company;
use App\Models\IntentEvent;
use App\Models\Visitor;
use App\Models\VisitorSession;
use App\Models\VisitorLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IdentityResolutionService
{
    /**
     * Resolve contact and company for an event based on metadata.
     */
    public function resolveContactAndCompany(array $eventData, int $tenantId): array
    {
        try {
            $email = $this->extractEmail($eventData);
            
            if (!$email) {
                return [
                    'contact_id' => null,
                    'company_id' => null,
                    'resolved' => false,
                    'method' => 'no_email'
                ];
            }

            // Find or create contact
            $contact = $this->findOrCreateContact($email, $eventData, $tenantId);
            
            // Find or create company based on email domain
            $company = $this->findOrCreateCompanyByDomain($email, $tenantId);
            
            // Link visitor to contact if we have session data
            if (isset($eventData['session_id']) && $contact) {
                $this->linkVisitorToContact($eventData['session_id'], $contact->id, $tenantId);
            }

            return [
                'contact_id' => $contact?->id,
                'company_id' => $company?->id,
                'resolved' => true,
                'method' => 'email_domain',
                'email' => $email,
                'contact' => $contact,
                'company' => $company,
            ];

        } catch (\Exception $e) {
            Log::error('Identity resolution failed', [
                'tenant_id' => $tenantId,
                'event_data' => $eventData,
                'error' => $e->getMessage()
            ]);

            return [
                'contact_id' => null,
                'company_id' => null,
                'resolved' => false,
                'method' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract email from event metadata.
     */
    private function extractEmail(array $eventData): ?string
    {
        // Check various possible email fields
        $emailFields = ['email', 'user_email', 'contact_email', 'visitor_email'];
        
        foreach ($emailFields as $field) {
            if (isset($eventData['metadata'][$field]) && filter_var($eventData['metadata'][$field], FILTER_VALIDATE_EMAIL)) {
                return strtolower(trim($eventData['metadata'][$field]));
            }
        }

        // Check direct fields in event_data
        foreach ($emailFields as $field) {
            if (isset($eventData[$field]) && filter_var($eventData[$field], FILTER_VALIDATE_EMAIL)) {
                return strtolower(trim($eventData[$field]));
            }
        }

        return null;
    }

    /**
     * Find or create contact based on email.
     */
    private function findOrCreateContact(string $email, array $eventData, int $tenantId): ?Contact
    {
        // First, try to find existing contact
        $contact = Contact::where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();

        if ($contact) {
            return $contact;
        }

        // Create new contact if not found
        $contactData = [
            'email' => $email,
            'tenant_id' => $tenantId,
            'source' => 'buyer_intent_tracking',
        ];

        // Try to extract name from metadata
        $nameData = $this->extractNameFromMetadata($eventData);
        if ($nameData) {
            $contactData = array_merge($contactData, $nameData);
        }

        try {
            $contact = Contact::create($contactData);
            Log::info('Created new contact from buyer intent tracking', [
                'contact_id' => $contact->id,
                'email' => $email,
                'tenant_id' => $tenantId
            ]);
            
            return $contact;
        } catch (\Exception $e) {
            Log::error('Failed to create contact', [
                'email' => $email,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract name from event metadata.
     */
    private function extractNameFromMetadata(array $eventData): ?array
    {
        $nameData = [];

        // Check for full name
        if (isset($eventData['metadata']['name']) || isset($eventData['metadata']['full_name'])) {
            $fullName = $eventData['metadata']['name'] ?? $eventData['metadata']['full_name'];
            $nameParts = $this->splitName($fullName);
            $nameData = array_merge($nameData, $nameParts);
        }

        // Check for separate first/last name fields
        if (isset($eventData['metadata']['first_name'])) {
            $nameData['first_name'] = trim($eventData['metadata']['first_name']);
        }
        if (isset($eventData['metadata']['last_name'])) {
            $nameData['last_name'] = trim($eventData['metadata']['last_name']);
        }

        return !empty($nameData) ? $nameData : null;
    }

    /**
     * Split full name into first and last name.
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        $parts = explode(' ', $fullName);
        
        if (count($parts) === 1) {
            return ['first_name' => $parts[0]];
        }
        
        if (count($parts) === 2) {
            return [
                'first_name' => $parts[0],
                'last_name' => $parts[1]
            ];
        }
        
        // For more than 2 parts, first part is first name, rest is last name
        return [
            'first_name' => $parts[0],
            'last_name' => implode(' ', array_slice($parts, 1))
        ];
    }

    /**
     * Find or create company based on email domain.
     */
    private function findOrCreateCompanyByDomain(string $email, int $tenantId): ?Company
    {
        $domain = $this->extractDomainFromEmail($email);
        
        if (!$domain) {
            return null;
        }

        // First, try to find existing company by domain
        $company = Company::where('tenant_id', $tenantId)
            ->where(function($query) use ($domain) {
                $query->where('domain', $domain)
                      ->orWhere('website', 'like', "%{$domain}%");
            })
            ->first();

        if ($company) {
            return $company;
        }

        // Create new company if not found
        try {
            $company = Company::create([
                'name' => $this->generateCompanyNameFromDomain($domain),
                'domain' => $domain,
                'website' => "https://{$domain}",
                'tenant_id' => $tenantId,
                'source' => 'buyer_intent_tracking',
            ]);

            Log::info('Created new company from email domain', [
                'company_id' => $company->id,
                'domain' => $domain,
                'tenant_id' => $tenantId
            ]);

            return $company;
        } catch (\Exception $e) {
            Log::error('Failed to create company', [
                'domain' => $domain,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract domain from email address.
     */
    private function extractDomainFromEmail(string $email): ?string
    {
        $parts = explode('@', $email);
        return count($parts) === 2 ? strtolower(trim($parts[1])) : null;
    }

    /**
     * Generate company name from domain.
     */
    private function generateCompanyNameFromDomain(string $domain): string
    {
        // Remove common TLDs and subdomains
        $name = str_replace(['www.', '.com', '.org', '.net', '.co', '.io'], '', $domain);
        
        // Capitalize and clean up
        $name = ucwords(str_replace(['.', '-', '_'], ' ', $name));
        
        return $name . ' Inc.';
    }

    /**
     * Link visitor to contact.
     */
    private function linkVisitorToContact(string $sessionId, int $contactId, int $tenantId): void
    {
        try {
            // Find or create visitor
            $visitor = $this->findOrCreateVisitor($sessionId, $tenantId);
            
            if (!$visitor) {
                return;
            }

            // Check if link already exists
            $existingLink = VisitorLink::where('visitor_id', $visitor->id)
                ->where('contact_id', $contactId)
                ->first();

            if ($existingLink) {
                return; // Link already exists
            }

            // Create visitor-contact link
            VisitorLink::create([
                'visitor_id' => $visitor->id,
                'contact_id' => $contactId,
                'linked_at' => now(),
            ]);

            // Update visitor's last contact
            $visitor->update([
                'last_contact_id' => $contactId,
                'last_seen_at' => now(),
            ]);

            // Perform retroactive linking for past 30 days
            $this->performRetroactiveLinking($visitor->id, $contactId, $tenantId);

            Log::info('Linked visitor to contact', [
                'visitor_id' => $visitor->id,
                'contact_id' => $contactId,
                'tenant_id' => $tenantId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to link visitor to contact', [
                'session_id' => $sessionId,
                'contact_id' => $contactId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Find or create visitor.
     */
    private function findOrCreateVisitor(string $sessionId, int $tenantId): ?Visitor
    {
        // First, try to find visitor by session
        $visitorSession = VisitorSession::where('session_id', $sessionId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($visitorSession) {
            return $visitorSession->visitor;
        }

        // Create new visitor and session
        try {
            DB::beginTransaction();

            $visitor = Visitor::create([
                'visitor_id' => Str::uuid(),
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'tenant_id' => $tenantId,
            ]);

            VisitorSession::create([
                'session_id' => $sessionId,
                'visitor_id' => $visitor->id,
                'started_at' => now(),
                'tenant_id' => $tenantId,
            ]);

            DB::commit();

            return $visitor;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create visitor', [
                'session_id' => $sessionId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Perform retroactive linking for past 30 days.
     */
    private function performRetroactiveLinking(int $visitorId, int $contactId, int $tenantId): void
    {
        try {
            // Get all sessions for this visitor
            $sessions = VisitorSession::where('visitor_id', $visitorId)
                ->where('tenant_id', $tenantId)
                ->pluck('session_id');

            if ($sessions->isEmpty()) {
                return;
            }

            // Find the company for this contact
            $contact = Contact::find($contactId);
            $companyId = null;
            if ($contact && $contact->company_id) {
                $companyId = $contact->company_id;
            }

            // Update all intent events from past 30 days for these sessions
            $updatedCount = IntentEvent::where('tenant_id', $tenantId)
                ->whereIn('session_id', $sessions)
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNull('contact_id') // Only update events without contact_id
                ->update([
                    'contact_id' => $contactId,
                    'company_id' => $companyId,
                ]);

            if ($updatedCount > 0) {
                Log::info('Performed retroactive linking', [
                    'visitor_id' => $visitorId,
                    'contact_id' => $contactId,
                    'company_id' => $companyId,
                    'updated_events' => $updatedCount,
                    'tenant_id' => $tenantId
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to perform retroactive linking', [
                'visitor_id' => $visitorId,
                'contact_id' => $contactId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get visitor statistics for a tenant.
     */
    public function getVisitorStats(int $tenantId): array
    {
        try {
            $totalVisitors = Visitor::where('tenant_id', $tenantId)->count();
            $linkedVisitors = Visitor::where('tenant_id', $tenantId)
                ->whereNotNull('last_contact_id')
                ->count();
            $totalSessions = VisitorSession::where('tenant_id', $tenantId)->count();
            $activeSessions = VisitorSession::where('tenant_id', $tenantId)
                ->whereNull('ended_at')
                ->count();

            return [
                'total_visitors' => $totalVisitors,
                'linked_visitors' => $linkedVisitors,
                'unlinked_visitors' => $totalVisitors - $linkedVisitors,
                'total_sessions' => $totalSessions,
                'active_sessions' => $activeSessions,
                'link_rate' => $totalVisitors > 0 ? round(($linkedVisitors / $totalVisitors) * 100, 2) : 0,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visitor stats', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
