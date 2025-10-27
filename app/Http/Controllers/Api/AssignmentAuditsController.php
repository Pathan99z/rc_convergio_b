<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssignmentAudit;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssignmentAuditsController extends Controller
{
    public function __construct(
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Display a listing of assignment audits.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssignmentAudit::class);

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = AssignmentAudit::where('tenant_id', $tenantId)
            ->with(['assignedUser:id,name,email', 'rule:id,name'])
            ->orderBy('created_at', 'desc');

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        // Filter by record type if provided
        if ($request->has('record_type') && $request->get('record_type') !== 'all') {
            $query->where('record_type', $request->get('record_type'));
        }

        // Filter by assigned user if provided
        if ($request->has('assigned_to') && $request->get('assigned_to') !== 'all') {
            $query->where('assigned_user_id', $request->get('assigned_to'));
        }

        // Filter by rule if provided
        if ($request->has('rule') && $request->get('rule') !== 'all') {
            $query->where('rule_id', $request->get('rule'));
        }

        // Filter by date range if provided
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $perPage = $request->get('per_page', 20);
        $audits = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $audits->items(),
            'pagination' => [
                'current_page' => $audits->currentPage(),
                'last_page' => $audits->lastPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
                'from' => $audits->firstItem(),
                'to' => $audits->lastItem(),
            ]
        ]);
    }

    /**
     * Export assignment audits to CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssignmentAudit::class);

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = AssignmentAudit::where('tenant_id', $tenantId)
            ->with(['assignedUser:id,name,email', 'rule:id,name'])
            ->orderBy('created_at', 'desc');

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        // Apply same filters as index method
        if ($request->has('record_type') && $request->get('record_type') !== 'all') {
            $query->where('record_type', $request->get('record_type'));
        }

        if ($request->has('assigned_to') && $request->get('assigned_to') !== 'all') {
            $query->where('assigned_user_id', $request->get('assigned_to'));
        }

        if ($request->has('rule') && $request->get('rule') !== 'all') {
            $query->where('rule_id', $request->get('rule'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $audits = $query->get();

        // Generate CSV content
        $csvContent = "Date,Record Type,Record ID,Assigned To,Rule,Assignment Type,Context\n";
        
        foreach ($audits as $audit) {
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $audit->created_at->format('Y-m-d H:i:s'),
                $audit->record_type,
                $audit->record_id,
                $audit->assignedUser ? $audit->assignedUser->name : 'N/A',
                $audit->rule ? $audit->rule->name : 'N/A',
                $audit->assignment_type,
                json_encode($audit->context ?? [])
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'csv_content' => base64_encode($csvContent),
                'filename' => 'assignment_audits_' . now()->format('Y-m-d_H-i-s') . '.csv',
                'total_records' => $audits->count()
            ]
        ]);
    }
}


