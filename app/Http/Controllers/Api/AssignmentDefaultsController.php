<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignmentDefaults\UpdateAssignmentDefaultRequest;
use App\Http\Resources\AssignmentDefaultResource;
use App\Models\AssignmentDefault;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class AssignmentDefaultsController extends Controller
{
    public function __construct(
        private AssignmentService $assignmentService
    ) {}

    /**
     * Get assignment defaults for the current tenant.
     */
    public function show(Request $request): JsonResource
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $defaults = AssignmentDefault::getForTenant($tenantId);
        $this->authorize('view', $defaults);
        
        $defaults->load(['defaultUser:id,name,email']);

        return new AssignmentDefaultResource($defaults);
    }

    /**
     * Update assignment defaults for the current tenant.
     */
    public function update(UpdateAssignmentDefaultRequest $request): JsonResource|JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $defaults = AssignmentDefault::getForTenant($tenantId);
        $this->authorize('update', $defaults);
        $data = $request->validated();

        // Validate that the default user belongs to the same tenant
        if (isset($data['default_user_id'])) {
            $user = User::where('id', $data['default_user_id'])
                       ->where('tenant_id', $tenantId)
                       ->first();
            
            if (!$user) {
                return response()->json([
                    'message' => 'The selected default user does not belong to your organization.',
                    'errors' => ['default_user_id' => ['The selected default user is invalid.']]
                ], 422);
            }
        }

        $defaults->update($data);

        Log::info('Assignment defaults updated', [
            'tenant_id' => $tenantId,
            'updated_by' => $request->user()->id,
            'changes' => $data
        ]);

        return new AssignmentDefaultResource($defaults->load('defaultUser'));
    }

    /**
     * Get available users for default assignment.
     */
    public function users(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $defaults = AssignmentDefault::getForTenant($tenantId);
        $this->authorize('view', $defaults);
        
        $users = User::where('tenant_id', $tenantId)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $users
        ]);
    }

    /**
     * Reset round-robin counters for the current tenant.
     */
    public function resetCounters(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $defaults = AssignmentDefault::getForTenant($tenantId);
        $this->authorize('update', $defaults);
        
        $this->assignmentService->resetRoundRobinCounters($tenantId);

        Log::info('Round-robin counters reset', [
            'tenant_id' => $tenantId,
            'reset_by' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Round-robin counters have been reset successfully.'
        ]);
    }

    /**
     * Toggle automatic assignment for the current tenant.
     */
    public function toggleAutomaticAssignment(Request $request): JsonResource
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $defaults = AssignmentDefault::getForTenant($tenantId);
        $this->authorize('update', $defaults);
        
        $defaults = AssignmentDefault::getForTenant($tenantId);
        $defaults->update(['enable_automatic_assignment' => !$defaults->enable_automatic_assignment]);

        Log::info('Automatic assignment toggled', [
            'tenant_id' => $tenantId,
            'enabled' => $defaults->enable_automatic_assignment,
            'updated_by' => $request->user()->id
        ]);

        return new AssignmentDefaultResource($defaults->load('defaultUser'));
    }

    /**
     * Get assignment statistics for the current tenant.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $defaults = AssignmentDefault::getForTenant($tenantId);
        $this->authorize('view', $defaults);
        
        $filters = [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'record_type' => $request->get('record_type'),
        ];

        $stats = $this->assignmentService->getAssignmentStats($tenantId, $filters);

        return response()->json([
            'data' => $stats,
            'meta' => [
                'tenant_id' => $tenantId,
                'filters' => array_filter($filters)
            ]
        ]);
    }
}
