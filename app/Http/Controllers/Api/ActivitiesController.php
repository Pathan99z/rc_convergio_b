<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activities\StoreActivityRequest;
use App\Http\Requests\Activities\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivitiesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

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

        // Log tenant and user info for debugging
        Log::info('Activities index request', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'filters' => $request->query()
        ]);

        $query = Activity::query()->where('tenant_id', $tenantId);

        // Filter by owner_id to ensure users only see their own activities
        // Only override if explicitly requested (for admin functionality)
        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where('owner_id', $userId);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($relatedType = $request->query('related_type')) {
            $query->where('related_type', $relatedType);
        }
        if ($relatedId = $request->query('related_id')) {
            $query->where('related_id', $relatedId);
        }
        if ($from = $request->query('scheduled_from')) {
            $query->where('scheduled_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to = $request->query('scheduled_to')) {
            $query->where('scheduled_at', '<=', Carbon::parse($to)->endOfDay());
        }

        // Handle sorting with whitelist mapping
        $sort = (string) $request->query('sort', 'created_at_desc');
        
        // Define sort mappings
        $sortMappings = [
            'title_asc' => ['subject', 'asc'],
            'title_desc' => ['subject', 'desc'],
            'scheduled_at_asc' => ['scheduled_at', 'asc'],
            'scheduled_at_desc' => ['scheduled_at', 'desc'],
            'created_at_asc' => ['created_at', 'asc'],
            'created_at_desc' => ['created_at', 'desc'],
        ];
        
        // Get the mapped sort or fallback to created_at_desc
        $mappedSort = $sortMappings[$sort] ?? ['created_at', 'desc'];
        $query->orderBy($mappedSort[0], $mappedSort[1]);

        $perPage = min((int) $request->query('per_page', 15), 100);
        $activities = $query->with(['owner', 'related'])->paginate($perPage);

        // Log the query results for debugging
        Log::info('Activities index results:', [
            'total_found' => $activities->total(),
            'current_page' => $activities->currentPage(),
            'per_page' => $activities->perPage(),
            'sql_query' => $query->toSql(),
            'sql_bindings' => $query->getBindings()
        ]);

        return response()->json([
            'data' => ActivityResource::collection($activities->items()),
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

    public function store(StoreActivityRequest $request): JsonResponse
    {
        $this->authorize('create', Activity::class);

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

        $data = $request->validated();
        
        // ALWAYS enforce tenant_id and owner_id for consistency
        $data['tenant_id'] = $tenantId;
        
        // Auto-assign owner_id if not provided or empty
        if (empty($data['owner_id'])) {
            $data['owner_id'] = $request->user()->id;
        }

        // Ensure related fields are null if not provided
        if (!isset($data['related_type']) || empty($data['related_type'])) {
            $data['related_type'] = null;
            $data['related_id'] = null;
        }

        // Log the data being created for debugging
        Log::info('Creating activity with data:', [
            'tenant_id' => $data['tenant_id'],
            'owner_id' => $data['owner_id'],
            'subject' => $data['subject'] ?? 'N/A',
            'type' => $data['type'] ?? 'N/A',
            'related_type' => $data['related_type'],
            'related_id' => $data['related_id']
        ]);

        $activity = Activity::create($data);

        return response()->json([
            'data' => new ActivityResource($activity->load(['owner', 'related'])),
        ], 201);
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
        $activity = Activity::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $activity);

        return response()->json([
            'data' => new ActivityResource($activity->load(['owner', 'related'])),
        ]);
    }

    public function update(UpdateActivityRequest $request, int $id): JsonResponse
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
        $activity = Activity::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $activity);

        $data = $request->validated();
        
        // ALWAYS enforce tenant_id and owner_id for consistency (prevent overwriting)
        $data['tenant_id'] = $tenantId;
        
        // Auto-assign owner_id if not provided or empty
        if (empty($data['owner_id'])) {
            $data['owner_id'] = $request->user()->id;
        }
        
        // Ensure related fields are null if not provided
        if (!isset($data['related_type']) || empty($data['related_type'])) {
            $data['related_type'] = null;
            $data['related_id'] = null;
        }

        // Log the data being updated for debugging
        Log::info('Updating activity with data:', [
            'activity_id' => $id,
            'tenant_id' => $data['tenant_id'],
            'owner_id' => $data['owner_id'],
            'subject' => $data['subject'] ?? 'N/A',
            'type' => $data['type'] ?? 'N/A',
            'related_type' => $data['related_type'],
            'related_id' => $data['related_id']
        ]);

        $activity->update($data);

        return response()->json([
            'data' => new ActivityResource($activity->load(['owner', 'related'])),
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
        $activity = Activity::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('delete', $activity);

        $activity->delete();

        return response()->json(['message' => 'Activity deleted successfully']);
    }
}
