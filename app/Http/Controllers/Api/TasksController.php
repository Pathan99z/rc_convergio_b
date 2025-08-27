<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TasksController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

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

        $query = Task::query()->where('tenant_id', $tenantId);

        // Filter by owner_id or assigned_to to ensure users see relevant tasks
        $query->where(function ($q) use ($userId) {
            $q->where('owner_id', $userId)->orWhere('assigned_to', $userId);
        });

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
        $tasks = $query->with(['owner', 'assignee', 'related'])->paginate($perPage);

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

        $task = Task::create($data);

        return response()->json([
            'data' => new TaskResource($task->load(['owner', 'assignee', 'related'])),
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
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $task);

        return response()->json([
            'data' => new TaskResource($task->load(['owner', 'assignee', 'related'])),
        ]);
    }

    public function update(UpdateTaskRequest $request, int $id): JsonResponse
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

        $task->update($data);

        return response()->json([
            'data' => new TaskResource($task->load(['owner', 'assignee', 'related'])),
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
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function complete(Request $request, int $id): JsonResponse
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
}
