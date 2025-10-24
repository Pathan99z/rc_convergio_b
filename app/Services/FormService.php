<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Contact;
use App\Models\Company;
use App\Models\User;
use App\Services\TeamAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FormService
{
    public function __construct(
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Get paginated forms with filters
     */
    public function getForms(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Form::query()
            ->forTenant($filters['tenant_id'] ?? null)
            ->with(['creator:id,name,email'])
            ->withCount('submissions');

        // Apply filters
        if (!empty($filters['name'])) {
            $query->searchByName($filters['name']);
        }

        if (!empty($filters['created_by'])) {
            $query->byCreator($filters['created_by']);
        }

        // Handle search query
        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // ✅ FIX: Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        return $query->paginate($perPage);
    }

    /**
     * Create a new form
     */
    public function createForm(array $data): Form
    {
        return Form::create($data);
    }

    /**
     * Update a form
     */
    public function updateForm(Form $form, array $data): bool
    {
        return $form->update($data);
    }

    /**
     * Delete a form (soft delete)
     */
    public function deleteForm(Form $form): bool
    {
        return $form->delete();
    }

    /**
     * Get a single form with submissions for detailed view
     */
    public function getFormWithSubmissions(Form $form): Form
    {
        $cacheKey = "form_with_submissions_{$form->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($form) {
            return $form->load([
                'creator:id,name,email',
                'submissions' => function ($query) {
                    $query->with([
                            'contact:id,first_name,last_name,email',
                            'company:id,name,domain'
                        ])
                          ->orderBy('created_at', 'desc')
                          ->limit(10);
                }
            ])->loadCount('submissions');
        });
    }

    /**
     * Get form submissions
     */
    public function getFormSubmissions(Form $form, int $perPage = 15): LengthAwarePaginator
    {
        return $form->submissions()
            ->with([
                'contact:id,first_name,last_name,email',
                'company:id,name,domain'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Submit a form and create/update contact
     */
    public function submitForm(Form $form, array $data, ?string $ipAddress = null, ?string $userAgent = null): FormSubmission
    {
        return DB::transaction(function () use ($form, $data, $ipAddress, $userAgent) {
            // Extract contact information from form data
            $contactData = $this->extractContactData($form, $data);
            
            // Idempotency: prevent duplicate submissions within 60s for same form+email+payload
            $submissionHash = $this->computeSubmissionHash($form->id, $data);
            // In-flight lock to avoid race-condition duplicates
            $lockKey = 'form_submit:' . $form->id . ':' . $submissionHash;
            if (!Cache::add($lockKey, 1, 60)) {
                $existing = FormSubmission::where('form_id', $form->id)
                    ->where('created_at', '>=', now()->subSeconds(60))
                    ->where(function($q) use ($submissionHash) {
                        $q->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, "$.__hash")) = ?', [$submissionHash])
                          ->orWhere('payload', 'like', '%"__hash":"' . $submissionHash . '"%');
                    })
                    ->orderByDesc('id')
                    ->first();
                if ($existing) {
                    Log::info('Duplicate submission detected by lock, returning existing', [
                        'form_id' => $form->id,
                        'submission_id' => $existing->id,
                    ]);
                    return $existing->load(['contact:id,first_name,last_name,email,company_id','company:id,name,domain']);
                }
            }

            $existingRecent = FormSubmission::where('form_id', $form->id)
                ->where('created_at', '>=', now()->subSeconds(60))
                ->where(function($q) use ($submissionHash) {
                    $q->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(payload, "$.__hash")) = ?', [$submissionHash])
                      ->orWhere('payload', 'like', '%"__hash":"' . $submissionHash . '"%');
                })
                ->first();
            if ($existingRecent) {
                Log::info('Duplicate submission detected: returning existing submission', [
                    'form_id' => $form->id,
                    'submission_id' => $existingRecent->id,
                ]);
                return $existingRecent;
            }

            // Ensure owner is the form creator for new records
            $preferredOwnerId = $form->created_by ?? $form->tenant_id;

            // Derive and upsert company first (graceful on errors)
            $company = null;
            try {
                $deriver = app(CompanyDeriver::class);
                $derived = $deriver->derive($data);
                $company = $this->upsertCompanyForTenant(
                    $form->tenant_id,
                    $preferredOwnerId,
                    $derived['normalized_domain'],
                    $derived['normalized_name']
                );
                if (!empty($derived['normalized_domain'])) {
                    Log::info('Company derived from email domain', [
                        'domain' => $derived['normalized_domain'],
                        'form_id' => $form->id,
                    ]);
                }
                if ($company) {
                    Log::info('Company upserted', [
                        'form_id' => $form->id,
                        'company_id' => $company->id,
                        'name' => $company->name,
                        'domain' => $company->domain,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Company upsert failed during submit (continuing)', [
                    'form_id' => $form->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Find or create contact
            $contact = $this->findOrCreateContactWithOwner($form->tenant_id, $preferredOwnerId, $contactData, $form->name);

            // Link company to contact if needed
            if ($company && (empty($contact->company_id) || $contact->company_id !== $company->id)) {
                $contact->update(['company_id' => $company->id]);
                Log::info('Company linked to contact', [
                    'contact_id' => $contact->id,
                    'company_id' => $company->id,
                ]);
            }
            
            // Create form submission (store hash inside payload for soft idempotency tracking)
            $dataWithHash = array_merge($data, ['__hash' => $submissionHash]);
            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'contact_id' => $contact->id,
                'payload' => $dataWithHash,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'company_id' => $company?->id ?? $contact->company_id ?? null,
                'status' => 'pending',
            ]);

            // Ensure company_id is set synchronously (even if contact was just linked)
            if ($company && $submission->company_id !== $company->id) {
                $submission->update(['company_id' => $company->id]);
            }

            if ($submission->company_id) {
                Log::info('Company linked to submission', [
                    'submission_id' => $submission->id,
                    'company_id' => $submission->company_id,
                ]);
            }

            Log::info('Form submitted', [
                'form_id' => $form->id,
                'contact_id' => $contact->id,
                'submission_id' => $submission->id,
            ]);

            // Eager-load for immediate response
            return $submission->load([
                'contact:id,first_name,last_name,email,company_id',
                'company:id,name,domain'
            ]);
        });
    }

    /**
     * Extract contact data from form submission
     */
    private function extractContactData(Form $form, array $data): array
    {
        $contactData = [];
        
        // Debug: Check if form fields are null
        if ($form->fields === null) {
            Log::error('Form fields are null', ['form_id' => $form->id]);
            throw new \Exception('Form fields are null for form ID: ' . $form->id);
        }
        
        Log::info('Extracting contact data', ['form_fields' => $form->fields, 'submitted_data' => $data]);
        
        foreach ($form->fields as $field) {
            $fieldName = $field['name'];
            $fieldType = $field['type'];
            
            if (isset($data[$fieldName])) {
                switch ($fieldType) {
                    case 'email':
                        $contactData['email'] = $data[$fieldName];
                        break;
                    case 'phone':
                        $contactData['phone'] = $data[$fieldName];
                        break;
                    default:
                        // Handle name fields - fix the logic
                        if ($fieldName === 'first_name' || str_contains(strtolower($fieldName), 'first')) {
                            $contactData['first_name'] = $data[$fieldName];
                        } elseif ($fieldName === 'last_name' || str_contains(strtolower($fieldName), 'last')) {
                            $contactData['last_name'] = $data[$fieldName];
                        }
                        break;
                }
            }
        }
        
        Log::info('Extracted contact data', ['contact_data' => $contactData]);
        
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
     * Find or create contact based on email
     */
    private function findOrCreateContactWithOwner(int $tenantId, int $preferredOwnerId, array $contactData, string $sourceName): Contact
    {
        $email = $contactData['email'] ?? null;
        
        if ($email) {
            // Try to find existing contact by email
            $contact = Contact::where('email', $email)
                ->where('tenant_id', $tenantId)
                ->first();
                
            if ($contact) {
                // Update existing contact
                $updateData = array_merge($contactData, []);
                $contact->update($updateData);
                return $contact;
            }
        }

        // Create new contact
        $contactData['tenant_id'] = $tenantId;
        $contactData['owner_id'] = $preferredOwnerId ?: $this->assignSalesRep($tenantId);
        $contactData['source'] = $sourceName;
        $contactData['lifecycle_stage'] = $contactData['lifecycle_stage'] ?? 'lead';

        return Contact::create($contactData);
    }

    /**
     * Find or create company based on email domain
     */
    private function findOrCreateCompanyWithOwner(int $tenantId, int $preferredOwnerId, string $domain): Company
    {
        Log::info('Form submission debug: findOrCreateCompanyWithOwner called', [
            'tenant_id' => $tenantId,
            'preferred_owner_id' => $preferredOwnerId,
            'domain' => $domain,
        ]);

        $company = Company::where('domain', $domain)
            ->where('tenant_id', $tenantId)
            ->first();
            
        if ($company) {
            Log::info('Form submission debug: existing company found', [
                'company_id' => $company->id,
                'tenant_id' => $tenantId,
                'domain' => $domain,
            ]);
            return $company;
        }

        // Create new company with safe defaults
        try {
            $safeName = trim(ucfirst($domain)) ?: 'Unknown Company';
            $ownerId = $preferredOwnerId ?: $this->assignSalesRep($tenantId);
            $data = [
                'tenant_id' => $tenantId,
                'name' => $safeName,
                'domain' => $domain ?: null,
                'owner_id' => $ownerId,
                'status' => 'active',
                'source' => 'Request Demo Form',
            ];
            Log::info('Form submission debug: creating company', $data);
            $company = Company::create($data);
            Log::info('Form submission debug: company created', [ 'company_id' => $company->id ]);
            return $company;
        } catch (\Throwable $e) {
            Log::error('Form submission error: company creation failed', [
                'tenant_id' => $tenantId,
                'domain' => $domain,
                'preferred_owner_id' => $preferredOwnerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Upsert company for tenant using domain or name, dedup-first. Returns Company|null.
     */
    private function upsertCompanyForTenant(int $tenantId, int $ownerId, ?string $domain, string $name): ?Company
    {
        return DB::transaction(function () use ($tenantId, $ownerId, $domain, $name) {
            $query = Company::query()->where('tenant_id', $tenantId);
            if ($domain) {
                $existing = (clone $query)->whereRaw('LOWER(domain) = ?', [strtolower($domain)])->first();
                if ($existing) {
                    return $existing;
                }
            }
            $existingByName = (clone $query)->whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            if ($existingByName) {
                return $existingByName;
            }
            // Create new
            $data = [
                'tenant_id' => $tenantId,
                'name' => $name ?: 'Unknown Company',
                'domain' => $domain ? strtolower($domain) : null,
                'owner_id' => $ownerId ?: $this->assignSalesRep($tenantId),
                'status' => 'active',
            ];
            Log::info('Company upsert creating new company', $data);
            return Company::create($data);
        });
    }

    /**
     * Public helper to derive and upsert company from a submission payload.
     */
    public function deriveAndUpsertCompanyForPayload(Form $form, array $payload): ?Company
    {
        try {
            $deriver = app(CompanyDeriver::class);
            $derived = $deriver->derive($payload);
            $company = $this->upsertCompanyForTenant(
                $form->tenant_id,
                $form->created_by ?? $form->tenant_id,
                $derived['normalized_domain'],
                $derived['normalized_name']
            );
            if ($company) {
                Log::info('Company upserted (public helper)', [
                    'form_id' => $form->id,
                    'company_id' => $company->id,
                    'name' => $company->name,
                    'domain' => $company->domain,
                ]);
            }
            return $company;
        } catch (\Throwable $e) {
            Log::error('Company upsert failed (public helper)', [
                'form_id' => $form->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    /**
     * Compute a short idempotency hash for submission.
     */
    private function computeSubmissionHash(int $formId, array $payload): string
    {
        $email = $payload['email'] ?? $payload['email_address'] ?? '';
        $normalized = $this->normalizePayloadForHash($payload);
        return substr(hash('sha256', $formId . '|' . strtolower($email) . '|' . json_encode($normalized)), 0, 16);
    }

    private function normalizePayloadForHash(array $payload): array
    {
        ksort($payload);
        return $payload;
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










