<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CompanyService
{
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

        if (!empty($filters['industry'])) {
            $query->byIndustry($filters['industry']);
        }

        if (!empty($filters['owner_id'])) {
            $query->byOwner($filters['owner_id']);
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
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
        return Company::forTenant($tenantId)
            ->whereNotNull('industry')
            ->distinct()
            ->pluck('industry')
            ->sort()
            ->values();
    }

    /**
     * Get metadata for company types
     */
    public function getCompanyTypes(int $tenantId): Collection
    {
        return Company::forTenant($tenantId)
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->sort()
            ->values();
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
}
