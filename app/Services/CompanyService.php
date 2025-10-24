<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Services\TeamAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CompanyService
{
    public function __construct(
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Get paginated companies with filters
     */
    public function getCompanies(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Company::query()
            ->forTenant($filters['tenant_id'] ?? null)
            ->with(['owner:id,name,email', 'contacts:id,company_id']);

        // Apply filters
        if (!empty($filters['name'])) {
            $query->searchByName($filters['name']);
        }

        // Handle search query
        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            Log::info('Searching for companies with term: ' . $searchTerm);
            
            $query->where(function($q) use ($searchTerm) {
                // Primary search on company name (exact match first, then partial)
                $q->where('name', 'like', $searchTerm . '%')  // Starts with
                  ->orWhere('name', 'like', '%' . $searchTerm . '%')  // Contains
                  ->orWhere('domain', 'like', '%' . $searchTerm . '%')  // Domain contains
                  ->orWhere('website', 'like', '%' . $searchTerm . '%'); // Website contains
            });
            
            Log::info('Search query built, will execute with filters: ' . json_encode($filters));
        }

        if (!empty($filters['industry'])) {
            $query->byIndustry($filters['industry']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['owner_id'])) {
            $query->byOwner($filters['owner_id']);
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Apply team filtering if enabled
        $this->teamAccessService->applyTeamFilter($query);

        // Create cache key for this specific query
        $cacheKey = "companies_list_{$filters['tenant_id']}_" . md5(serialize($filters)) . "_{$perPage}";
        
        return Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->paginate($perPage);
        });
    }

    /**
     * Get deleted companies
     */
    public function getDeletedCompanies(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return Company::onlyTrashed()
            ->forTenant($tenantId)
            ->with(['owner:id,name,email'])
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new company
     */
    public function createCompany(array $data): Company
    {
        return Company::create($data);
    }

    /**
     * Update a company
     */
    public function updateCompany(Company $company, array $data): bool
    {
        return $company->update($data);
    }

    /**
     * Delete a company (soft delete)
     */
    public function deleteCompany(Company $company): bool
    {
        return $company->delete();
    }

    /**
     * Restore a deleted company
     */
    public function restoreCompany(int $companyId, int $tenantId): bool
    {
        $company = Company::onlyTrashed()
            ->where('id', $companyId)
            ->forTenant($tenantId)
            ->first();

        return $company ? $company->restore() : false;
    }

    /**
     * Attach contacts to a company
     */
    public function attachContacts(Company $company, array $contactIds): void
    {
        Contact::whereIn('id', $contactIds)
            ->where('tenant_id', $company->tenant_id)
            ->update(['company_id' => $company->id]);
    }

    /**
     * Detach a contact from a company
     */
    public function detachContact(Company $company, int $contactId): bool
    {
        $contact = Contact::where('id', $contactId)
            ->where('company_id', $company->id)
            ->where('tenant_id', $company->tenant_id)
            ->first();

        if ($contact) {
            $contact->update(['company_id' => null]);
            return true;
        }

        return false;
    }

    /**
     * Check for duplicate companies
     */
    public function checkDuplicates(array $data, int $tenantId, ?int $excludeId = null): Collection
    {
        $query = Company::forTenant($tenantId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $duplicates = collect();

        // Check by domain
        if (!empty($data['domain'])) {
            $domainDuplicates = $query->where('domain', $data['domain'])->get();
            $duplicates = $duplicates->merge($domainDuplicates);
        }

        // Check by name (fuzzy match)
        if (!empty($data['name'])) {
            $nameDuplicates = $query->where('name', 'like', '%' . $data['name'] . '%')->get();
            $duplicates = $duplicates->merge($nameDuplicates);
        }

        // Check by website
        if (!empty($data['website'])) {
            $websiteDuplicates = $query->where('website', $data['website'])->get();
            $duplicates = $duplicates->merge($websiteDuplicates);
        }

        return $duplicates->unique('id');
    }

    /**
     * Get metadata for industries
     */
    public function getIndustries(int $tenantId): Collection
    {
        $industries = Company::forTenant($tenantId)
            ->whereNotNull('industry')
            ->distinct()
            ->pluck('industry')
            ->sort()
            ->values();
        
        // If no industries found, return default options
        if ($industries->isEmpty()) {
            return collect([
                'Technology',
                'Healthcare',
                'Finance',
                'Education',
                'Manufacturing',
                'Retail',
                'Consulting',
                'Real Estate',
                'Transportation',
                'Entertainment',
                'Non-Profit',
                'Other'
            ]);
        }
        
        return $industries;
    }

    /**
     * Get metadata for company types
     */
    public function getCompanyTypes(int $tenantId): Collection
    {
        $types = Company::forTenant($tenantId)
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->sort()
            ->values();
        
        // If no types found, return default options
        if ($types->isEmpty()) {
            return collect([
                'Corporation',
                'LLC',
                'Partnership',
                'Sole Proprietorship',
                'Startup',
                'Non-Profit',
                'Government',
                'Educational',
                'Healthcare',
                'Financial',
                'Technology',
                'Other'
            ]);
        }
        
        return $types;
    }

    /**
     * Get metadata for owners
     */
    public function getOwners(int $tenantId): Collection
    {
        return Company::forTenant($tenantId)
            ->with('owner:id,name,email')
            ->whereNotNull('owner_id')
            ->get()
            ->pluck('owner')
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Bulk create companies
     */
    public function bulkCreate(array $companiesData, int $tenantId): array
    {
        $created = [];
        $errors = [];

        foreach ($companiesData as $index => $data) {
            try {
                $data['tenant_id'] = $tenantId;
                $company = $this->createCompany($data);
                $created[] = $company;
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'data' => $data,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'created' => $created,
            'errors' => $errors
        ];
    }

    /**
     * Attach a contact to a company
     */
    public function attachContact(Company $company, int $contactId): bool
    {
        // Check if contact exists and is not already attached
        $contact = Contact::find($contactId);
        if (!$contact) {
            return false;
        }

        // Check if already attached
        if ($company->contacts()->where('id', $contactId)->exists()) {
            return false;
        }

        // Attach the contact by updating its company_id
        $contact->update(['company_id' => $company->id]);
        return true;
    }
}
