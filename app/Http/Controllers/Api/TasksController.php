<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TasksController extends Controller
{
    public function __construct(
        private TeamAccessService $teamAccessService
    ) {}
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

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

        $query = Task::query()->where('tenant_id', $tenantId);

        // Apply owner/assignee-based filtering (tasks are owner/assignee-specific)
        $query->where(function ($q) use ($userId) {
            $q->where('owner_id', $userId)->orWhere('assigned_to', $userId);
        });
        
        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        // Search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Priority filter
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        // Status filter with overdue handling
        if ($status = $request->query('status')) {
            if ($status === 'overdue') {
                $query->where('status', '!=', 'completed')
                      ->where('due_date', '<', now());
            } else {
                $query->where('status', $status);
            }
        }

        // Assignee filter
        if ($assignedTo = $request->query('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        // Owner filter
        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        }

        // Related entity filters
        if ($relatedType = $request->query('related_type')) {
            $query->where('related_type', $relatedType);
        }
        if ($relatedId = $request->query('related_id')) {
            $query->where('related_id', $relatedId);
        }

        // Date range filters
        if ($from = $request->query('due_from')) {
            $query->whereDate('due_date', '>=', $from);
        }
        if ($to = $request->query('due_to')) {
            $query->whereDate('due_date', '<=', $to);
        }

        // Safe sort mapping
        $sort = (string) $request->query('sort', '-due_date');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        
        // Whitelist allowed columns
        $allowedColumns = ['due_date', 'created_at', 'updated_at', 'title', 'priority'];
        if (!in_array($column, $allowedColumns)) {
            $column = 'due_date';
            $direction = 'desc';
        }
        
        $query->orderBy($column, $direction);

        $perPage = min((int) $request->query('per_page', 15), 100);
        
        // Create cache key for this specific query
        $cacheKey = "tasks_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        $tasks = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->with(['owner', 'assignee', 'related'])->paginate($perPage);
        });

        // Debug logging (only in debug mode)
        if (config('app.debug')) {
            Log::debug('Tasks index query', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'filters' => $request->all(),
                'sort' => "{$column} {$direction}",
                'total_found' => $tasks->total()
            ]);
        }

        return response()->json([
            'data' => TaskResource::collection($tasks->items()),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'from' => $tasks->firstItem(),
                'to' => $tasks->lastItem(),
            ],
        ]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

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
        
        // Auto-assign owner_id if not provided or empty
        if (empty($data['owner_id'])) {
            $data['owner_id'] = $request->user()->id;
        }
        
        // Map assignee_id to assigned_to if provided
        if (isset($data['assignee_id'])) {
            $data['assigned_to'] = $data['assignee_id'];
            unset($data['assignee_id']);
        }
        
        // Ensure related_type and related_id are set to null if not provided
        if (!isset($data['related_type'])) {
            $data['related_type'] = null;
        }
        if (!isset($data['related_id'])) {
            $data['related_id'] = null;
        }

        $task = Task::create($data);

        return response()->json([
            'data' => new TaskResource($task->load(['owner', 'assignee', 'related'])),
        ], 201);
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
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $task);

        return response()->json([
            'data' => new TaskResource($task->load(['owner', 'assignee', 'related'])),
        ]);
    }

    public function update(UpdateTaskRequest $request, int $id): JsonResponse
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
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $task);

        $data = $request->validated();
        
        // Auto-assign owner_id if not provided or empty
        if (empty($data['owner_id'])) {
            $data['owner_id'] = $request->user()->id;
        }
        
        // Map assignee_id to assigned_to if provided
        if (isset($data['assignee_id'])) {
            $data['assigned_to'] = $data['assignee_id'];
            unset($data['assignee_id']);
        }
        
        // Ensure related_type and related_id are set to null if not provided
        if (!isset($data['related_type'])) {
            $data['related_type'] = null;
        }
        if (!isset($data['related_id'])) {
            $data['related_id'] = null;
        }

        $task->update($data);

        return response()->json([
            'data' => new TaskResource($task->load(['owner', 'assignee', 'related'])),
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
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function complete(Request $request, int $id): JsonResponse
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
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('complete', $task);

        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'data' => new TaskResource($task->load(['owner', 'assignee', 'related'])),
            'message' => 'Task completed successfully',
        ]);
    }

    public function assigneeTasks(Request $request, int $assigneeId): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }

        $query = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('assigned_to', $assigneeId);

        $tasks = $query->with(['owner', 'assignee', 'related'])
            ->orderBy('due_date', 'asc')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json([
            'data' => TaskResource::collection($tasks->items()),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'assignee_id' => $assigneeId,
            ],
        ]);
    }

    public function ownerTasks(Request $request, int $ownerId): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }

        $query = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('owner_id', $ownerId);

        $tasks = $query->with(['owner', 'assignee', 'related'])
            ->orderBy('due_date', 'asc')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json([
            'data' => TaskResource::collection($tasks->items()),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'owner_id' => $ownerId,
            ],
        ]);
    }

    public function overdue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $userId = $request->user()->id;

        $query = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', now());

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('owner_id', $userId)->orWhere('assigned_to', $userId);
            });
        }

        $tasks = $query->with(['owner', 'assignee', 'related'])
            ->orderBy('due_date', 'asc')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json([
            'data' => TaskResource::collection($tasks->items()),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $userId = $request->user()->id;
        $days = (int) $request->query('days', 7);

        $query = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'completed')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays($days));

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('owner_id', $userId)->orWhere('assigned_to', $userId);
            });
        }

        $tasks = $query->with(['owner', 'assignee', 'related'])
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json([
            'data' => TaskResource::collection($tasks),
            'meta' => [
                'days_ahead' => $days,
                'count' => $tasks->count(),
            ],
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('updateAny', Task::class);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tasks,id',
            'status' => 'sometimes|string|in:pending,in_progress,completed,cancelled',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'due_date' => 'sometimes|date',
            'assigned_to' => 'sometimes|integer|exists:users,id',
        ]);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }

        $updateData = $request->only(['status', 'priority', 'due_date', 'assigned_to']);

        $updated = Task::where('tenant_id', $tenantId)
            ->whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'message' => "Successfully updated {$updated} tasks",
            'updated_count' => $updated,
        ]);
    }

    public function bulkComplete(Request $request): JsonResponse
    {
        $this->authorize('updateAny', Task::class);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:tasks,id',
        ]);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }

        $updated = Task::where('tenant_id', $tenantId)
            ->whereIn('id', $request->ids)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        return response()->json([
            'message' => "Successfully completed {$updated} tasks",
            'completed_count' => $updated,
        ]);
    }
}
