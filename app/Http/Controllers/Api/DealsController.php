<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\StoreDealRequest;
use App\Http\Requests\Deals\UpdateDealRequest;
use App\Http\Requests\Deals\MoveDealRequest;
use App\Http\Resources\DealResource;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class DealsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Deal::class);

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = (int) $request->header('X-Tenant-ID');
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
        $tenantId = (int) $request->header('X-Tenant-ID');
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
        $data['owner_id'] = $request->user()->id; // Set owner to current user

        $deal = Deal::create($data);

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
        $tenantId = (int) $request->header('X-Tenant-ID');
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

        return response()->json([
            'data' => new DealResource($deal->load(['pipeline', 'stage', 'owner', 'contact', 'company'])),
        ]);
    }

    public function update(UpdateDealRequest $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = (int) $request->header('X-Tenant-ID');
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
        $tenantId = (int) $request->header('X-Tenant-ID');
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
        $tenantId = (int) $request->header('X-Tenant-ID');
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
        $tenantId = (int) $request->header('X-Tenant-ID');
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
}
