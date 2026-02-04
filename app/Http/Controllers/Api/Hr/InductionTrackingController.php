<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeInductionAssignment;
use App\Services\Hr\InductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InductionTrackingController extends Controller
{
    public function __construct(
        private InductionService $inductionService
    ) {}

    /**
     * Get tracking dashboard data.
     * 
     * GET /api/hr/induction/tracking
     * Query params: employee_id, department_id, content_id, status, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $currentUser = $request->user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        // Disable global scope to avoid tenant_id ambiguity in joins
        $query = EmployeeInductionAssignment::withoutGlobalScope('tenant')
            ->where('hr_employee_induction_assignments.tenant_id', $tenantId)
            ->with(['employee.department', 'content.creator', 'acknowledgedByUser'])
            ->join('hr_employees', 'hr_employee_induction_assignments.employee_id', '=', 'hr_employees.id')
            ->select('hr_employee_induction_assignments.*');

        // Filter by employee (only if not empty)
        if ($request->filled('employee_id')) {
            $query->where('hr_employee_induction_assignments.employee_id', $request->query('employee_id'));
        }

        // Filter by department (only if not empty)
        if ($request->filled('department_id')) {
            $query->where('hr_employees.department_id', $request->query('department_id'));
        }

        // Filter by content (only if not empty)
        if ($request->filled('content_id')) {
            $query->where('hr_employee_induction_assignments.induction_content_id', $request->query('content_id'));
        }

        // Filter by status (only if not empty)
        if ($request->filled('status')) {
            $query->where('hr_employee_induction_assignments.status', $request->query('status'));
        }

        // Filter by overdue (only if not empty)
        if ($request->filled('overdue')) {
            $query->where('hr_employee_induction_assignments.is_overdue', $request->boolean('overdue'));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $assignments = $query->orderBy('hr_employee_induction_assignments.assigned_at', 'desc')
            ->paginate($perPage);

        // Format data
        $data = $assignments->getCollection()->map(function ($assignment) {
            return [
                'assignment_id' => $assignment->id,
                'employee_id' => $assignment->employee_id,
                'employee_name' => $assignment->employee->full_name ?? 'N/A',
                'employee_id_code' => $assignment->employee->employee_id ?? 'N/A',
                'department' => $assignment->employee->department->name ?? 'N/A',
                'department_id' => $assignment->employee->department_id ?? null,
                'content_id' => $assignment->induction_content_id,
                'content_title' => $assignment->content->title ?? 'N/A',
                'category' => $assignment->content->category ?? null,
                'status' => $assignment->status,
                'assigned_at' => $assignment->assigned_at,
                'started_at' => $assignment->started_at,
                'completed_at' => $assignment->completed_at,
                'due_date' => $assignment->due_date,
                'is_overdue' => $assignment->is_overdue,
                'is_mandatory' => $assignment->content->is_mandatory ?? false,
            ];
        });

        // Calculate summary
        $allAssignments = EmployeeInductionAssignment::where('tenant_id', $tenantId)->get();
        $summary = [
            'total_assignments' => $allAssignments->count(),
            'completed' => $allAssignments->where('status', 'completed')->count(),
            'pending' => $allAssignments->where('status', 'pending')->count(),
            'in_progress' => $allAssignments->where('status', 'in_progress')->count(),
            'overdue' => $allAssignments->where('is_overdue', true)->where('status', '!=', 'completed')->count(),
        ];
        $summary['completion_rate'] = $summary['total_assignments'] > 0 
            ? round(($summary['completed'] / $summary['total_assignments']) * 100, 2) 
            : 0;

        return response()->json([
            'success' => true,
            'data' => $data,
            'summary' => $summary,
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
        ]);
    }

    /**
     * Get employee's induction progress (HR view).
     * 
     * GET /api/hr/induction/employees/{employeeId}/progress
     */
    public function employeeProgress(int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        $this->authorize('view', $employee);

        $progress = $this->inductionService->getEmployeeInductionProgress($employee);

        $assignments = EmployeeInductionAssignment::where('employee_id', $employeeId)
            ->with(['content.creator', 'acknowledgedByUser'])
            ->orderBy('assigned_at', 'desc')
            ->get();

        $items = $assignments->map(function ($assignment) {
            return [
                'assignment_id' => $assignment->id,
                'content_id' => $assignment->induction_content_id,
                'title' => $assignment->content->title ?? 'N/A',
                'category' => $assignment->content->category ?? null,
                'status' => $assignment->status,
                'assigned_at' => $assignment->assigned_at,
                'started_at' => $assignment->started_at,
                'completed_at' => $assignment->completed_at,
                'due_date' => $assignment->due_date,
                'is_overdue' => $assignment->is_overdue,
                'is_mandatory' => $assignment->content->is_mandatory ?? false,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'employee_id' => $employee->employee_id,
                    'full_name' => $employee->full_name,
                    'department' => $employee->department->name ?? null,
                ],
                'progress' => $progress,
                'items' => $items,
            ],
        ]);
    }

    /**
     * Send reminders to employees.
     * 
     * POST /api/hr/induction/reminders
     * Body: {
     *   assignment_ids: [10, 11, 12], // optional - specific assignments
     *   employee_ids: [5, 6, 7], // optional - all pending for these employees
     *   content_id: 2, // optional - all pending for this content
     *   message: "Custom message" // optional
     * }
     */
    public function sendReminders(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $currentUser = $request->user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'assignment_ids' => 'nullable|array',
            'assignment_ids.*' => 'integer|exists:hr_employee_induction_assignments,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:hr_employees,id',
            'content_id' => 'nullable|integer|exists:hr_induction_contents,id',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $query = EmployeeInductionAssignment::where('tenant_id', $tenantId)
            ->where('status', '!=', 'completed')
            ->with(['employee.user', 'content']);

        // Build query based on filters
        if (!empty($data['assignment_ids'])) {
            $query->whereIn('id', $data['assignment_ids']);
        } elseif (!empty($data['employee_ids'])) {
            $query->whereIn('employee_id', $data['employee_ids']);
        } elseif (!empty($data['content_id'])) {
            $query->where('induction_content_id', $data['content_id']);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Please provide assignment_ids, employee_ids, or content_id',
            ], 422);
        }

        $assignments = $query->get();
        $sentCount = 0;
        $defaultMessage = $data['message'] ?? 'Please complete your mandatory induction content';

        // TODO: Implement actual email/notification sending
        // For now, just log the reminders
        foreach ($assignments as $assignment) {
            if ($assignment->employee->user) {
                // Log reminder sent
                \Illuminate\Support\Facades\Log::info('Induction reminder sent', [
                    'employee_id' => $assignment->employee_id,
                    'assignment_id' => $assignment->id,
                    'content_title' => $assignment->content->title,
                    'message' => $defaultMessage,
                ]);
                $sentCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Reminders sent to {$sentCount} employees",
            'sent_count' => $sentCount,
        ]);
    }
}

