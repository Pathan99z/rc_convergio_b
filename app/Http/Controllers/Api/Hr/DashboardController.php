<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\Payslip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * HR Admin Dashboard.
     */
    public function admin(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard', Employee::class);

        $tenantId = $request->user()->tenant_id ?? $request->user()->id;

        // Headcount
        $headcount = [
            'total' => Employee::where('tenant_id', $tenantId)->count(),
            'active' => Employee::where('tenant_id', $tenantId)
                ->where('employment_status', HrConstants::STATUS_ACTIVE)
                ->count(),
            'onboarding' => Employee::where('tenant_id', $tenantId)
                ->where('employment_status', HrConstants::STATUS_ONBOARDING)
                ->count(),
            'offboarded_this_month' => Employee::where('tenant_id', $tenantId)
                ->where('employment_status', HrConstants::STATUS_OFFBOARDED)
                ->whereMonth('archived_at', now()->month)
                ->whereYear('archived_at', now()->year)
                ->count(),
        ];

        // Leave utilization
        $leaveUtilization = [
            'total_days_used_this_month' => LeaveRequest::where('tenant_id', $tenantId)
                ->where('status', HrConstants::LEAVE_STATUS_APPROVED)
                ->whereMonth('start_date', now()->month)
                ->whereYear('start_date', now()->year)
                ->sum('days_requested'),
            'average_per_employee' => $headcount['active'] > 0
                ? round(LeaveRequest::where('tenant_id', $tenantId)
                    ->where('status', HrConstants::LEAVE_STATUS_APPROVED)
                    ->whereMonth('start_date', now()->month)
                    ->whereYear('start_date', now()->year)
                    ->sum('days_requested') / $headcount['active'], 2)
                : 0,
        ];

        // Recent activity
        $recentActivity = [
            'new_hires' => Employee::where('tenant_id', $tenantId)
                ->where('employment_status', HrConstants::STATUS_ACTIVE)
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($e) => [
                    'id' => $e->id,
                    'employee_id' => $e->employee_id,
                    'name' => $e->full_name,
                    'department' => $e->department,
                    'start_date' => $e->start_date?->toDateString(),
                ]),
            'offboarded' => Employee::where('tenant_id', $tenantId)
                ->where('employment_status', HrConstants::STATUS_OFFBOARDED)
                ->where('archived_at', '>=', now()->subDays(30))
                ->orderBy('archived_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($e) => [
                    'id' => $e->id,
                    'employee_id' => $e->employee_id,
                    'name' => $e->full_name,
                    'archived_at' => $e->archived_at?->toISOString(),
                ]),
            'recent_leave' => LeaveRequest::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subDays(7))
                ->with('employee')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($l) => [
                    'id' => $l->id,
                    'employee' => $l->employee->full_name ?? null,
                    'days' => $l->days_requested,
                    'start_date' => $l->start_date?->toDateString(),
                ]),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'headcount' => $headcount,
                'leave_utilization' => $leaveUtilization,
                'recent_activity' => $recentActivity,
            ],
        ]);
    }

    /**
     * Manager Dashboard.
     */
    public function manager(Request $request): JsonResponse
    {
        $this->authorize('viewManagerDashboard', Employee::class);

        $user = $request->user();
        $teamId = $user->team_id;

        if (!$teamId) {
            return response()->json([
                'success' => true,
                'data' => [
                    'team' => ['size' => 0, 'members' => []],
                    'team_leave' => ['upcoming' => [], 'calendar' => []],
                    'performance_notes' => [],
                ],
            ]);
        }

        // Team overview
        $teamMembers = Employee::where('team_id', $teamId)
            ->where('employment_status', HrConstants::STATUS_ACTIVE)
            ->get();

        // Team leave
        $upcomingLeave = LeaveRequest::whereHas('employee', function ($q) use ($teamId) {
            $q->where('team_id', $teamId);
        })
            ->where('status', HrConstants::LEAVE_STATUS_APPROVED)
            ->where('start_date', '>=', now())
            ->with('employee', 'leaveType')
            ->orderBy('start_date', 'asc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'team' => [
                    'size' => $teamMembers->count(),
                    'members' => $teamMembers->map(fn($e) => [
                        'id' => $e->id,
                        'employee_id' => $e->employee_id,
                        'name' => $e->full_name,
                        'job_title' => $e->job_title,
                    ]),
                ],
                'team_leave' => [
                    'upcoming' => $upcomingLeave->map(fn($l) => [
                        'id' => $l->id,
                        'employee' => $l->employee->full_name ?? null,
                        'leave_type' => $l->leaveType->name ?? null,
                        'start_date' => $l->start_date?->toDateString(),
                        'end_date' => $l->end_date?->toDateString(),
                        'days' => $l->days_requested,
                    ]),
                ],
            ],
        ]);
    }

    /**
     * Employee Dashboard.
     */
    public function employee(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
            return response()->json([
                'success' => true,
                'data' => [
                    'leave_balance' => [],
                    'leave_history' => [],
                    'documents' => [
                        'contract_available' => false,
                        'recent_payslips' => [],
                    ],
                ],
            ]);
        }

        // Leave balance
        $leaveBalances = $employee->leaveBalances()
            ->with('leaveType')
            ->get()
            ->map(fn($b) => [
                'leave_type' => $b->leaveType->name ?? null,
                'balance' => $b->balance,
            ]);

        // Leave history
        $leaveHistory = $employee->leaveRequests()
            ->with('leaveType')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'leave_type' => $l->leaveType->name ?? null,
                'start_date' => $l->start_date?->toDateString(),
                'end_date' => $l->end_date?->toDateString(),
                'days' => $l->days_requested,
                'status' => $l->status,
            ]);

        // Documents
        $contractAvailable = $employee->documents()
            ->where('category', HrConstants::DOC_CATEGORY_CONTRACT)
            ->exists();

        $recentPayslips = $employee->payslips()
            ->orderBy('pay_period_start', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'payslip_number' => $p->payslip_number,
                'pay_period_start' => $p->pay_period_start?->toDateString(),
                'pay_period_end' => $p->pay_period_end?->toDateString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'leave_balance' => $leaveBalances,
                'leave_history' => $leaveHistory,
                'documents' => [
                    'contract_available' => $contractAvailable,
                    'recent_payslips' => $recentPayslips,
                ],
            ],
        ]);
    }
}


