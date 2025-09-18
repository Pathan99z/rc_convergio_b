<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AutomationController extends Controller
{
    /**
     * Get all automations for the authenticated user's tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = Automation::where('tenant_id', $tenantId)
            ->with(['creator:id,name,email']);

        // Filter by status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by trigger event if provided
        if ($request->has('trigger_event')) {
            $query->where('trigger_event', $request->get('trigger_event'));
        }

        // Filter by action if provided
        if ($request->has('action')) {
            $query->where('action', $request->get('action'));
        }

        // Search by name if provided
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->get('search') . '%');
        }

        $automations = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $automations->items(),
            'meta' => [
                'current_page' => $automations->currentPage(),
                'last_page' => $automations->lastPage(),
                'per_page' => $automations->perPage(),
                'total' => $automations->total(),
                'from' => $automations->firstItem(),
                'to' => $automations->lastItem(),
            ],
            'message' => 'Automations retrieved successfully'
        ]);
    }

    /**
     * Create a new automation.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_event' => [
                'required',
                'string',
                Rule::in(array_keys(Automation::getAvailableTriggerEvents()))
            ],
            'delay_minutes' => 'required|integer|min:0|max:10080', // Max 7 days
            'action' => [
                'required',
                'string',
                Rule::in(array_keys(Automation::getAvailableActions()))
            ],
            'metadata' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $automation = Automation::create([
                'name' => $validated['name'],
                'trigger_event' => $validated['trigger_event'],
                'delay_minutes' => $validated['delay_minutes'],
                'action' => $validated['action'],
                'metadata' => $validated['metadata'] ?? [],
                'is_active' => $validated['is_active'] ?? true,
                'tenant_id' => $tenantId,
                'created_by' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'data' => $automation->load(['creator:id,name,email']),
                'message' => 'Automation created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create automation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific automation.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $automation = Automation::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->with(['creator:id,name,email', 'logs' => function ($query) {
                $query->with('contact:id,email,name')
                    ->orderBy('created_at', 'desc')
                    ->limit(10);
            }])
            ->firstOrFail();

        return response()->json([
            'data' => $automation,
            'message' => 'Automation retrieved successfully'
        ]);
    }

    /**
     * Update a specific automation.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $automation = Automation::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'trigger_event' => [
                'sometimes',
                'string',
                Rule::in(array_keys(Automation::getAvailableTriggerEvents()))
            ],
            'delay_minutes' => 'sometimes|integer|min:0|max:10080',
            'action' => [
                'sometimes',
                'string',
                Rule::in(array_keys(Automation::getAvailableActions()))
            ],
            'metadata' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();

            $automation->update($validated);

            DB::commit();

            return response()->json([
                'data' => $automation->load(['creator:id,name,email']),
                'message' => 'Automation updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update automation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a specific automation.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $automation = Automation::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            $automation->delete();

            DB::commit();

            return response()->json([
                'message' => 'Automation deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete automation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available trigger events and actions for dropdowns.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'trigger_events' => Automation::getAvailableTriggerEvents(),
                'actions' => Automation::getAvailableActions(),
            ],
            'message' => 'Automation options retrieved successfully'
        ]);
    }

    /**
     * Get automation execution logs.
     */
    public function logs(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify automation exists and belongs to tenant
        $automation = Automation::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $query = AutomationLog::where('automation_id', $id)
            ->where('tenant_id', $tenantId)
            ->with(['contact:id,email,name']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by date range if provided
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->get('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->get('to_date'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
            'message' => 'Automation logs retrieved successfully'
        ]);
    }
}
