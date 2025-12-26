<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\StoreDealRequest;
use App\Http\Requests\Deals\UpdateDealRequest;
use App\Http\Requests\Deals\MoveDealRequest;
use App\Http\Resources\DealResource;
use App\Http\Resources\DealStageHistoryResource;
use App\Models\Deal;
use App\Models\DealStageHistory;
use App\Services\AssignmentService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;


class DealsController extends Controller
{
    public function __construct(
        private AssignmentService $assignmentService,
        private TeamAccessService $teamAccessService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Deal::class);

        // Get tenant_id from authenticated user - ensure proper tenant isolation
        $user = $request->user();
        $tenantId = $user->tenant_id;
        
        // Validate tenant_id exists
        if (!$tenantId) {
            Log::error('DealsController: User has no tenant_id', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            abort(403, 'Invalid tenant access');
        }
        
        $userId = $user->id;

        $query = Deal::query()->where('tenant_id', $tenantId);

        // Apply owner-based filtering only if user is not admin
        // Admin users should see all deals in their tenant
        if (!$user->hasRole('admin')) {
            $query->where('owner_id', $userId);
        }
        
        // Apply team filtering if team access is enabled
        // Admin users see all tenant data regardless of team settings
        if (!$user->hasRole('admin')) {
            $this->teamAccessService->applyTeamFilter($query);
        }

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        }
        if ($pipelineId = $request->query('pipeline_id')) {
            $query->where('pipeline_id', $pipelineId);
        }
        if ($stageId = $request->query('stage_id')) {
            $query->where('stage_id', $stageId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('created_from') ?: $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('created_to') ?: $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }
        if ($valueMin = $request->query('value_min') ?: $request->query('min_value')) {
            $query->where('value', '>=', $valueMin);
        }
        if ($valueMax = $request->query('value_max') ?: $request->query('max_value')) {
            $query->where('value', '<=', $valueMax);
        }

        $sort = (string) $request->query('sort', '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column, $direction);

        $perPage = min((int) $request->query('per_page', 15), 100);
        
        // Create cache key for this specific query
        $cacheKey = "deals_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        // Cache deals list for 5 minutes (300 seconds) - increased from 30 seconds for better performance
        $deals = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->with(['pipeline', 'stage', 'owner', 'contact', 'company'])->paginate($perPage);
        });

