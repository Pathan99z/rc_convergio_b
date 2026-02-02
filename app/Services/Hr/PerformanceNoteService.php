<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\Employee;
use App\Models\Hr\PerformanceNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceNoteService
{
    protected HrAuditService $auditService;

    public function __construct(HrAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Get performance notes for an employee.
     */
    public function getPerformanceNotes(int $employeeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();

        $query = PerformanceNote::query()
            ->where('employee_id', $employeeId)
            ->with(['employee', 'creator']);

        // Apply visibility filter based on role
        if ($currentUser->hasRole('employee')) {
            // Employees can only see notes with visibility 'employee'
            $query->where('visibility', HrConstants::VISIBILITY_EMPLOYEE);
        } elseif ($currentUser->hasRole('line_manager')) {
            // Managers can see 'manager' and 'employee' visibility
            $query->whereIn('visibility', [
                HrConstants::VISIBILITY_MANAGER,
                HrConstants::VISIBILITY_EMPLOYEE
            ]);
        }
        // HR Admin can see all (no filter)

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a performance note.
     */
    public function createPerformanceNote(int $employeeId, array $data): PerformanceNote
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            $employee = Employee::findOrFail($employeeId);

            // Validate access
            if ($currentUser->hasRole('employee')) {
                throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
            }

            // Managers can only create notes with 'manager' or 'employee' visibility
            if ($currentUser->hasRole('line_manager')) {
                if ($data['visibility'] === HrConstants::VISIBILITY_HR_ONLY) {
                    throw new \Exception('Managers cannot create HR-only notes');
                }
            }

            $note = PerformanceNote::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'note' => $data['note'],
                'visibility' => $data['visibility'],
                'created_by' => $currentUser->id,
            ]);

            // Log audit
            $this->auditService->log(
                HrConstants::AUDIT_PERFORMANCE_NOTE_CREATED,
                'performance_note',
                $note->id,
                [],
                ['employee_id' => $employeeId, 'visibility' => $data['visibility']]
            );

            DB::commit();
            return $note->load(['employee', 'creator']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create performance note', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update a performance note.
     */
    public function updatePerformanceNote(PerformanceNote $note, array $data): PerformanceNote
    {
        $currentUser = Auth::user();

        DB::beginTransaction();
        try {
            // Only creator, HR Admin, or Tenant Admin can update
            if ($note->created_by !== $currentUser->id && 
                !$currentUser->hasRole('hr_admin') && 
                !$currentUser->hasRole('admin') && 
                !$currentUser->hasRole('system_admin')) {
                throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
            }

            $oldValues = $note->toArray();
            $note->update($data);
            $note->refresh();

            // Log audit
            $this->auditService->log(
                HrConstants::AUDIT_PERFORMANCE_NOTE_UPDATED,
                'performance_note',
                $note->id,
                $oldValues,
                $note->toArray()
            );

            DB::commit();
            return $note->load(['employee', 'creator']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a performance note.
     */
    public function deletePerformanceNote(PerformanceNote $note): bool
    {
        $currentUser = Auth::user();

        DB::beginTransaction();
        try {
            // Only creator, HR Admin, or Tenant Admin can delete
            if ($note->created_by !== $currentUser->id && 
                !$currentUser->hasRole('hr_admin') && 
                !$currentUser->hasRole('admin') && 
                !$currentUser->hasRole('system_admin')) {
                throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
            }

            $noteData = $note->toArray();
            $note->delete();

            // Log audit
            $this->auditService->log(
                HrConstants::AUDIT_PERFORMANCE_NOTE_DELETED,
                'performance_note',
                $note->id,
                $noteData,
                []
            );

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

