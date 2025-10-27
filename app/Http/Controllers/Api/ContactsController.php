<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Jobs\ImportContactsJob;
use App\Models\Contact;
use App\Services\CampaignAutomationService;
use App\Services\AssignmentService;
use App\Services\TeamAccessService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContactsController extends Controller
{
    public function __construct(
        private CacheRepository $cache,
        private CampaignAutomationService $automationService,
        private AssignmentService $assignmentService,
        private TeamAccessService $teamAccessService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $userId = $request->user()->id;

        $query = Contact::query();

        // Filter by tenant_id to ensure users only see contacts from their organization
        // Note: Removed owner_id filter to allow users to see all contacts assigned by assignment rules
        $query->where('tenant_id', $tenantId);

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        }
        if ($stage = $request->query('stage')) {
            $query->where('lifecycle_stage', $stage);
        }
        if ($from = $request->query('created_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('created_to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column, $direction);

        // Show contacts if (a) they have no submission at all OR (b) they have at least one processed submission
        $query->where(function ($q) {
            $q->whereNotExists(function ($q1) {
                $q1->select(DB::raw(1))
                    ->from('form_submissions as fs_none')
                    ->whereColumn('fs_none.contact_id', 'contacts.id');
            })
            ->orWhereExists(function ($q2) {
                $q2->select(DB::raw(1))
                    ->from('form_submissions as fs_ok')
                    ->whereColumn('fs_ok.contact_id', 'contacts.id')
                    ->where('fs_ok.status', 'processed');
            });
        });

        // Apply team filtering if team access is enabled
        // Admin users see all tenant data regardless of team settings
        if (!$request->user()->hasRole('admin')) {
            $this->teamAccessService->applyTeamFilter($query);
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        
        // Create cache key for this specific query
        $cacheKey = "contacts_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        $contacts = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->paginate($perPage);
        });

        return response()->json([
            'data' => $contacts->items(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
                'from' => $contacts->firstItem(),
                'to' => $contacts->lastItem(),
            ],
        ]);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

        // Idempotency via table with 5-minute window
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $cacheKey = null;
        if ($idempotencyKey !== '') {
            $existing = DB::table('idempotency_keys')
                ->where('user_id', $request->user()->id)
                ->where('route', 'contacts.store')
                ->where('key', $idempotencyKey)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();
            if ($existing) {
                return response()->json(json_decode($existing->response, true));
            }
        }

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;

        $contact = Contact::create($data);

        // Always run assignment logic (override approach - rules take priority)
        try {
            $originalOwnerId = $contact->owner_id;
            $assignedUserId = $this->assignmentService->assignOwnerForRecord($contact, 'contact', [
                'tenant_id' => $tenantId,
                'created_by' => $request->user()->id
            ]);

            // If assignment rule found a match, apply assignment (owner_id and team_id)
            if ($assignedUserId) {
                $this->assignmentService->applyAssignmentToRecord($contact, $assignedUserId);
                Log::info('Contact assigned via assignment rules (override)', [
                    'contact_id' => $contact->id,
                    'original_owner_id' => $originalOwnerId,
                    'assigned_user_id' => $assignedUserId,
                    'tenant_id' => $tenantId,
                    'override_type' => $originalOwnerId ? 'manual_override' : 'auto_assignment'
                ]);
            } else {
                Log::info('No assignment rule matched, keeping original owner', [
                    'contact_id' => $contact->id,
                    'owner_id' => $originalOwnerId,
                    'tenant_id' => $tenantId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to run assignment rules for contact', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
        }

        // Trigger automation for contact creation
        try {
            $this->automationService->triggerContactCreated($contact->id, $data);
            Log::info('Contact creation automation triggered', [
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'tenant_id' => $contact->tenant_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger contact creation automation', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
        }

        // Trigger lead scoring for contact creation
        try {
            $leadScoringService = new \App\Services\LeadScoringService();
            $leadScoringService->processEvent([
                'event' => 'contact_created',
                'contact_id' => $contact->id,
                'tenant_id' => $contact->tenant_id,
                'created_at' => now()->toISOString()
            ], $contact->tenant_id);
            
            Log::info('Lead scoring triggered for contact creation', [
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'tenant_id' => $contact->tenant_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trigger lead scoring for contact creation', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
        }

        $response = [
            'data' => $contact,
            'meta' => [ 'page' => 1, 'total' => 1 ],
        ];

        if (! empty($idempotencyKey)) {
            DB::table('idempotency_keys')->insert([
                'user_id' => $request->user()->id,
                'route' => 'contacts.store',
                'key' => $idempotencyKey,
                'response' => json_encode($response),
                'created_at' => now(),
            ]);
        }

        return response()->json($response, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // Fetch by id only; tenant is enforced via global scope
        $contact = Contact::with('company')->findOrFail($id);

        $this->authorize('view', $contact);

        // Get linked documents for this contact using the new relationship approach
        $user = $request->user();
        $tenantId = $user->tenant_id ?? $user->id;
        
        $documentIds = \App\Models\DocumentRelationship::where('tenant_id', $tenantId)
            ->where(function($query) {
                $query->where('related_type', 'App\\Models\\Contact')
                      ->orWhere('related_type', 'contact');
            })
            ->where('related_id', $id)
            ->pluck('document_id');
            
        $documents = \App\Models\Document::where('tenant_id', $tenantId)
            ->whereIn('id', $documentIds)
            ->whereNull('deleted_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'contact' => $contact,
                'documents' => $documents,
                'timeline_summary' => [],
            ],
            'meta' => [ 'page' => 1, 'total' => 1 ],
        ]);
    }

    public function update(UpdateContactRequest $request, int $id): JsonResponse
    {
        // Fetch by id only; tenant is enforced via global scope
        $contact = Contact::findOrFail($id);
        $this->authorize('update', $contact);

        $contact->fill($request->validated());
        $contact->save();

        return response()->json([
            'data' => $contact,
            'meta' => [ 'page' => 1, 'total' => 1 ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // Fetch by id only; tenant is enforced via global scope
        $contact = Contact::findOrFail($id);
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->json([
            'data' => null,
            'meta' => [ 'page' => 1, 'total' => 0 ],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);
        $tenantId = (int) (optional($request->user())->tenant_id ?? $request->user()->id);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $path = $request->file('file')->store('imports/contacts');
        $fileSize = $request->file('file')->getSize();
        $userId = $request->user()->id;

        // Determine processing mode based on file size and row count
        $csv = \League\Csv\Reader::createFromPath(Storage::path($path), 'r');
        $csv->setHeaderOffset(0);
        $rowCount = iterator_count($csv->getRecords());
        
        // Sync-first approach: Use sync for small files (≤1000 rows), async for large files
        $shouldUseSync = $rowCount <= 1000;
        
        Log::info('Contact import started', [
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
                
                $job = new ImportContactsJob($path, $tenantId, $userId);
                $result = $job->handleSync(); // New method for sync processing
                
                $processingTime = round((microtime(true) - $startTime) * 1000, 2);
                
                Log::info('Contact import completed synchronously', [
                    'path' => $path,
                    'row_count' => $rowCount,
                    'processing_time_ms' => $processingTime,
                    'imported' => $result['imported'] ?? 0,
                    'failed' => $result['failed'] ?? 0,
                    'mode' => 'sync'
                ]);

                return response()->json([
                    'data' => [
                        'job_id' => spl_object_hash($job),
                        'status' => 'completed',
                        'message' => "Import completed successfully - {$result['imported']} contacts imported",
                        'imported' => $result['imported'] ?? 0,
                        'failed' => $result['failed'] ?? 0,
                        'mode' => 'sync',
                        'processing_time_ms' => $processingTime
                    ],
                    'meta' => [ 'page' => 1, 'total' => 1 ],
                ], 200);
                
            } catch (\Exception $e) {
                Log::error('Contact import failed in sync mode', [
                    'error' => $e->getMessage(),
                    'path' => $path,
                    'row_count' => $rowCount,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'mode' => 'sync'
                ]);

                return response()->json([
                    'error' => 'Import failed: ' . $e->getMessage(),
                    'data' => null,
                ], 500);
            }
        } else {
            // Use async for large files
            try {
                $job = new ImportContactsJob($path, $tenantId, $userId);
                dispatch($job);
                
                Log::info('Contact import job dispatched to queue for large file', [
                    'job_id' => spl_object_hash($job),
                    'path' => $path,
                    'row_count' => $rowCount,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'mode' => 'async'
                ]);

                return response()->json([
                    'data' => [ 
                        'job_id' => spl_object_hash($job),
                        'status' => 'queued',
                        'message' => "Large import queued ({$rowCount} contacts) - will be processed in background",
                        'row_count' => $rowCount,
                        'mode' => 'async'
                    ],
                    'meta' => [ 'page' => 1, 'total' => 1 ],
                ], 202);
                
            } catch (\Exception $e) {
                Log::warning('Failed to dispatch large import job, falling back to sync mode', [
                    'error' => $e->getMessage(),
                    'path' => $path,
                    'row_count' => $rowCount
                ]);
                
                // Fallback to sync even for large files if queue fails
                try {
                    $startTime = microtime(true);
                    
                    $job = new ImportContactsJob($path, $tenantId, $userId);
                    $result = $job->handleSync();
                    
                    $processingTime = round((microtime(true) - $startTime) * 1000, 2);
                    
                    Log::info('Large contact import completed in sync fallback mode', [
                        'path' => $path,
                        'row_count' => $rowCount,
                        'processing_time_ms' => $processingTime,
                        'imported' => $result['imported'] ?? 0,
                        'failed' => $result['failed'] ?? 0,
                        'mode' => 'sync_fallback'
                    ]);

                    return response()->json([
                        'data' => [
                            'job_id' => spl_object_hash($job),
                            'status' => 'completed',
                            'message' => "Import completed successfully - {$result['imported']} contacts imported",
                            'imported' => $result['imported'] ?? 0,
                            'failed' => $result['failed'] ?? 0,
                            'mode' => 'sync_fallback',
                            'processing_time_ms' => $processingTime
                        ],
                        'meta' => [ 'page' => 1, 'total' => 1 ],
                    ], 200);
                    
                } catch (\Exception $fallbackError) {
                    Log::error('Contact import failed in sync fallback mode', [
                        'error' => $fallbackError->getMessage(),
                        'path' => $path,
                        'row_count' => $rowCount,
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'mode' => 'sync_fallback'
                    ]);

                    return response()->json([
                        'error' => 'Import failed: ' . $fallbackError->getMessage(),
                        'data' => null,
                    ], 500);
                }
            }
        }
    }

    public function search(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        $results = Contact::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('first_name', 'like', "%$q%")
                        ->orWhere('last_name', 'like', "%$q%")
                        ->orWhere('email', 'like', "%$q%")
                        ->orWhere('phone', 'like', "%$q%");
                });
            })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $results,
            'meta' => [ 'page' => 1, 'total' => $results->count() ],
        ]);
    }

    /**
     * Get the company linked to a contact.
     */
    public function getCompany(Request $request, int $id): JsonResponse
    {
        // Align with show(): fetch by id and authorize; avoid false 404s due to filters
        $contact = Contact::whereNull('deleted_at')->find($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }
        $this->authorize('view', $contact);

        $company = $contact->company;

        return response()->json([
            'data' => $company,
            'meta' => [ 'page' => 1, 'total' => $company ? 1 : 0 ],
        ]);
    }

    /**
     * Get deals linked to a contact.
     */
    public function getDeals(Request $request, int $id): JsonResponse
    {
        // Fetch by id only; tenant is enforced via global scope
        $contact = Contact::findOrFail($id);
        
        $this->authorize('view', $contact);

        $query = \App\Models\Deal::query()
            ->where('contact_id', $id)
            ->with(['pipeline', 'stage', 'owner', 'company']);

        // Apply filters
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($stageId = $request->query('stage_id')) {
            $query->where('stage_id', $stageId);
        }
        if ($pipelineId = $request->query('pipeline_id')) {
            $query->where('pipeline_id', $pipelineId);
        }
        if ($from = $request->query('created_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('created_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Sorting
        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column, $direction);

        $perPage = min((int) $request->query('per_page', 15), 100);
        $deals = $query->paginate($perPage);

        return response()->json([
            'data' => $deals->items(),
            'meta' => [
                'current_page' => $deals->currentPage(),
                'last_page' => $deals->lastPage(),
                'per_page' => $deals->perPage(),
                'total' => $deals->total(),
                'from' => $deals->firstItem(),
                'to' => $deals->lastItem(),
            ],
        ]);
    }

    /**
     * Get activities timeline for a contact.
     */
    public function getActivities(Request $request, int $id): JsonResponse
    {
        // Fetch by id only; tenant is enforced via global scope
        $contact = Contact::findOrFail($id);
        
        $this->authorize('view', $contact);

        $query = \App\Models\Activity::query()
            ->where('related_type', 'App\\Models\\Contact')
            ->where('related_id', $id)
            ->with(['owner', 'related']);

        // Apply filters
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('scheduled_from')) {
            $query->where('scheduled_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
        }
        if ($to = $request->query('scheduled_to')) {
            $query->where('scheduled_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
        }

        // Sorting by most recent first
        $query->orderBy('scheduled_at', 'desc')
              ->orderBy('created_at', 'desc');

        $perPage = min((int) $request->query('per_page', 15), 100);
        $activities = $query->paginate($perPage);

        return response()->json([
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
            ],
        ]);
    }
}


