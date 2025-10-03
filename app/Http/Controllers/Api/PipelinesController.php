<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pipelines\StorePipelineRequest;
use App\Http\Requests\Pipelines\UpdatePipelineRequest;
use App\Http\Resources\PipelineResource;
use App\Http\Resources\StageResource;
use App\Models\Pipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PipelinesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Pipeline::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

        $query = Pipeline::query()->where('tenant_id', $tenantId);

        if ($isActive = $request->query('is_active')) {
            $query->where('is_active', $isActive === 'true');
        }

        $sort = (string) $request->query('sort', 'sort_order');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column, $direction);

        $perPage = min((int) $request->query('per_page', 15), 100);
        $pipelines = $query->with(['stages'])->paginate($perPage);

        return response()->json([
            'data' => PipelineResource::collection($pipelines->items()),
            'meta' => [
                'current_page' => $pipelines->currentPage(),
                'last_page' => $pipelines->lastPage(),
                'per_page' => $pipelines->perPage(),
                'total' => $pipelines->total(),
                'from' => $pipelines->firstItem(),
                'to' => $pipelines->lastItem(),
            ],
        ]);
    }

    public function store(StorePipelineRequest $request): JsonResponse
    {
        $this->authorize('create', Pipeline::class);

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
        // Do not set owner_id; pipelines table may not have this column

        $pipeline = Pipeline::create($data);

        return response()->json([
            'data' => new PipelineResource($pipeline->load(['stages'])),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $pipeline = Pipeline::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $pipeline);

        return response()->json([
            'data' => new PipelineResource($pipeline->load(['stages'])),
        ]);
    }

    public function update(UpdatePipelineRequest $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $pipeline = Pipeline::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $pipeline);

        $pipeline->update($request->validated());

        return response()->json([
            'data' => new PipelineResource($pipeline->load(['stages'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $pipeline = Pipeline::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('delete', $pipeline);

        $pipeline->delete();

        return response()->json(['message' => 'Pipeline deleted successfully']);
    }

    public function stages(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $pipeline = Pipeline::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $pipeline);

        $stages = $pipeline->stages()->orderBy('sort_order')->get();

        return response()->json([
            'data' => StageResource::collection($stages),
        ]);
    }

    public function kanban(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $pipeline = Pipeline::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $pipeline);

        // Load stages with their deals and owner relationship, ordered by sort_order
        $stages = $pipeline->stages()
            ->with(['deals' => function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                      ->with('owner')
                      ->orderBy('created_at', 'desc');
            }])
            ->orderBy('sort_order')
            ->get();

        // Transform the data to match the required format
        $kanbanData = [
            'id' => $pipeline->id,
            'name' => $pipeline->name,
            'stages' => $stages->map(function ($stage) {
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'deals' => $stage->deals->map(function ($deal) {
                        return [
                            'id' => $deal->id,
                            'title' => $deal->title,
                            'owner' => $deal->owner ? [
                                'id' => $deal->owner->id,
                                'name' => $deal->owner->name,
                                'email' => $deal->owner->email,
                            ] : null,
                            'value' => $deal->value,
                            'currency' => $deal->currency,
                            'status' => $deal->status,
                            'expected_close_date' => $deal->expected_close_date,
                            'created_at' => $deal->created_at?->toISOString(),
                            'updated_at' => $deal->updated_at?->toISOString(),
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];

        return response()->json($kanbanData);
    }
}
