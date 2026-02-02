<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveBalance;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveService
{
    protected HrAuditService $auditService;

    public function __construct(HrAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Get leave balances for an employee or all employees (filtered by role).
     */
    public function getLeaveBalances(?int $employeeId = null): array
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $query = LeaveBalance::query()
            ->with(['employee', 'leaveType']);

        // Role-based filtering
        if ($currentUser->hasRole('line_manager')) {
            $query->whereHas('employee', function ($q) use ($currentUser) {
                $q->where('team_id', $currentUser->team_id);
            });
        } elseif ($currentUser->hasRole('employee')) {
            $query->whereHas('employee', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get leave requests with filters.
     */
    public function getLeaveRequests(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();

        $query = LeaveRequest::query()
            ->with(['employee', 'leaveType', 'approvedBy']);

        // Role-based filtering
        if ($currentUser->hasRole('line_manager')) {
            $query->whereHas('employee', function ($q) use ($currentUser) {
                $q->where('team_id', $currentUser->team_id);
            });
        } elseif ($currentUser->hasRole('employee')) {
            $query->whereHas('employee', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
        }

        // Apply filters
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('start_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('end_date', '<=', $filters['end_date']);
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a leave request.
     */
    public function createLeaveRequest(array $data): LeaveRequest
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            $employee = Employee::findOrFail($data['employee_id']);
            
            // Validate employee access
            if ($currentUser->hasRole('employee') && $employee->user_id !== $currentUser->id) {
                throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
            }

            // Check leave balance
            $leaveBalance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $data['leave_type_id'])
                ->firstOrFail();

            if ($leaveBalance->balance < $data['days_requested']) {
                throw new \Exception(HrConstants::ERROR_LEAVE_BALANCE_INSUFFICIENT);
            }

            // Check for overlapping requests
            $overlapping = LeaveRequest::where('employee_id', $employee->id)
                ->where('status', '!=', HrConstants::LEAVE_STATUS_CANCELLED)
                ->where(function ($q) use ($data) {
                    $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                      ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                      ->orWhere(function ($q2) use ($data) {
                          $q2->where('start_date', '<=', $data['start_date'])
                             ->where('end_date', '>=', $data['end_date']);
                      });
                })
                ->exists();

            if ($overlapping) {
                throw new \Exception(HrConstants::ERROR_LEAVE_OVERLAPPING);
            }

            // Create leave request (auto-approved in MVP)
            $leaveRequest = LeaveRequest::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days_requested' => $data['days_requested'],
                'reason' => $data['reason'] ?? null,
                'status' => HrConstants::LEAVE_STATUS_APPROVED, // Auto-approved in MVP
                'approved_by' => $currentUser->id,
                'approved_at' => now(),
            ]);

            // Deduct from balance
            $leaveBalance->decrement('balance', $data['days_requested']);
            $leaveBalance->increment('used_this_year', $data['days_requested']);

            // Log audit
            $this->auditService->log(
                HrConstants::AUDIT_LEAVE_REQUEST_CREATED,
                'leave_request',
                $leaveRequest->id,
                [],
                ['employee_id' => $employee->id, 'days' => $data['days_requested']]
            );

            DB::commit();
            return $leaveRequest->load(['employee', 'leaveType', 'approvedBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create leave request', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Adjust leave balance (HR Admin only).
     */
    public function adjustLeaveBalance(int $employeeId, int $leaveTypeId, string $type, float $amount, string $reason): LeaveBalance
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            $balance = LeaveBalance::where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->firstOrFail();

            $oldBalance = $balance->balance;

            switch ($type) {
                case 'add':
                    $balance->increment('balance', $amount);
                    $balance->increment('accrued_this_year', $amount);
                    break;
                case 'deduct':
                    if ($balance->balance < $amount) {
                        throw new \Exception(HrConstants::ERROR_LEAVE_BALANCE_INSUFFICIENT);
                    }
                    $balance->decrement('balance', $amount);
                    break;
                case 'set':
                    $balance->update(['balance' => $amount]);
                    break;
                default:
                    throw new \Exception('Invalid adjustment type');
            }

            $balance->refresh();

            // Log audit
            $this->auditService->logLeaveBalanceAdjusted($employeeId, $leaveTypeId, [
                'type' => $type,
                'amount' => $amount,
                'old_balance' => $oldBalance,
                'new_balance' => $balance->balance,
                'reason' => $reason,
            ]);

            DB::commit();
            return $balance->load(['employee', 'leaveType']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to adjust leave balance', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a leave request.
     */
    public function cancelLeaveRequest(LeaveRequest $leaveRequest): LeaveRequest
    {
        DB::beginTransaction();
        try {
            if ($leaveRequest->status === HrConstants::LEAVE_STATUS_CANCELLED) {
                throw new \Exception('Leave request is already cancelled');
            }

            // Restore balance
            $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->first();

            if ($balance) {
                $balance->increment('balance', $leaveRequest->days_requested);
                $balance->decrement('used_this_year', $leaveRequest->days_requested);
            }

            $leaveRequest->update([
                'status' => HrConstants::LEAVE_STATUS_CANCELLED,
            ]);

            // Log audit
            $this->auditService->log(
                HrConstants::AUDIT_LEAVE_REQUEST_CANCELLED,
                'leave_request',
                $leaveRequest->id,
                [],
                ['employee_id' => $leaveRequest->employee_id]
            );

            DB::commit();
            return $leaveRequest->load(['employee', 'leaveType']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get leave types.
     */
    public function getLeaveTypes(): array
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        return LeaveType::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    /**
     * Get leave calendar for manager.
     */
    public function getLeaveCalendar(array $filters = []): array
    {
        $currentUser = Auth::user();
        
        $query = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->where('status', HrConstants::LEAVE_STATUS_APPROVED);

        // Managers see only their team
        if ($currentUser->hasRole('line_manager')) {
            $query->whereHas('employee', function ($q) use ($currentUser) {
                $q->where('team_id', $currentUser->team_id);
            });
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('start_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('end_date', '<=', $filters['end_date']);
        }

        return $query->get()->toArray();
    }
}

