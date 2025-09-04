<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Contact;
use App\Models\Company;
use App\Models\Activity;
use App\Models\User;
use App\Models\ContactList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FormSubmissionHandler
{
    /**
     * Process form submission with smart CRM automation
     */
    public function processSubmission(
        Form $form, 
        array $payload, 
        ?string $ipAddress = null, 
        ?string $userAgent = null,
        array $utmData = [],
        ?string $referrer = null
    ): array {
        return DB::transaction(function () use ($form, $payload, $ipAddress, $userAgent, $utmData, $referrer) {
            // 1. Always store submission row for audit/logs
            $submission = $this->createSubmission($form, $payload, $ipAddress, $userAgent);
            
            // 2. Parse & validate payload using form field mapping
            $contactData = $this->extractContactData($form, $payload);
            
            // 3. Company handling
            $companyResult = $this->handleCompany($form, $payload);
            
            // 4. Contact handling with deduplication
            $contactResult = $this->handleContact($form, $contactData, $companyResult);
            
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
                'source' => 'Request Demo Form'
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
        $email = $payload['email'] ?? null;
        $companyName = $payload['company'] ?? null;
        
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
            $company = Company::create([
                'tenant_id' => $form->tenant_id,
                'name' => $companyName ?: ucfirst($this->extractDomain($email)),
                'domain' => $email ? $this->extractDomain($email) : null,
                'owner_id' => $this->assignSalesRep($form->tenant_id),
                'status' => 'active',
                'source' => 'Request Demo Form'
            ]);
            
            return ['company' => $company, 'status' => 'created'];
        }
        
        return ['company' => null, 'status' => 'skipped'];
    }

    /**
     * Handle contact creation/update
     */
    private function handleContact(Form $form, array $contactData, array $companyResult): array
    {
        $email = $contactData['email'] ?? null;
        
        if ($email) {
            // Try to find existing contact by email
            $contact = Contact::where('email', $email)
                ->where('tenant_id', $form->tenant_id)
                ->first();
                
            if ($contact) {
                // Update existing contact
                $updateData = array_merge($contactData, [
                    'company_id' => $companyResult['company']?->id ?? $contact->company_id,
                    'source' => 'Request Demo Form',
                    'lifecycle_stage' => $this->getFormLifecycleStage($form)
                ]);
                
                $contact->update($updateData);
                
                return ['contact' => $contact, 'status' => 'updated'];
            }
        }

        // Create new contact
        $contactData['tenant_id'] = $form->tenant_id;
        $contactData['owner_id'] = $this->assignSalesRep($form->tenant_id);
        $contactData['company_id'] = $companyResult['company']?->id;
        $contactData['source'] = 'Request Demo Form';
        $contactData['lifecycle_stage'] = $this->getFormLifecycleStage($form);
        $contactData['tags'] = $this->getFormTags($form);
        
        $contact = Contact::create($contactData);
        
        return ['contact' => $contact, 'status' => 'created'];
    }

    /**
     * Track marketing source and UTM data
     */
    private function trackMarketingSource(Contact $contact, Form $form, array $utmData, ?string $referrer): void
    {
        $marketingData = array_merge($utmData, [
            'source' => 'Request Demo Form',
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
            'subject' => 'Submitted ' . $form->name,
            'description' => 'Contact submitted the ' . $form->name . ' form',
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
