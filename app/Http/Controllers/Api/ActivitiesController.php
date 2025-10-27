<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activities\StoreActivityRequest;
use App\Http\Requests\Activities\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Services\TeamAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ActivitiesController extends Controller
{
    public function __construct(
        private TeamAccessService $teamAccessService
    ) {}
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        // Resolve tenant from authenticated user
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $userId = $request->user()->id;

        // Log tenant and user info for debugging
        Log::info('Activities index request', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'filters' => $request->query()
        ]);

        $query = Activity::query()->where('tenant_id', $tenantId);

        // Apply owner-based filtering (activities are owner-specific)
        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where('owner_id', $userId);
        }
        
        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);
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
        
        // Create cache key for this specific query
        $cacheKey = "activities_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        $activities = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->with(['owner', 'related'])->paginate($perPage);
        });

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

        // Resolve tenant from authenticated user
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

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
        // Resolve tenant from authenticated user
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $activity = Activity::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $activity);

        return response()->json([
            'data' => new ActivityResource($activity->load(['owner', 'related'])),
        ]);
    }

    public function update(UpdateActivityRequest $request, int $id): JsonResponse
    {
        // Resolve tenant from authenticated user
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
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

    public function complete(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        
        $activity = Activity::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorize('update', $activity);

        $activity->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'data' => new ActivityResource($activity->load(['owner', 'related'])),
            'message' => 'Activity marked as completed',
        ]);
    }

    public function timeline(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $userId = $request->user()->id;

        $query = Activity::query()->where('tenant_id', $tenantId);

        // Apply filters
        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where('owner_id', $userId);
        }
        if ($relatedType = $request->query('related_type')) {
            $query->where('related_type', $relatedType);
        }
        if ($relatedId = $request->query('related_id')) {
            $query->where('related_id', $relatedId);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $activities = $query->with(['owner', 'related'])
            ->orderBy('scheduled_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->query('per_page', 50), 100));

        return response()->json([
            'data' => ActivityResource::collection($activities->items()),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $userId = $request->user()->id;
        $days = (int) $request->query('days', 7);

        $query = Activity::query()->where('tenant_id', $tenantId);

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where('owner_id', $userId);
        }

        $query->where('status', '!=', 'completed')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addDays($days))
            ->orderBy('scheduled_at', 'asc');

        $activities = $query->with(['owner', 'related'])->get();

        return response()->json([
            'data' => ActivityResource::collection($activities),
            'meta' => [
                'days_ahead' => $days,
                'count' => $activities->count(),
            ],
        ]);
    }

    public function entityActivities(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

        $query = Activity::query()
            ->where('tenant_id', $tenantId)
            ->where('related_type', $entityType)
            ->where('related_id', $entityId);

        $activities = $query->with(['owner', 'related'])
            ->orderBy('scheduled_at', 'desc')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json([
            'data' => ActivityResource::collection($activities->items()),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $userId = $request->user()->id;
        $searchTerm = $request->query('q');

        if (!$searchTerm) {
            return response()->json([
                'data' => [],
                'meta' => ['message' => 'Search term is required'],
            ]);
        }

        $query = Activity::query()->where('tenant_id', $tenantId);

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where('owner_id', $userId);
        }

        $query->where(function($q) use ($searchTerm) {
            $q->where('subject', 'like', "%{$searchTerm}%")
              ->orWhere('notes', 'like', "%{$searchTerm}%")
              ->orWhere('type', 'like', "%{$searchTerm}%");
        });

        $activities = $query->with(['owner', 'related'])
            ->orderBy('scheduled_at', 'desc')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json([
            'data' => ActivityResource::collection($activities->items()),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'search_term' => $searchTerm,
            ],
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('updateAny', Activity::class);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:activities,id',
            'status' => 'sometimes|string|in:pending,in_progress,completed,cancelled',
            'due_date' => 'sometimes|date',
            'owner_id' => 'sometimes|integer|exists:users,id',
        ]);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

        $updateData = $request->only(['status', 'due_date', 'owner_id']);
        if (isset($updateData['due_date'])) {
            $updateData['scheduled_at'] = $updateData['due_date'];
            unset($updateData['due_date']);
        }

        $updated = Activity::where('tenant_id', $tenantId)
            ->whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'message' => "Successfully updated {$updated} activities",
            'updated_count' => $updated,
        ]);
    }

    public function bulkComplete(Request $request): JsonResponse
    {
        $this->authorize('updateAny', Activity::class);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:activities,id',
        ]);

        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

        $updated = Activity::where('tenant_id', $tenantId)
            ->whereIn('id', $request->ids)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        return response()->json([
            'message' => "Successfully completed {$updated} activities",
            'completed_count' => $updated,
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('deleteAny', Activity::class);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:activities,id',
        ]);

        $tenantId = (int) $request->header('X-Tenant-ID');
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }

        $deleted = Activity::where('tenant_id', $tenantId)
            ->whereIn('id', $request->ids)
            ->delete();

        return response()->json([
            'message' => "Successfully deleted {$deleted} activities",
            'deleted_count' => $deleted,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $tenantId = (int) $request->header('X-Tenant-ID');
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $userId = $request->user()->id;

        $query = Activity::query()->where('tenant_id', $tenantId);

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where('owner_id', $userId);
        }

        $stats = $query->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        return response()->json([
            'data' => $stats,
            'meta' => [
                'total_activities' => array_sum($stats),
            ],
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $tenantId = (int) $request->header('X-Tenant-ID');
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $userId = $request->user()->id;
        $period = $request->query('period', 'weekly');

        $query = Activity::query()->where('tenant_id', $tenantId);

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where('owner_id', $userId);
        }

        if ($period === 'weekly') {
            $metrics = $query->select(
                DB::raw('DATE(scheduled_at) as date'),
                DB::raw('count(*) as count')
            )
            ->where('scheduled_at', '>=', now()->subWeeks(4))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        } else {
            $metrics = $query->select(
                DB::raw('YEAR(scheduled_at) as year'),
                DB::raw('MONTH(scheduled_at) as month'),
                DB::raw('count(*) as count')
            )
            ->where('scheduled_at', '>=', now()->subMonths(6))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
        }

        return response()->json([
            'data' => $metrics,
            'meta' => [
                'period' => $period,
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        $tenantId = (int) $request->header('X-Tenant-ID');
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $userId = $request->user()->id;

        $query = Activity::query()->where('tenant_id', $tenantId);

        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->where('owner_id', $userId);
        }

        // Apply filters
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

        $activities = $query->with(['owner', 'related'])
            ->orderBy('scheduled_at', 'desc')
            ->get();

        $filename = 'activities_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($activities) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Subject', 'Type', 'Status', 'Owner', 'Related Entity',
                'Scheduled Date', 'Completed Date', 'Created Date'
            ]);

            // CSV Data
            foreach ($activities as $activity) {
                fputcsv($file, [
                    $activity->id,
                    $activity->subject,
                    $activity->type,
                    $activity->status,
                    $activity->owner?->name ?? '',
                    $activity->related ? ($activity->related_type . ' #' . $activity->related_id) : '',
                    $activity->scheduled_at?->format('Y-m-d H:i:s'),
                    $activity->completed_at?->format('Y-m-d H:i:s'),
                    $activity->created_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
