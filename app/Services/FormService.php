<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Contact;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormService
{
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
        return $form->load([
            'creator:id,name,email',
            'submissions' => function ($query) {
                $query->with(['contact:id,first_name,last_name,email'])
                      ->orderBy('created_at', 'desc')
                      ->limit(10);
            }
        ])->loadCount('submissions');
    }

    /**
     * Get form submissions
     */
    public function getFormSubmissions(Form $form, int $perPage = 15): LengthAwarePaginator
    {
        return $form->submissions()
            ->with(['contact:id,first_name,last_name,email'])
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
            
            // Find or create contact
            $contact = $this->findOrCreateContact($form->tenant_id, $contactData);
            
            // Create form submission
            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'contact_id' => $contact->id,
                'payload' => $data,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            Log::info('Form submitted', [
                'form_id' => $form->id,
                'contact_id' => $contact->id,
                'submission_id' => $submission->id,
            ]);

            return $submission;
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
    private function findOrCreateContact(int $tenantId, array $contactData): Contact
    {
        $email = $contactData['email'] ?? null;
        
        if ($email) {
            // Try to find existing contact by email
            $contact = Contact::where('email', $email)
                ->where('tenant_id', $tenantId)
                ->first();
                
            if ($contact) {
                // Update existing contact
                $updateData = array_merge($contactData, [
                    'source' => 'Request Demo Form',
                ]);
                $contact->update($updateData);
                return $contact;
            }
        }

        // Create new contact
        $contactData['tenant_id'] = $tenantId;
        $contactData['owner_id'] = $this->assignSalesRep($tenantId);
        $contactData['source'] = 'Request Demo Form';
        $contactData['lifecycle_stage'] = $contactData['lifecycle_stage'] ?? 'lead';
        
        // Create company if email domain is new
        if ($email) {
            $domain = $this->extractDomain($email);
            if ($domain) {
                $company = $this->findOrCreateCompany($tenantId, $domain);
                $contactData['company_id'] = $company->id;
            }
        }

        return Contact::create($contactData);
    }

    /**
     * Find or create company based on email domain
     */
    private function findOrCreateCompany(int $tenantId, string $domain): Company
    {
        $company = Company::where('domain', $domain)
            ->where('tenant_id', $tenantId)
            ->first();
            
        if ($company) {
            return $company;
        }

        // Create new company
        return Company::create([
            'tenant_id' => $tenantId,
            'name' => ucfirst($domain),
            'domain' => $domain,
            'owner_id' => $this->assignSalesRep($tenantId),
        ]);
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










