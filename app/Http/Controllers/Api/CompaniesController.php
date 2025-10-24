<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\StoreCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Jobs\ImportCompaniesJob;
use App\Models\Company;
use App\Services\CompanyService;
use App\Services\TeamAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CompaniesController extends Controller
{
    public function __construct(
        private CompanyService $companyService,
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Display a listing of companies with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $filters = [
            'tenant_id' => optional($request->user())->tenant_id ?? $request->user()->id,
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
        $contactId = $data['contact_id'] ?? null;
        
        // Remove contact_id from data to avoid it being saved to company table
        unset($data['contact_id']);
        
        $data['tenant_id'] = optional($request->user())->tenant_id ?? $request->user()->id;

        // Use database transaction to ensure company creation and contact attachment succeed together
        $company = DB::transaction(function () use ($data, $contactId, $request) {
            // Create the company
            $company = $this->companyService->createCompany($data);
            
            // Auto-attach contact if contact_id is provided
            if ($contactId) {
                // Verify contact exists and belongs to same tenant
                $contact = \App\Models\Contact::where('id', $contactId)
                    ->where('tenant_id', $data['tenant_id'])
                    ->first();
                
                if ($contact) {
                    // Check if user has permission to attach this contact
                    $this->authorize('update', $company);
                    
                    // Attach the contact to the company
                    $this->companyService->attachContacts($company, [$contactId]);
                    
                    Log::info('Contact auto-attached to company during creation', [
                        'company_id' => $company->id,
                        'company_name' => $company->name,
                        'contact_id' => $contactId,
                        'contact_name' => $contact->first_name . ' ' . $contact->last_name,
                        'tenant_id' => $data['tenant_id'],
                        'user_id' => $request->user()->id
                    ]);
                } else {
                    Log::warning('Contact not found or does not belong to tenant during company creation', [
                        'contact_id' => $contactId,
                        'tenant_id' => $data['tenant_id'],
                        'company_id' => $company->id
                    ]);
                }
            }
            
            return $company;
        });

        // Load the company with contacts for the response
        $company->load(['owner:id,name,email', 'contacts']);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
            'message' => $contactId ? 'Company created and contact attached successfully' : 'Company created successfully'
        ], 201);
    }

    /**
     * Display the specified company.
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Company not found');
        }
        
        $company = Company::findOrFail((int) $id);
        
        $this->authorize('view', $company);

        $company->load(['owner:id,name,email', 'contacts']);

        // Get linked documents for this company
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? $user->id) : 1;
        
        // Get linked documents for this company using the new relationship approach
        $documentIds = \App\Models\DocumentRelationship::where('tenant_id', $tenantId)
            ->where('related_type', 'App\\Models\\Company')
            ->where('related_id', $id)
            ->pluck('document_id');
            
        $documents = \App\Models\Document::where('tenant_id', $tenantId)
            ->whereIn('id', $documentIds)
            ->whereNull('deleted_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
            'documents' => $documents
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(UpdateCompanyRequest $request, $id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Company not found');
        }
        
        $company = Company::findOrFail((int) $id);
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
    public function destroy($id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Company not found');
        }
        
        $company = Company::findOrFail((int) $id);
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

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
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
    public function restore(Request $request, $id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Company not found');
        }
        
        // Find the company first (including soft deleted ones)
        $company = Company::withTrashed()->findOrFail((int) $id);
        $this->authorize('restore', $company);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        
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
    public function attachContacts(Request $request, $id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Company not found');
        }
        
        $company = Company::findOrFail((int) $id);
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
    public function detachContact(Request $request, $id, $contactId): JsonResponse
    {
        // Validate that IDs are valid integers
        if (!is_numeric($id) || $id <= 0 || !is_numeric($contactId) || $contactId <= 0) {
            abort(404, 'Company or contact not found');
        }
        
        $company = Company::findOrFail((int) $id);
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
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
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
    public function activityLog($id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Company not found');
        }
        
        $company = Company::findOrFail((int) $id);
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
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

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

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = (int) (optional($request->user())->tenant_id ?? $request->user()->id);
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $file = $request->file('file');
        $fileName = 'companies_' . time() . '_' . Str::random(10) . '.csv';
        $path = 'imports/companies/' . $fileName;

        // Ensure directory exists
        Storage::disk('local')->makeDirectory('imports/companies');
        
        // Store the file
        $file->storeAs('imports/companies', $fileName, 'local');
        $fileSize = $file->getSize();
        $userId = $request->user()->id;

        // Determine processing mode based on file size and row count
        $csv = \League\Csv\Reader::createFromPath(Storage::path($path), 'r');
        $csv->setHeaderOffset(0);
        $rowCount = iterator_count($csv->getRecords());
        
        // Sync-first approach: Use sync for small files (≤1000 rows), async for large files
        $shouldUseSync = $rowCount <= 1000;
        
        Log::info('Company import started', [
            'path' => $path,
            'file_size' => $fileSize,
            'row_count' => $rowCount,
            'mode' => $shouldUseSync ? 'sync' : 'async',
            'tenant_id' => $tenantId,
            'user_id' => $userId
        ]);

        if ($shouldUseSync) {
            // Process immediately for small files
            try {
                $startTime = microtime(true);
                
                $job = new ImportCompaniesJob($path, $tenantId, $userId);
                $result = $job->handleSync(); // New method for sync processing
                
                $processingTime = round((microtime(true) - $startTime) * 1000, 2);
                
                Log::info('Company import completed synchronously', [
                    'path' => $path,
                    'row_count' => $rowCount,
                    'processing_time_ms' => $processingTime,
                    'imported' => $result['imported'] ?? 0,
                    'failed' => $result['failed'] ?? 0,
                    'mode' => 'sync'
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'imported' => $result['imported'] ?? 0,
                        'failed' => $result['failed'] ?? 0,
                        'total' => $result['total'] ?? 0,
                        'success_rate' => $result['success_rate'] ?? 0,
                        'mode' => 'sync',
                        'processing_time_ms' => $processingTime
                    ],
                    'message' => 'Companies imported successfully'
                ], 200);

            } catch (\Exception $e) {
                Log::error('Company import failed in sync mode', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                    'mode' => 'sync'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Company import failed: ' . $e->getMessage(),
                    'data' => [
                        'imported' => 0,
                        'failed' => $rowCount,
                        'mode' => 'sync'
                    ]
                ], 422);
            }
        } else {
            // Process asynchronously for large files
            try {
                ImportCompaniesJob::dispatch($path, $tenantId, $userId);
                
                Log::info('Company import job dispatched to queue for large file', [
                    'path' => $path,
                    'row_count' => $rowCount,
                    'mode' => 'async'
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'job_id' => uniqid('import_'),
                        'file_name' => $fileName,
                        'status' => 'queued',
                        'mode' => 'async'
                    ],
                    'message' => 'Import job queued successfully'
                ], 202);

            } catch (\Exception $e) {
                Log::error('Company import job dispatch failed, falling back to sync', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                    'fallback_mode' => 'sync'
                ]);

                // Fallback to sync processing if queue dispatch fails
                try {
                    $startTime = microtime(true);
                    
                    $job = new ImportCompaniesJob($path, $tenantId, $userId);
                    $result = $job->handleSync();
                    
                    $processingTime = round((microtime(true) - $startTime) * 1000, 2);
                    
                    Log::info('Large company import completed in sync fallback mode', [
                        'path' => $path,
                        'row_count' => $rowCount,
                        'processing_time_ms' => $processingTime,
                        'imported' => $result['imported'] ?? 0,
                        'failed' => $result['failed'] ?? 0,
                        'mode' => 'sync_fallback'
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'imported' => $result['imported'] ?? 0,
                            'failed' => $result['failed'] ?? 0,
                            'total' => $result['total'] ?? 0,
                            'success_rate' => $result['success_rate'] ?? 0,
                            'mode' => 'sync_fallback',
                            'processing_time_ms' => $processingTime
                        ],
                        'message' => 'Companies imported successfully (sync fallback)'
                    ], 200);

                } catch (\Exception $fallbackError) {
                    Log::error('Company import failed in sync fallback mode', [
                        'path' => $path,
                        'error' => $fallbackError->getMessage(),
                        'mode' => 'sync_fallback'
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Company import failed: ' . $fallbackError->getMessage(),
                        'data' => [
                            'imported' => 0,
                            'failed' => $rowCount,
                            'mode' => 'sync_fallback'
                        ]
                    ], 422);
                }
            }
        }
    }

    /**
     * Get contacts associated with a company.
     */
    public function getCompanyContacts($id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Company not found');
        }
        
        $company = Company::findOrFail((int) $id);
        $this->authorize('view', $company);

        $contacts = $company->contacts()->with(['owner:id,name,email'])->get();

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }

    /**
     * Get deals associated with a company.
     */
    public function getDeals(Request $request, $id): JsonResponse
    {
        // Validate that ID is a valid integer
        if (!is_numeric($id) || $id <= 0) {
            abort(404, 'Company not found');
        }
        
        $company = Company::findOrFail((int) $id);
        $this->authorize('view', $company);

        // Apply filters
        $query = $company->deals();
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        
        // Filter by stage
        if ($request->has('stage')) {
            $query->where('stage_id', $request->get('stage'));
        }
        
        // Filter by date range
        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->get('created_from'));
        }
        
        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->get('created_to'));
        }
        
        // Sort by most recent first
        $query->orderBy('updated_at', 'desc');
        
        // Pagination
        $perPage = min($request->get('limit', 10), 100);
        $deals = $query->with(['pipeline', 'stage', 'owner', 'contact'])
                      ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $deals->items(),
            'meta' => [
                'current_page' => $deals->currentPage(),
                'last_page' => $deals->lastPage(),
                'per_page' => $deals->perPage(),
                'total' => $deals->total(),
                'from' => $deals->firstItem(),
                'to' => $deals->lastItem(),
            ]
        ]);
    }


}
