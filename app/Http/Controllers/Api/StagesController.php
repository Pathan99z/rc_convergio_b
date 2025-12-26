<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stages\StoreStageRequest;
use App\Http\Requests\Stages\UpdateStageRequest;
use App\Http\Resources\StageResource;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StagesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Stage::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

        $query = Stage::query()->where('tenant_id', $tenantId);

        if ($pipelineId = $request->query('pipeline_id')) {
            $query->where('pipeline_id', $pipelineId);
        }
        if ($isActive = $request->query('is_active')) {
            $query->where('is_active', $isActive === 'true');
        }

        $sort = (string) $request->query('sort', 'sort_order');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column, $direction);

        $perPage = min((int) $request->query('per_page', 15), 100);
        $userId = $request->user()->id;
        
        // Create cache key with tenant and user isolation
        $cacheKey = "stages_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        // Cache stages list for 5 minutes (300 seconds)
        $stages = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->with(['pipeline'])->paginate($perPage);
        });

        return response()->json([
            'data' => StageResource::collection($stages->items()),
            'meta' => [
                'current_page' => $stages->currentPage(),
                'last_page' => $stages->lastPage(),
                'per_page' => $stages->perPage(),
                'total' => $stages->total(),
                'from' => $stages->firstItem(),
                'to' => $stages->lastItem(),
            ],
        ]);
    }

    public function store(StoreStageRequest $request): JsonResponse
    {
        $this->authorize('create', Stage::class);

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

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = $request->user()->id;

        $stage = Stage::create($data);

        // Clear cache after creating stage
        $this->clearStagesCache($tenantId, $request->user()->id);

        return response()->json([
            'data' => new StageResource($stage->load(['pipeline'])),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $stage = Stage::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $stage);

        $userId = $request->user()->id;
        
        // Create cache key with tenant, user, and stage ID isolation
        $cacheKey = "stage_show_{$tenantId}_{$userId}_{$id}";
        
        // Cache stage detail for 15 minutes (900 seconds)
        $stage = Cache::remember($cacheKey, 900, function () use ($tenantId, $id) {
            return Stage::where('tenant_id', $tenantId)->findOrFail($id);
        });

        return response()->json([
            'data' => new StageResource($stage->load(['pipeline'])),
        ]);
    }

    public function update(UpdateStageRequest $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $stage = Stage::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $stage);

        $stage->update($request->validated());

        // Clear cache after updating stage
        $this->clearStagesCache($tenantId, $request->user()->id);
        Cache::forget("stage_show_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'data' => new StageResource($stage->load(['pipeline'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->header('X-Tenant-ID');
        $stage = Stage::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('delete', $stage);

        $userId = $request->user()->id;
        $stageId = $stage->id;

        $stage->delete();

        // Clear cache after deleting stage
        $this->clearStagesCache($tenantId, $userId);
        Cache::forget("stage_show_{$tenantId}_{$userId}_{$stageId}");

        return response()->json(['message' => 'Stage deleted successfully']);
    }

    /**
     * Clear stages cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearStagesCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for stages list
            $commonParams = [
                '',
                md5(serialize(['sort' => 'sort_order', 'per_page' => 15])),
                md5(serialize(['is_active' => true, 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("stages_list_{$tenantId}_{$userId}_{$params}");
            }

            Log::info('Stages cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear stages cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
