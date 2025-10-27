<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Contact;
use App\Models\Company;
use App\Models\Activity;
use App\Models\User;
use App\Models\ContactList;
use App\Services\CampaignAutomationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FormSubmissionHandler
{
    public function __construct(
        private CampaignAutomationService $automationService
    ) {}
    /**
     * Process form submission with smart CRM automation
     */
    public function processSubmission(
        Form $form, 
        array $payload, 
        ?string $ipAddress = null, 
        ?string $userAgent = null,
        array $utmData = [],
        ?string $referrer = null,
        ?FormSubmission $existingSubmission = null
    ): array {
        return DB::transaction(function () use ($form, $payload, $ipAddress, $userAgent, $utmData, $referrer, $existingSubmission) {
            // 1. Store submission row only if not provided (avoid duplicates)
            $submission = $existingSubmission ?: $this->createSubmission($form, $payload, $ipAddress, $userAgent);
            
            // 2. Parse & validate payload using form field mapping
            $contactData = $this->extractContactData($form, $payload);
            
            // 3. Company handling (respect existing submission linkage)
            if ($existingSubmission && $existingSubmission->company_id) {
                $companyResult = ['company' => $existingSubmission->company, 'status' => 'linked'];
            } else {
                $companyResult = $this->handleCompany($form, $payload);
            }
            
            // 4. Contact handling with deduplication
            $contactResult = $this->handleContact($form, $contactData, $companyResult);
            // Touch updated_at to surface in recent lists immediately
            $contactResult['contact']->touch();

            // 5. Trigger campaign automations for form submission
            $this->automationService->triggerFormSubmission(
                $contactResult['contact']->id,
                $form->id,
                $payload
            );

            // 6. Also trigger contact_created automations (for manual contact creation automations)
            $this->automationService->triggerContactCreated(
                $contactResult['contact']->id,
                $contactData
            );

            // 7. Trigger lead scoring for contact creation (if new contact)
            if ($contactResult['status'] === 'created') {
                try {
                    $leadScoringService = new \App\Services\LeadScoringService();
                    $leadScoringService->processEvent([
                        'event' => 'contact_created',
                        'contact_id' => $contactResult['contact']->id,
                        'tenant_id' => $contactResult['contact']->tenant_id,
                        'created_at' => now()->toISOString()
                    ], $contactResult['contact']->tenant_id);
                    
                    Log::info('Lead scoring triggered for form contact creation', [
                        'contact_id' => $contactResult['contact']->id,
                        'email' => $contactResult['contact']->email,
                        'tenant_id' => $contactResult['contact']->tenant_id
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to trigger lead scoring for form contact creation', [
                        'contact_id' => $contactResult['contact']->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Ensure we link the existing submission (if passed) to company/contact
            if ($submission && $companyResult['company'] && empty($submission->company_id)) {
                $submission->update(['company_id' => $companyResult['company']->id]);
            }
            if ($submission && empty($submission->contact_id)) {
                $submission->update(['contact_id' => $contactResult['contact']->id]);
            }
            
            // 5. Track marketing source
            $this->trackMarketingSource($contactResult['contact'], $form, $utmData, $referrer);
            
            // 6. Create timeline activity
            $this->createTimelineActivity($contactResult['contact'], $form);
            
            // 7. Trigger list/segment matching logic
            $this->matchListsAndSegments($contactResult['contact']);
            
            // 8. Notify assigned owner (round-robin or owner_id)
            $ownerId = $this->assignOwner($form, $contactResult['contact']);
            
            // 9. Return enhanced response
            return [
                'submission_id' => $submission->id,
                'processed' => true,
                'contact' => [
                    'id' => $contactResult['contact']->id,
                    'status' => $contactResult['status']
                ],
                'company' => [
                    'id' => $companyResult['company']?->id,
                    'status' => $companyResult['status']
                ],
                'owner_id' => $ownerId,
                'source' => $form->name,
                'messages' => [
                    'contact' => $contactResult['status'] === 'created'
                        ? 'Contact ' . ($contactResult['contact']->first_name . ' ' . $contactResult['contact']->last_name) . ' created successfully.'
                        : 'Contact ' . ($contactResult['contact']->first_name . ' ' . $contactResult['contact']->last_name) . ' updated successfully.',
                    'company' => $companyResult['company']
                        ? 'Company ' . $companyResult['company']->name . ' ' . ($companyResult['status'] === 'created' ? 'created' : 'linked') . ' successfully.'
                        : null,
                ],
            ];
        });
    }

    /**
     * Create form submission record
     */
    private function createSubmission(Form $form, array $payload, ?string $ipAddress, ?string $userAgent): FormSubmission
    {
        return FormSubmission::create([
            'form_id' => $form->id,
            'payload' => $payload,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'consent_given' => $payload['consent_given'] ?? false,
        ]);
    }

    /**
     * Extract contact data from form submission
     */
    private function extractContactData(Form $form, array $payload): array
    {
        $contactData = [];
        
        if (!$form->fields) {
            Log::error('Form fields are null', ['form_id' => $form->id]);
            throw new \Exception('Form fields are null for form ID: ' . $form->id);
        }
        
        foreach ($form->fields as $field) {
            $fieldName = $field['name'];
            $fieldType = $field['type'];
            
            if (isset($payload[$fieldName])) {
                switch ($fieldType) {
                    case 'email':
                        $contactData['email'] = $payload[$fieldName];
                        break;
                    case 'phone':
                        $contactData['phone'] = $payload[$fieldName];
                        break;
                    default:
                        // Handle name fields - DO NOT set company_name in contact data
                        if ($fieldName === 'first_name' || str_contains(strtolower($fieldName), 'first')) {
                            $contactData['first_name'] = $payload[$fieldName];
                        } elseif ($fieldName === 'last_name' || str_contains(strtolower($fieldName), 'last')) {
                            $contactData['last_name'] = $payload[$fieldName];
                        }
                        // Company name is handled separately in handleCompany method
                        break;
                }
            }
        }
        
        // Ensure required fields have default values
        if (!isset($contactData['first_name'])) {
            $contactData['first_name'] = 'Unknown';
        }
        if (!isset($contactData['last_name'])) {
            $contactData['last_name'] = 'Unknown';
        }
        
        return $contactData;
    }

    /**
     * Handle company creation/linking
     */
    private function handleCompany(Form $form, array $payload): array
    {
        $email = $payload['email'] ?? $payload['email_address'] ?? null;
        $companyName = $payload['company'] ?? $payload['company_name'] ?? null;
        
        Log::info('Form submission debug: handleCompany start', [
            'form_id' => $form->id,
            'tenant_id' => $form->tenant_id,
            'created_by' => $form->created_by,
            'email' => $email,
            'company_name' => $companyName,
        ]);

        if (!$email && !$companyName) {
            return ['company' => null, 'status' => 'skipped'];
        }
        
        // Try to find existing company by domain or name
        $company = null;
        
        if ($email) {
            $domain = $this->extractDomain($email);
            if ($domain) {
                $company = Company::where('domain', $domain)
                    ->where('tenant_id', $form->tenant_id)
                    ->first();
                    
                if (!$company && $companyName) {
                    $company = Company::where('name', 'like', "%{$companyName}%")
                        ->where('tenant_id', $form->tenant_id)
                        ->first();
                }
            }
        }
        
        if ($company) {
            return ['company' => $company, 'status' => 'linked'];
        }
        
        // Create new company if auto-creation is enabled or company name is provided
        if ($companyName || ($email && $this->shouldCreateCompanyFromDomain($form))) {
            try {
                $deriver = app(\App\Services\CompanyDeriver::class);
                $derived = $deriver->derive([
                    'email' => $email,
                    'company' => $companyName,
                ]);

                $ownerToUse = $form->created_by ?: $this->assignSalesRep($form->tenant_id);
                $company = DB::transaction(function () use ($form, $ownerToUse, $derived) {
                    $tenantId = $form->tenant_id;
                    $domain = $derived['normalized_domain'];
                    $name = $derived['normalized_name'];
                    $query = Company::query()->where('tenant_id', $tenantId);
                    if ($domain) {
                        $existing = (clone $query)->whereRaw('LOWER(domain) = ?', [strtolower($domain)])->first();
                        if ($existing) return $existing;
                    }
                    $existingByName = (clone $query)->whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
                    if ($existingByName) return $existingByName;
                    return Company::create([
                        'tenant_id' => $tenantId,
                        'name' => $name,
                        'domain' => $domain,
                        'owner_id' => $ownerToUse,
                        'status' => 'active',
                    ]);
                });
                
                Log::info('Form submission debug: company created in handler', [ 'company_id' => $company->id ]);
                return ['company' => $company, 'status' => 'created'];
            } catch (\Throwable $e) {
                Log::error('Form submission error: handleCompany failed', [
                    'form_id' => $form->id,
                    'tenant_id' => $form->tenant_id,
                    'company_name' => $companyName,
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Graceful fallback: skip company creation, proceed with contact only
                return ['company' => null, 'status' => 'skipped'];
            }
        }
        
        return ['company' => null, 'status' => 'skipped'];
    }

    /**
     * Handle contact creation/update
     */
    private function handleContact(Form $form, array $contactData, array $companyResult): array
    {
        $email = $contactData['email'] ?? null;
        // Ensure tenant_id is always set for contacts created via form submissions
        $contactData['tenant_id'] = $form->tenant_id;
        
        if ($email) {
            // Try to find existing contact by email
            $contact = Contact::where('email', $email)
                ->where('tenant_id', $form->tenant_id)
                ->first();
                
            if ($contact) {
                // Update existing contact
                $updateData = array_merge($contactData, [
                    'company_id' => $companyResult['company']?->id ?? $contact->company_id,
                    'lifecycle_stage' => $this->getFormLifecycleStage($form)
                ]);
                
                $contact->update($updateData);
                
                return ['contact' => $contact, 'status' => 'updated'];
            }
        }

        // Create new contact
        // Ensure owner is the form creator when available
        $contactData['owner_id'] = $form->created_by ?: $this->assignSalesRep($form->tenant_id);
        $contactData['company_id'] = $companyResult['company']?->id;
        $contactData['source'] = $form->name;
        $contactData['lifecycle_stage'] = $this->getFormLifecycleStage($form);
        $contactData['tags'] = $this->getFormTags($form);
        
        $contact = Contact::create($contactData);
        
        // Run assignment logic (override approach - rules take priority)
        try {
            $originalOwnerId = $contact->owner_id;
            $assignmentService = app(\App\Services\AssignmentService::class);
            $assignedUserId = $assignmentService->assignOwnerForRecord($contact, 'contact', [
                'tenant_id' => $form->tenant_id,
                'created_by' => $form->created_by,
                'source' => 'form_submission'
            ]);

            // If assignment rule found a match, apply assignment (owner_id and team_id)
            if ($assignedUserId) {
                $assignmentService->applyAssignmentToRecord($contact, $assignedUserId);
                Log::info('Form submission contact assigned via assignment rules (override)', [
                    'contact_id' => $contact->id,
                    'form_id' => $form->id,
                    'original_owner_id' => $originalOwnerId,
                    'assigned_user_id' => $assignedUserId,
                    'tenant_id' => $form->tenant_id,
                    'override_type' => $originalOwnerId ? 'manual_override' : 'auto_assignment'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to run assignment rules for form submission contact', [
                'contact_id' => $contact->id,
                'form_id' => $form->id,
                'error' => $e->getMessage(),
                'tenant_id' => $form->tenant_id
            ]);
        }
        
        return ['contact' => $contact, 'status' => 'created'];
    }

    /**
     * Track marketing source and UTM data
     */
    private function trackMarketingSource(Contact $contact, Form $form, array $utmData, ?string $referrer): void
    {
        $marketingData = array_merge($utmData, [
            'source' => $form->name,
            'form_id' => $form->id,
            'form_name' => $form->name,
            'referrer' => $referrer,
            'submitted_at' => now()->toISOString()
        ]);
        
        // Store marketing data in contact metadata or tags
        $existingTags = $contact->tags ?? [];
        $marketingTags = [
            'source:request_demo_form',
            'form:' . Str::slug($form->name),
            'utm_source:' . ($utmData['utm_source'] ?? 'direct'),
            'utm_medium:' . ($utmData['utm_medium'] ?? 'form'),
            'utm_campaign:' . ($utmData['utm_campaign'] ?? 'demo_request')
        ];
        
        $contact->update([
            'tags' => array_merge($existingTags, $marketingTags)
        ]);
    }

    /**
     * Create timeline activity
     */
    private function createTimelineActivity(Contact $contact, Form $form): void
    {
        Activity::create([
            'type' => 'form_submission',
            'subject' => 'Form submitted: ' . $form->name,
            'description' => 'A contact submitted the ' . $form->name . ' form',
            'status' => 'completed',
            'completed_at' => now(),
            'owner_id' => $contact->owner_id,
            'tenant_id' => $contact->tenant_id,
            'related_type' => Contact::class,
            'related_id' => $contact->id,
            'metadata' => [
                'form_id' => $form->id,
                'form_name' => $form->name,
                'submission_type' => 'demo_request'
            ]
        ]);
    }

    /**
     * Match contact to lists/segments
     */
    private function matchListsAndSegments(Contact $contact): void
    {
        // Get dynamic lists that might match this contact
        $dynamicLists = ContactList::where('type', 'dynamic')
            ->where('tenant_id', $contact->tenant_id)
            ->get();
            
        foreach ($dynamicLists as $list) {
            if ($this->contactMatchesListRules($contact, $list)) {
                // Add contact to list if not already present
                if (!$list->contacts()->where('contact_id', $contact->id)->exists()) {
                    $list->contacts()->attach($contact->id);
                }
            }
        }
    }

    /**
     * Check if contact matches list rules
     */
    private function contactMatchesListRules(Contact $contact, ContactList $list): bool
    {
        if (!$list->rule || !is_array($list->rule)) {
            return false;
        }
        
        foreach ($list->rule as $rule) {
            $field = $rule['field'] ?? '';
            $operator = $rule['operator'] ?? '';
            $value = $rule['value'] ?? '';
            
            $contactValue = $this->getContactFieldValue($contact, $field);
            
            if (!$this->evaluateRule($contactValue, $operator, $value)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get contact field value
     */
    private function getContactFieldValue(Contact $contact, string $field): mixed
    {
        return match($field) {
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'phone' => $contact->phone,
            'lifecycle_stage' => $contact->lifecycle_stage,
            'source' => $contact->source,
            'company.name' => $contact->company?->name,
            'company.industry' => $contact->company?->industry,
            default => null
        };
    }

    /**
     * Evaluate rule condition
     */
    private function evaluateRule(mixed $contactValue, string $operator, string $value): bool
    {
        if ($contactValue === null) {
            return false;
        }
        
        return match($operator) {
            'equals' => $contactValue == $value,
            'not_equals' => $contactValue != $value,
            'contains' => str_contains(strtolower($contactValue), strtolower($value)),
            'starts_with' => str_starts_with(strtolower($contactValue), strtolower($value)),
            'ends_with' => str_ends_with(strtolower($contactValue), strtolower($value)),
            'greater_than' => is_numeric($contactValue) && is_numeric($value) && $contactValue > $value,
            'less_than' => is_numeric($contactValue) && is_numeric($value) && $contactValue < $value,
            default => false
        };
    }

    /**
     * Assign owner using round-robin
     */
    private function assignOwner(Form $form, Contact $contact): int
    {
        // Check if form has specific owner assignment
        if (isset($form->settings['owner_id'])) {
            return $form->settings['owner_id'];
        }
        
        // Use existing contact owner
        return $contact->owner_id;
    }

    /**
     * Extract domain from email
     */
    private function extractDomain(string $email): ?string
    {
        $parts = explode('@', $email);
        return count($parts) === 2 ? $parts[1] : null;
    }

    /**
     * Check if company should be created from domain
     */
    private function shouldCreateCompanyFromDomain(Form $form): bool
    {
        return $form->settings['create_company_from_domain'] ?? true;
    }

    /**
     * Get form lifecycle stage setting
     */
    private function getFormLifecycleStage(Form $form): string
    {
        return $form->settings['lifecycle_stage'] ?? 'lead';
    }

    /**
     * Get form tags setting
     */
    private function getFormTags(Form $form): array
    {
        return $form->settings['tags'] ?? ['demo_request'];
    }

    /**
     * Assign sales rep using round-robin
     */
    private function assignSalesRep(int $tenantId): int
    {
        // Get users for this tenant (excluding the tenant user)
        $users = User::where('id', '!=', $tenantId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'sales_rep');
            })
            ->pluck('id');

        if ($users->isEmpty()) {
            // Fallback to any user except tenant
            $users = User::where('id', '!=', $tenantId)->pluck('id');
        }

        if ($users->isEmpty()) {
            // Last resort - use tenant ID
            return $tenantId;
        }

        // Simple round-robin: get the user with the least contacts
        $userWithLeastContacts = User::select('users.id')
            ->leftJoin('contacts', 'users.id', '=', 'contacts.owner_id')
            ->whereIn('users.id', $users)
            ->groupBy('users.id')
            ->orderByRaw('COUNT(contacts.id) ASC')
            ->first();

        return $userWithLeastContacts ? $userWithLeastContacts->id : $users->first();
    }
}
