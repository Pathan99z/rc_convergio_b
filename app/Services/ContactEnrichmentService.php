<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Company;
use App\Models\IntentEvent;
use App\Models\Visitor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactEnrichmentService
{
    /**
     * Extract contact information from form data and store in intent event metadata.
     * This does NOT create contacts in core CRM - just stores visitor data for buyer intent.
     */
    public function enrichFromFormData(array $formData, IntentEvent $intentEvent, int $tenantId): array
    {
        try {
            $contactInfo = $this->extractContactInfo($formData);
            
            if (empty($contactInfo)) {
                return [
                    'enriched' => false,
                    'reason' => 'No contact information found in form data'
                ];
            }

            // Store visitor information in intent event metadata (NOT in core CRM)
            $this->updateIntentEventWithVisitorData($intentEvent, $contactInfo);

            return [
                'enriched' => true,
                'visitor_data' => $contactInfo,
                'intent_event_updated' => true,
                'message' => 'Visitor data stored in buyer intent system (not in core CRM)'
            ];

        } catch (\Exception $e) {
            Log::error('Visitor data enrichment failed', [
                'intent_event_id' => $intentEvent->id,
                'tenant_id' => $tenantId,
                'form_data' => $formData,
                'error' => $e->getMessage()
            ]);

            return [
                'enriched' => false,
                'reason' => 'Enrichment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract contact information from form data.
     */
    public function extractContactInfo(array $formData): array
    {
        $contactInfo = [];

        // Common field mappings - expanded to handle any customer field names
        $fieldMappings = [
            'email' => ['email', 'email_address', 'e-mail', 'emailaddress', 'mail', 'email_field', 'your_email'],
            'first_name' => ['first_name', 'firstname', 'fname', 'first', 'full_name', 'name', 'your_name', 'contact_name', 'fullname'],
            'last_name' => ['last_name', 'lastname', 'lname', 'last', 'surname', 'family_name'],
            'phone' => ['phone', 'phone_number', 'telephone', 'mobile', 'tel', 'contact_number', 'phone_field', 'your_phone'],
            'company' => ['company', 'company_name', 'organization', 'business', 'firm', 'corp', 'corporation', 'org', 'your_company']
        ];

        // Debug logging
        Log::info('Extracting contact info from form data', [
            'form_data' => $formData,
            'form_data_keys' => array_keys($formData)
        ]);

        foreach ($fieldMappings as $standardField => $possibleFields) {
            foreach ($possibleFields as $field) {
                if (isset($formData[$field]) && !empty($formData[$field])) {
                    $contactInfo[$standardField] = trim($formData[$field]);
                    break;
                }
            }
        }

        // Also check nested data structure
        if (isset($formData['data']) && is_array($formData['data'])) {
            foreach ($fieldMappings as $standardField => $possibleFields) {
                if (!isset($contactInfo[$standardField])) {
                    foreach ($possibleFields as $field) {
                        if (isset($formData['data'][$field]) && !empty($formData['data'][$field])) {
                            $contactInfo[$standardField] = trim($formData['data'][$field]);
                            break;
                        }
                    }
                }
            }
        }

        // Handle full_name field by splitting into first and last name
        if (isset($contactInfo['first_name']) && strpos($contactInfo['first_name'], ' ') !== false) {
            $nameParts = explode(' ', $contactInfo['first_name'], 2);
            $contactInfo['first_name'] = $nameParts[0];
            $contactInfo['last_name'] = $nameParts[1] ?? '';
        }

        Log::info('Extracted contact info', [
            'contact_info' => $contactInfo,
            'has_email' => isset($contactInfo['email'])
        ]);

        return $contactInfo;
    }

    /**
     * Find or create contact based on email.
     */
    private function findOrCreateContact(array $contactInfo, int $tenantId): Contact
    {
        if (!isset($contactInfo['email']) || empty($contactInfo['email'])) {
            throw new \Exception('Email is required to create/find contact');
        }

        $email = strtolower(trim($contactInfo['email']));

        // Try to find existing contact
        $contact = Contact::where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();

        if ($contact) {
            // Update existing contact with new information
            $updateData = [];
            
            if (isset($contactInfo['first_name']) && empty($contact->first_name)) {
                $updateData['first_name'] = $contactInfo['first_name'];
            }
            
            if (isset($contactInfo['last_name']) && empty($contact->last_name)) {
                $updateData['last_name'] = $contactInfo['last_name'];
            }
            
            if (isset($contactInfo['phone']) && empty($contact->phone)) {
                $updateData['phone'] = $contactInfo['phone'];
            }

            if (!empty($updateData)) {
                $contact->update($updateData);
            }

            return $contact;
        }

        // Create new contact
        return Contact::create([
            'first_name' => $contactInfo['first_name'] ?? null,
            'last_name' => $contactInfo['last_name'] ?? null,
            'email' => $email,
            'phone' => $contactInfo['phone'] ?? null,
            'owner_id' => $tenantId, // Use tenant_id as owner_id for form submissions
            'tenant_id' => $tenantId,
            'source' => 'form_submission',
            'lifecycle_stage' => 'lead'
        ]);
    }

    /**
     * Find or create company.
     */
    private function findOrCreateCompany(string $companyName, int $tenantId): ?Company
    {
        if (empty($companyName)) {
            return null;
        }

        $companyName = trim($companyName);

        // Try to find existing company
        $company = Company::where('tenant_id', $tenantId)
            ->where('name', $companyName)
            ->first();

        if ($company) {
            return $company;
        }

        // Create new company
        return Company::create([
            'name' => $companyName,
            'tenant_id' => $tenantId,
            'source' => 'form_submission'
        ]);
    }

    /**
     * Update intent event with visitor data (stored in metadata, NOT in core CRM).
     */
    private function updateIntentEventWithVisitorData(IntentEvent $intentEvent, array $visitorData): void
    {
        // Boost score for form submission
        $updateData = [
            'intent_score' => min($intentEvent->intent_score + 20, 100)
        ];

        // Store visitor information in metadata (NOT in core CRM)
        $metadata = is_array($intentEvent->metadata) ? $intentEvent->metadata : (json_decode($intentEvent->metadata, true) ?? []);
        
        // Build full name from available data - handle any field naming
        $fullName = '';
        if (isset($visitorData['first_name']) && !empty($visitorData['first_name'])) {
            $fullName = $visitorData['first_name'];
            if (isset($visitorData['last_name']) && !empty($visitorData['last_name'])) {
                $fullName .= ' ' . $visitorData['last_name'];
            }
        } elseif (isset($visitorData['name']) && !empty($visitorData['name'])) {
            $fullName = $visitorData['name'];
        } elseif (isset($visitorData['full_name']) && !empty($visitorData['full_name'])) {
            $fullName = $visitorData['full_name'];
        }
        
        // Store visitor data in metadata - safe access to all fields
        $metadata['visitor_data'] = [
            'name' => $fullName,
            'email' => $visitorData['email'] ?? null,
            'phone' => $visitorData['phone'] ?? null,
            'company' => $visitorData['company'] ?? null,
            'captured_at' => now()->toISOString(),
            'source' => 'form_submission',
            'raw_form_data' => $visitorData // Store original form data for reference
        ];
        
        $metadata['visitor_enriched'] = true;
        $metadata['enriched_at'] = now()->toISOString();

        $updateData['metadata'] = json_encode($metadata);

        $intentEvent->update($updateData);
    }

    /**
     * Update visitor with contact information.
     */
    private function updateVisitorWithContact(IntentEvent $intentEvent, Contact $contact): void
    {
        // Find the visitor associated with this intent event
        $visitor = $this->findVisitorFromIntentEvent($intentEvent);
        
        if ($visitor) {
            // Update visitor with contact information
            $visitor->update([
                'contact_id' => $contact->id,
                'last_seen_at' => now()
            ]);
        }
    }

    /**
     * Find visitor from intent event.
     */
    private function findVisitorFromIntentEvent(IntentEvent $intentEvent): ?Visitor
    {
        // Try to find visitor through session
        if ($intentEvent->session_id) {
            $visitorSession = \App\Models\VisitorSession::where('session_id', $intentEvent->session_id)
                ->where('tenant_id', $intentEvent->tenant_id)
                ->first();
            
            if ($visitorSession) {
                return $visitorSession->visitor;
            }
        }

        // Try to find visitor by IP and User Agent
        return Visitor::where('tenant_id', $intentEvent->tenant_id)
            ->where('ip_address', $intentEvent->ip_address)
            ->where('user_agent', $intentEvent->user_agent)
            ->first();
    }

    /**
     * Check if form data contains contact information.
     */
    public function hasContactInfo(array $formData): bool
    {
        $contactInfo = $this->extractContactInfo($formData);
        return !empty($contactInfo) && isset($contactInfo['email']);
    }
}