        return response()->json([
            'data' => DealResource::collection($deals->items()),
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

    public function store(StoreDealRequest $request): JsonResponse
    {
        $this->authorize('create', Deal::class);

        // Get tenant_id from authenticated user - ensure proper tenant isolation
        $user = $request->user();
        $tenantId = $user->tenant_id;
        
        // Validate tenant_id exists
        if (!$tenantId) {
            Log::error('DealsController: User has no tenant_id', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            abort(403, 'Invalid tenant access');
        }

        // Idempotency via table with 5-minute window
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        $cacheKey = null;
        if ($idempotencyKey !== '') {
            $existing = DB::table('idempotency_keys')
                ->where('user_id', $request->user()->id)
                ->where('route', 'deals.store')
                ->where('key', $idempotencyKey)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();
            if ($existing) {
                return response()->json(json_decode($existing->response, true));
            }
        }

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        
        // Create the deal with the owner_id from frontend (if provided)
        $deal = Deal::create($data);

        // Run assignment logic to potentially override the owner (like ContactsController)
        try {
            $originalOwnerId = $deal->owner_id;
            $assignedUserId = $this->assignmentService->assignOwnerForRecord($deal, 'deal', [
                'tenant_id' => $tenantId,
                'created_by' => $user->id
            ]);

            // If assignment rule found a match, apply assignment (owner_id and team_id)
            if ($assignedUserId) {
                $this->assignmentService->applyAssignmentToRecord($deal, $assignedUserId);
                Log::info('Deal assigned via assignment rules (override)', [
                    'deal_id' => $deal->id,
                    'original_owner_id' => $originalOwnerId,
                    'assigned_user_id' => $assignedUserId,
                    'tenant_id' => $tenantId,
                    'override_type' => $originalOwnerId ? 'manual_override' : 'auto_assignment'
                ]);
            } else {
                Log::info('No assignment rule matched, keeping original owner', [
                    'deal_id' => $deal->id,
                    'owner_id' => $originalOwnerId,
                    'tenant_id' => $tenantId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to run assignment rules for deal', [
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
        }

        // Clear deals cache to ensure immediate visibility of new deal
        $this->clearDealsCache($tenantId, $user->id);

        $response = [
            'data' => new DealResource($deal->load(['pipeline', 'stage', 'owner', 'contact', 'company'])),
            'meta' => ['page' => 1, 'total' => 1],
        ];

        // Store idempotency response
        if ($idempotencyKey !== '') {
            DB::table('idempotency_keys')->insert([
                'user_id' => $request->user()->id,
                'route' => 'deals.store',
                'key' => $idempotencyKey,
                'response' => json_encode($response),
                'created_at' => now(),
            ]);
        }

        return response()->json($response, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from authenticated user - ensure proper tenant isolation
        $user = $request->user();
        $tenantId = $user->tenant_id;
        
        // Validate tenant_id exists
        if (!$tenantId) {
            Log::error('DealsController: User has no tenant_id', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            abort(403, 'Invalid tenant access');
        }
        
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $deal);

        // Get linked documents for this deal using the new relationship approach
        $documentIds = \App\Models\DocumentRelationship::where('tenant_id', $tenantId)
            ->where('related_type', 'App\\Models\\Deal')
            ->where('related_id', $id)
            ->pluck('document_id');
            
        $documents = \App\Models\Document::where('tenant_id', $tenantId)
            ->whereIn('id', $documentIds)
            ->whereNull('deleted_at')
            ->get();

        return response()->json([
            'data' => new DealResource($deal->load(['pipeline', 'stage', 'owner', 'contact', 'company'])),
            'documents' => $documents,
        ]);
    }

    public function update(UpdateDealRequest $request, int $id): JsonResponse
    {
        // Get tenant_id from authenticated user - ensure proper tenant isolation
        $user = $request->user();
        $tenantId = $user->tenant_id;
        
        // Validate tenant_id exists
        if (!$tenantId) {
            Log::error('DealsController: User has no tenant_id', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            abort(403, 'Invalid tenant access');
        }
        
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $deal);

        // Protect tenant_id and owner_id from being overwritten
        $data = $request->validated();
        $data['tenant_id'] = $deal->tenant_id; // Preserve original tenant_id
        $data['owner_id'] = $deal->owner_id;   // Preserve original owner_id
        
        $deal->update($data);

        return response()->json([
            'data' => new DealResource($deal->load(['pipeline', 'stage', 'owner', 'contact', 'company'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from authenticated user - ensure proper tenant isolation
        $user = $request->user();
        $tenantId = $user->tenant_id;
        
        // Validate tenant_id exists
        if (!$tenantId) {
            Log::error('DealsController: User has no tenant_id', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            abort(403, 'Invalid tenant access');
        }
        
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('delete', $deal);

        $deal->delete();

        return response()->json(['message' => 'Deal deleted successfully']);
    }

    public function move(MoveDealRequest $request, int $id): JsonResponse
    {
        // Get tenant_id from authenticated user - ensure proper tenant isolation
        $user = $request->user();
        $tenantId = $user->tenant_id;
        
        // Validate tenant_id exists
        if (!$tenantId) {
            Log::error('DealsController: User has no tenant_id', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            abort(403, 'Invalid tenant access');
        }
        
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('move', $deal);

        // Capture previous stage BEFORE update
        $previousStageId = $deal->stage_id;
        $validated = $request->validated();
        $newStageId = $validated['stage_id'];
        $reason = $validated['reason'];
        
        // Only proceed if stage actually changed
        if ($previousStageId == $newStageId) {
            return response()->json([
                'message' => 'Deal is already in this stage',
            ], 422);
        }
        
        // Use database transaction for consistency
        DB::transaction(function () use ($deal, $newStageId, $previousStageId, $reason, $user, $tenantId) {
            // Update deal stage
            $deal->update(['stage_id' => $newStageId]);
            
            // Create history record (lightweight, fast insert)
            DealStageHistory::create([
                'deal_id' => $deal->id,
                'from_stage_id' => $previousStageId,
                'to_stage_id' => $newStageId,
                'reason' => $reason,
                'moved_by' => $user->id,
                'tenant_id' => $tenantId,
            ]);
        });
        
        // Load relationships efficiently (only what's needed)
        $deal->load(['pipeline:id,name', 'stage:id,name,color', 'owner:id,name,email']);
        
        // Get latest movement for response
        $latestMovement = $deal->latestStageMovement()
            ->with(['fromStage:id,name', 'toStage:id,name', 'movedBy:id,name'])
            ->first();
        
        return response()->json([
            'data' => new DealResource($deal),
            'stage_movement' => $latestMovement ? [
                'from_stage' => $latestMovement->fromStage?->name,
                'to_stage' => $latestMovement->toStage->name,
                'reason' => $latestMovement->reason,
                'moved_by' => $latestMovement->movedBy->name,
                'moved_at' => $latestMovement->created_at->toISOString(),
            ] : null,
            'message' => 'Deal moved successfully',
        ]);
    }

    /**
     * Get stage movement history for a deal.
     */
    public function stageHistory(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        
        if (!$tenantId) {
            Log::error('DealsController: User has no tenant_id', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            abort(403, 'Invalid tenant access');
        }
        
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorize('view', $deal);
        
        $perPage = min($request->get('per_page', 10), 50); // Default 10, max 50
        
        $history = $deal->stageHistory()
            ->with(['fromStage:id,name,color', 'toStage:id,name,color', 'movedBy:id,name,email'])
            ->paginate($perPage);
        
        return response()->json([
            'data' => DealStageHistoryResource::collection($history->items()),
            'pagination' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Deal::class);

        // Get tenant_id from authenticated user - ensure proper tenant isolation
        $user = $request->user();
        $tenantId = $user->tenant_id;
        
        // Validate tenant_id exists
        if (!$tenantId) {
            Log::error('DealsController: User has no tenant_id', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            abort(403, 'Invalid tenant access');
        }
        
        $userId = $user->id;
        $range = $request->query('range', '7d');

        $query = Deal::query()->where('tenant_id', $tenantId);

        // Apply owner-based filtering only if user is not admin
        // Admin users should see all deals in their tenant
        if (!$user->hasRole('admin')) {
            $query->where('owner_id', $userId);
        }

        // Calculate date range
        $endDate = now();
        switch ($range) {
            case '7d':
                $startDate = now()->subDays(7);
                break;
            case '30d':
                $startDate = now()->subDays(30);
                break;
            case '90d':
                $startDate = now()->subDays(90);
                break;
            default:
                $startDate = now()->subDays(7);
        }

        $query->whereBetween('created_at', [$startDate, $endDate]);

        $summary = [
            'total_deals' => $query->count(),
            'open_deals' => $query->where('status', 'open')->count(),
            'won_deals' => $query->where('status', 'won')->count(),
            'lost_deals' => $query->where('status', 'lost')->count(),
            'total_value' => $query->sum('value'),
            'won_value' => $query->where('status', 'won')->sum('value'),
            'avg_deal_size' => $query->avg('value'),
            'conversion_rate' => $query->count() > 0 ? 
                round(($query->where('status', 'won')->count() / $query->count()) * 100, 2) : 0,
        ];

        return response()->json([
            'data' => $summary,
            'meta' => [
                'range' => $range,
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString(),
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $this->authorize('viewAny', Deal::class);

        // Get tenant_id from authenticated user - ensure proper tenant isolation
        $user = $request->user();
        $tenantId = $user->tenant_id;
        
        // Validate tenant_id exists
        if (!$tenantId) {
            Log::error('DealsController: User has no tenant_id', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            abort(403, 'Invalid tenant access');
        }
        
        $userId = $user->id;

        $query = Deal::query()->where('tenant_id', $tenantId);

        // Apply owner-based filtering only if user is not admin
        // Admin users should see all deals in their tenant
        if (!$user->hasRole('admin')) {
            $query->where('owner_id', $userId);
        }

        // Apply filters
        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        }
        if ($pipelineId = $request->query('pipeline_id')) {
            $query->where('pipeline_id', $pipelineId);
        }
        if ($stageId = $request->query('stage_id')) {
            $query->where('stage_id', $stageId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('created_from') ?: $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('created_to') ?: $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }
        if ($valueMin = $request->query('value_min') ?: $request->query('min_value')) {
            $query->where('value', '>=', $valueMin);
        }
        if ($valueMax = $request->query('value_max') ?: $request->query('max_value')) {
            $query->where('value', '<=', $valueMax);
        }

        $deals = $query->with(['pipeline', 'stage', 'owner', 'contact', 'company'])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'deals_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($deals) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Title', 'Value', 'Currency', 'Status', 'Probability (%)',
                'Pipeline', 'Stage', 'Owner', 'Contact', 'Company',
                'Expected Close Date', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($deals as $deal) {
                fputcsv($file, [
                    $deal->id,
                    $deal->title,
                    $deal->value,
                    $deal->currency,
                    $deal->status,
                    $deal->probability,
                    $deal->pipeline?->name ?? '',
                    $deal->stage?->name ?? '',
                    $deal->owner?->name ?? '',
                    $deal->contact?->name ?? '',
                    $deal->company?->name ?? '',
                    $deal->expected_close_date,
                    $deal->created_at?->format('Y-m-d H:i:s'),
                    $deal->updated_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Clear deals cache for immediate data visibility.
     */
    private function clearDealsCache(int $tenantId, int $userId): void
    {
        try {
            // Clear deals list cache patterns
            $cachePatterns = [
                "deals_list_{$tenantId}_{$userId}_*",
                "deals_summary_{$tenantId}_{$userId}_*",
            ];

            // Since Laravel doesn't support wildcard cache clearing by default,
            // we'll clear the most common cache keys
            $commonParams = [
                '', // No additional params
                md5(serialize(['sort' => '-created_at', 'page' => 1, 'per_page' => 15])),
                md5(serialize(['sort' => '-updated_at', 'page' => 1, 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                $cacheKey = "deals_list_{$tenantId}_{$userId}_" . $params;
                Cache::forget($cacheKey);
            }

            // Clear summary cache
            Cache::forget("deals_summary_{$tenantId}_{$userId}_7d");
            Cache::forget("deals_summary_{$tenantId}_{$userId}_30d");
            Cache::forget("deals_summary_{$tenantId}_{$userId}_90d");

            Log::info('Deals cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams) + 3
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear deals cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
