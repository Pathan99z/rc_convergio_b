<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeInductionAssignment;
use App\Models\Hr\InductionContent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InductionService
{
    protected HrAuditService $auditService;

    public function __construct(HrAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Create induction content.
     */
    public function createContent(array $data): InductionContent
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            $content = InductionContent::create([
                'tenant_id' => $tenantId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'content_type' => $data['content_type'] ?? 'document',
                'category' => $data['category'] ?? 'induction',
                'file_url' => $data['file_url'] ?? null,
                'video_url' => $data['video_url'] ?? null,
                'support_documents' => $data['support_documents'] ?? null,
                'target_audience_type' => $data['target_audience_type'] ?? 'all_employees',
                'target_departments' => $data['target_departments'] ?? null,
                'is_mandatory' => $data['is_mandatory'] ?? false,
                'due_date' => $data['due_date'] ?? null,
                'estimated_time' => $data['estimated_time'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'created_by' => $currentUser->id,
            ]);

            DB::commit();
            return $content->load('creator');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create induction content', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Update induction content.
     */
    public function updateContent(InductionContent $content, array $data): InductionContent
    {
        DB::beginTransaction();
        try {
            $content->update([
                'title' => $data['title'] ?? $content->title,
                'description' => $data['description'] ?? $content->description,
                'content_type' => $data['content_type'] ?? $content->content_type,
                'category' => $data['category'] ?? $content->category,
                'file_url' => $data['file_url'] ?? $content->file_url,
                'video_url' => $data['video_url'] ?? $content->video_url,
                'support_documents' => $data['support_documents'] ?? $content->support_documents,
                'target_audience_type' => $data['target_audience_type'] ?? $content->target_audience_type,
                'target_departments' => $data['target_departments'] ?? $content->target_departments,
                'is_mandatory' => $data['is_mandatory'] ?? $content->is_mandatory,
                'due_date' => $data['due_date'] ?? $content->due_date,
                'estimated_time' => $data['estimated_time'] ?? $content->estimated_time,
                'status' => $data['status'] ?? $content->status,
            ]);

            DB::commit();
            return $content->fresh()->load('creator');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update induction content', [
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Publish content and assign to target employees.
     */
    public function publishContent(InductionContent $content): array
    {
        $currentUser = Auth::user();
        $tenantId = $content->tenant_id;

        DB::beginTransaction();
        try {
            // Update content status
            $content->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            // Assign to target employees
            $assignedCount = $this->assignContentToEmployees($content);

            DB::commit();

            return [
                'content' => $content->fresh()->load('creator'),
                'assigned_count' => $assignedCount,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to publish induction content', [
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Assign content to target employees based on target_audience_type.
     */
    public function assignContentToEmployees(InductionContent $content): int
    {
        $tenantId = $content->tenant_id;
        $employees = collect();

        // Determine which employees should receive this content
        if ($content->target_audience_type === 'all_employees') {
            // Assign to ALL active employees (not offboarded, not archived)
            $employees = Employee::where('tenant_id', $tenantId)
                ->where('employment_status', '!=', HrConstants::STATUS_OFFBOARDED)
                ->whereNull('archived_at')
                ->get();
        } elseif ($content->target_audience_type === 'onboarding_only') {
            // Assign to ALL onboarding employees
            $employees = Employee::where('tenant_id', $tenantId)
                ->where('employment_status', HrConstants::STATUS_ONBOARDING)
                ->whereNull('archived_at')
                ->get();
        } elseif ($content->target_audience_type === 'department_specific') {
            // Assign to employees in specified departments
            if ($content->target_departments && is_array($content->target_departments)) {
                $employees = Employee::where('tenant_id', $tenantId)
                    ->whereIn('department_id', $content->target_departments)
                    ->where('employment_status', '!=', HrConstants::STATUS_OFFBOARDED)
                    ->whereNull('archived_at')
                    ->get();
            }
        }

        $assignedCount = 0;

        // Create assignments for each employee
        foreach ($employees as $employee) {
            // Check if already assigned (prevent duplicates)
            $existing = EmployeeInductionAssignment::where('employee_id', $employee->id)
                ->where('induction_content_id', $content->id)
                ->first();

            if (!$existing) {
                EmployeeInductionAssignment::create([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'induction_content_id' => $content->id,
                    'status' => 'pending',
                    'assigned_at' => now(),
                    'due_date' => $content->due_date ?? null,
                ]);
                $assignedCount++;
            }
        }

        return $assignedCount;
    }

    /**
     * Assign all active published contents to a new employee.
     * Called when employee is created with status=onboarding.
     */
    public function assignAllActiveContentsToEmployee(Employee $employee): void
    {
        $tenantId = $employee->tenant_id;

        // Get all published, active contents
        $contents = InductionContent::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get();

        foreach ($contents as $content) {
            // Check if employee matches target audience
            $shouldAssign = false;

            if ($content->target_audience_type === 'all_employees') {
                $shouldAssign = true;
            } elseif ($content->target_audience_type === 'onboarding_only') {
                $shouldAssign = ($employee->employment_status === HrConstants::STATUS_ONBOARDING);
            } elseif ($content->target_audience_type === 'department_specific') {
                if ($content->target_departments && is_array($content->target_departments)) {
                    $shouldAssign = in_array($employee->department_id, $content->target_departments);
                }
            }

            if ($shouldAssign) {
                // Check if already assigned (prevent duplicates)
                $existing = EmployeeInductionAssignment::where('employee_id', $employee->id)
                    ->where('induction_content_id', $content->id)
                    ->first();

                if (!$existing) {
                    EmployeeInductionAssignment::create([
                        'tenant_id' => $tenantId,
                        'employee_id' => $employee->id,
                        'induction_content_id' => $content->id,
                        'status' => 'pending',
                        'assigned_at' => now(),
                        'due_date' => $content->due_date ?? null,
                    ]);
                }
            }
        }
    }

    /**
     * Get employee's induction progress.
     */
    public function getEmployeeInductionProgress(Employee $employee): array
    {
        $assignments = EmployeeInductionAssignment::where('employee_id', $employee->id)
            ->with('content')
            ->get();

        $total = $assignments->count();
        $completed = $assignments->where('status', 'completed')->count();
        $pending = $assignments->where('status', 'pending')->count();
        $inProgress = $assignments->where('status', 'in_progress')->count();
        $overdue = $assignments->where('is_overdue', true)->where('status', '!=', 'completed')->count();

        // Mandatory items
        $mandatory = $assignments->filter(function ($assignment) {
            return $assignment->content && $assignment->content->is_mandatory;
        });
        $mandatoryTotal = $mandatory->count();
        $mandatoryCompleted = $mandatory->filter(function ($assignment) {
            return $assignment->status === 'completed';
        })->count();

        $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
            'percentage' => $percentage,
            'mandatory' => [
                'total' => $mandatoryTotal,
                'completed' => $mandatoryCompleted,
                'all_completed' => ($mandatoryCompleted === $mandatoryTotal && $mandatoryTotal > 0),
            ],
        ];
    }

    /**
     * Mark assignment as started.
     */
    public function markAssignmentAsStarted(EmployeeInductionAssignment $assignment): EmployeeInductionAssignment
    {
        if ($assignment->status === 'pending') {
            $assignment->markAsStarted();
        }

        return $assignment->fresh()->load(['content', 'employee']);
    }

    /**
     * Acknowledge/complete assignment.
     */
    public function acknowledgeAssignment(EmployeeInductionAssignment $assignment, ?string $notes = null): EmployeeInductionAssignment
    {
        $currentUser = Auth::user();
        $employee = $assignment->employee;

        // Verify that the current user is the employee or has permission
        if ($employee->user_id !== $currentUser->id && !$currentUser->hasRole(['hr_admin', 'system_admin', 'admin'])) {
            throw new \Exception('You can only acknowledge your own induction assignments.');
        }

        $assignment->markAsCompleted($currentUser->id, $notes);

        return $assignment->fresh()->load(['content', 'employee', 'acknowledgedByUser']);
    }

    /**
     * Archive content.
     */
    public function archiveContent(InductionContent $content): InductionContent
    {
        $content->update([
            'status' => 'archived',
        ]);

        return $content->fresh();
    }
}

