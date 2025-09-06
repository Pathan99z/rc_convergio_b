<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stages\StoreStageRequest;
use App\Http\Requests\Stages\UpdateStageRequest;
use App\Http\Resources\StageResource;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $stages = $query->with(['pipeline'])->paginate($perPage);

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

        return response()->json([
            'data' => new StageResource($stage->load(['pipeline'])),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $stage = Stage::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $stage);

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

        return response()->json([
            'data' => new StageResource($stage->load(['pipeline'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->header('X-Tenant-ID');
        $stage = Stage::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('delete', $stage);

        $stage->delete();

        return response()->json(['message' => 'Stage deleted successfully']);
    }
}
