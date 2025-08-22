<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\StoreCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Jobs\ImportCompaniesJob;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompaniesController extends Controller
{
    public function __construct(
        private CompanyService $companyService
    ) {}

    /**
     * Display a listing of companies with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $filters = [
            'tenant_id' => $request->header('X-Tenant-ID') ?? $request->user()->id,
            'name' => $request->get('name'),
            'q' => $request->get('q'), // Add search query parameter
            'industry' => $request->get('industry'),
            'type' => $request->get('type'),
            'owner_id' => $request->get('owner_id'),
            'sortBy' => $request->get('sortBy', 'created_at'),
            'sortOrder' => $request->get('sortOrder', 'desc'),
        ];

        $perPage = min($request->get('pageSize', 15), 100); // Max 100 per page

        $companies = $this->companyService->getCompanies($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => CompanyResource::collection($companies),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
                'from' => $companies->firstItem(),
                'to' => $companies->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created company.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $data = $request->validated();
        $data['tenant_id'] = $request->header('X-Tenant-ID') ?? $request->user()->id;

        $company = $this->companyService->createCompany($data);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
            'message' => 'Company created successfully'
        ], 201);
    }

    /**
     * Display the specified company.
     */
    public function show(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        
        $this->authorize('view', $company);

        $company->load(['owner:id,name,email', 'contacts']);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company)
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(UpdateCompanyRequest $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $data = $request->validated();
        
        $this->companyService->updateCompany($company, $data);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company->fresh()),
            'message' => 'Company updated successfully'
        ]);
    }

    /**
     * Remove the specified company (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('delete', $company);

        $this->companyService->deleteCompany($company);

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully'
        ]);
    }

    /**
     * Get deleted companies.
     */
    public function deleted(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()->id;
        $perPage = min($request->get('pageSize', 15), 100);

        $companies = $this->companyService->getDeletedCompanies($tenantId, $perPage);

        return response()->json([
            'success' => true,
            'data' => CompanyResource::collection($companies),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ]
        ]);
    }

    /**
     * Restore a deleted company.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        // Find the company first (including soft deleted ones)
        $company = Company::withTrashed()->findOrFail($id);
        $this->authorize('restore', $company);

        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()->id;
        
        $restored = $this->companyService->restoreCompany($id, $tenantId);

        if (!$restored) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or could not be restored'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Company restored successfully'
        ]);
    }

    /**
     * Attach contacts to a company.
     */
    public function attachContacts(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'integer|exists:contacts,id'
        ]);

        $contactIds = $request->input('contact_ids');
        $this->companyService->attachContacts($company, $contactIds);

        return response()->json([
            'success' => true,
            'message' => 'Contacts attached successfully'
        ]);
    }

    /**
     * Detach a contact from a company.
     */
    public function detachContact(Request $request, int $id, int $contactId): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $detached = $this->companyService->detachContact($company, $contactId);

        if (!$detached) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found or not associated with this company'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact detached successfully'
        ]);
    }

    /**
     * Check for duplicate companies.
     */
    public function checkDuplicates(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $request->validate([
            'name' => 'nullable|string',
            'domain' => 'nullable|string',
            'website' => 'nullable|url',
            'exclude_id' => 'nullable|integer|exists:companies,id'
        ]);

        $data = $request->only(['name', 'domain', 'website']);
        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()->id;
        $excludeId = $request->input('exclude_id');

        $duplicates = $this->companyService->checkDuplicates($data, $tenantId, $excludeId);

        return response()->json([
            'success' => true,
            'data' => CompanyResource::collection($duplicates),
            'meta' => [
                'count' => $duplicates->count()
            ]
        ]);
    }

    /**
     * Get company activity log (placeholder).
     */
    public function activityLog(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('view', $company);

        // Placeholder for activity log - would integrate with a logging system
        return response()->json([
            'success' => true,
            'data' => [],
            'message' => 'Activity log feature coming soon'
        ]);
    }

    /**
     * Bulk create companies.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $request->validate([
            'companies' => 'required|array|max:100',
            'companies.*.name' => 'required|string|max:255',
            'companies.*.domain' => 'nullable|string|max:255',
            'companies.*.website' => 'nullable|url|max:255',
            'companies.*.industry' => 'nullable|string|max:100',
            'companies.*.size' => 'nullable|integer|min:1',
            'companies.*.type' => 'nullable|string|max:50',
            'companies.*.annual_revenue' => 'nullable|numeric|min:0',
            'companies.*.timezone' => 'nullable|string|max:50',
            'companies.*.description' => 'nullable|string|max:1000',
            'companies.*.linkedin_page' => 'nullable|url|max:255',
            'companies.*.owner_id' => 'nullable|exists:users,id',
        ]);

        $companiesData = $request->input('companies');
        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()->id;

        $result = $this->companyService->bulkCreate($companiesData, $tenantId);

        return response()->json([
            'success' => true,
            'data' => [
                'created' => CompanyResource::collection($result['created']),
                'errors' => $result['errors']
            ],
            'meta' => [
                'created_count' => count($result['created']),
                'error_count' => count($result['errors'])
            ]
        ]);
    }

    /**
     * Import companies from CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240' // 10MB max
        ]);

        $file = $request->file('file');
        $fileName = 'companies_' . time() . '_' . Str::random(10) . '.csv';
        $filePath = storage_path('app/imports/companies/' . $fileName);

        // Ensure directory exists
        Storage::disk('local')->makeDirectory('imports/companies');
        
        // Store the file
        $file->storeAs('imports/companies', $fileName, 'local');

        // Dispatch import job
        ImportCompaniesJob::dispatch(
            $filePath,
            1, // Use tenant ID 1 as default
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => uniqid('import_'),
                'file_name' => $fileName
            ],
            'message' => 'Import job queued successfully'
        ], 202);
    }

    /**
     * Get contacts associated with a company.
     */
    public function getCompanyContacts(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('view', $company);

        $contacts = $company->contacts()->with(['owner:id,name,email'])->get();

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }


}
