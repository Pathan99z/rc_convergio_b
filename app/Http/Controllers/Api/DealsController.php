<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\StoreDealRequest;
use App\Http\Requests\Deals\UpdateDealRequest;
use App\Http\Requests\Deals\MoveDealRequest;
use App\Http\Resources\DealResource;
use App\Models\Deal;
use App\Services\AssignmentService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $userId = $request->user()->id;

        $query = Deal::query()->where('tenant_id', $tenantId);

        // Apply owner-based filtering (deals are owner-specific)
        $query->where('owner_id', $userId);
        
        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

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
        
        // Debug: Log the query and results
        Log::info('Deals API Debug', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'all_query_params' => $request->all(),
            'query_sql' => $query->toSql(),
            'query_bindings' => $query->getBindings(),
            'total_deals_before_pagination' => $query->count(),
        ]);
        
        $deals = $query->with(['pipeline', 'stage', 'owner', 'contact', 'company'])->paginate($perPage);
        
        Log::info('Deals API Results', [
            'total_deals' => $deals->total(),
            'current_page' => $deals->currentPage(),
            'per_page' => $deals->perPage(),
            'deals_count' => count($deals->items()),
        ]);

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

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
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
        
        // Only set owner_id if not already provided in the request
        if (empty($data['owner_id'])) {
            $data['owner_id'] = $request->user()->id; // Set owner to current user
        }

        $deal = Deal::create($data);

        // Run assignment logic if no owner is set or if we want to override
        if (empty($deal->owner_id) || $request->boolean('run_assignment', false)) {
            try {
                $assignedUserId = $this->assignmentService->assignOwnerForRecord($deal, 'deal', [
                    'tenant_id' => $tenantId,
                    'created_by' => $request->user()->id
                ]);

                if ($assignedUserId && $assignedUserId !== $deal->owner_id) {
                    $this->assignmentService->applyAssignmentToRecord($deal, $assignedUserId);
                    Log::info('Deal assigned via assignment rules', [
                        'deal_id' => $deal->id,
                        'assigned_user_id' => $assignedUserId,
                        'tenant_id' => $tenantId
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to assign deal via assignment rules', [
                    'deal_id' => $deal->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

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
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
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
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
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
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('delete', $deal);

        $deal->delete();

        return response()->json(['message' => 'Deal deleted successfully']);
    }

    public function move(MoveDealRequest $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('move', $deal);

        $deal->update(['stage_id' => $request->validated()['stage_id']]);

        return response()->json([
            'data' => new DealResource($deal->load(['pipeline', 'stage', 'owner', 'contact', 'company'])),
            'message' => 'Deal moved successfully',
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Deal::class);

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $userId = $request->user()->id;
        $range = $request->query('range', '7d');

        $query = Deal::query()->where('tenant_id', $tenantId)->where('owner_id', $userId);

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

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $userId = $request->user()->id;

        $query = Deal::query()->where('tenant_id', $tenantId);

        // Filter by owner_id to ensure users only see their own deals
        $query->where('owner_id', $userId);

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
}
