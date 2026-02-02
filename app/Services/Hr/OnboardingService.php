<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\Employee;
use App\Models\Hr\OnboardingChecklist;
use App\Models\Hr\OnboardingChecklistTemplate;
use App\Models\Hr\OnboardingTask;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingService
{
    protected HrAuditService $auditService;

    public function __construct(HrAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Initialize onboarding for an employee.
     * Creates checklist items and tasks from templates.
     */
    public function initializeOnboarding(Employee $employee): void
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            // Ensure default templates exist for this tenant
            $this->ensureDefaultTemplatesExist($tenantId, $currentUser->id);

            // Get active templates for this tenant
            $templates = OnboardingChecklistTemplate::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('order', 'asc')
                ->get();

            // Create checklist items from templates
            foreach ($templates as $template) {
                $this->createChecklistItem($employee, $template);
            }

            // Create default tasks based on employee role/department
            $this->createDefaultTasks($employee);

            // Log audit
            $this->auditService->logEmployeeUpdated($employee->id, [], [
                'onboarding_initialized' => true,
                'checklist_items_created' => $templates->count(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to initialize onboarding', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Ensure default templates exist for a tenant.
     * Creates default templates if none exist.
     */
    public function ensureDefaultTemplatesExist(int $tenantId, int $createdBy): void
    {
        $existingCount = OnboardingChecklistTemplate::where('tenant_id', $tenantId)->count();

        if ($existingCount > 0) {
            return; // Templates already exist
        }

        $defaultTemplates = [
            [
                'name' => 'Documents Upload',
                'category' => HrConstants::CHECKLIST_CATEGORY_HR,
                'description' => 'Upload required documents: Employment Contract, ID Document, Tax Forms, Emergency Contact Form',
                'is_required' => true,
                'order' => 1,
            ],
            [
                'name' => 'Bank Details',
                'category' => HrConstants::CHECKLIST_CATEGORY_FINANCE,
                'description' => 'Complete bank account details for salary payment',
                'is_required' => true,
                'order' => 2,
            ],
            [
                'name' => 'ID Verification',
                'category' => HrConstants::CHECKLIST_CATEGORY_HR,
                'description' => 'Verify identity document (Passport/National ID)',
                'is_required' => true,
                'order' => 3,
            ],
            [
                'name' => 'IT Setup',
                'category' => HrConstants::CHECKLIST_CATEGORY_IT,
                'description' => 'Complete IT setup: Email account, System access, Equipment assignment',
                'is_required' => true,
                'order' => 4,
            ],
            [
                'name' => 'Policy Acknowledgment',
                'category' => HrConstants::CHECKLIST_CATEGORY_HR,
                'description' => 'Read and acknowledge company policies and procedures',
                'is_required' => true,
                'order' => 5,
            ],
        ];

        foreach ($defaultTemplates as $templateData) {
            OnboardingChecklistTemplate::create([
                'tenant_id' => $tenantId,
                'created_by' => $createdBy,
                'name' => $templateData['name'],
                'category' => $templateData['category'],
                'description' => $templateData['description'],
                'is_required' => $templateData['is_required'],
                'order' => $templateData['order'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Create a checklist item from a template.
     */
    protected function createChecklistItem(Employee $employee, OnboardingChecklistTemplate $template): OnboardingChecklist
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        // Determine assigned user based on category
        $assignedTo = $this->getAssignedUserForCategory($template->category, $employee);

        // Calculate due date (7 days from start date or employee start date)
        $dueDate = $employee->start_date ? $employee->start_date->copy()->addDays(7) : now()->addDays(7);

        return OnboardingChecklist::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'checklist_template_id' => $template->id,
            'status' => HrConstants::CHECKLIST_STATUS_PENDING,
            'assigned_to' => $assignedTo,
            'due_date' => $dueDate,
        ]);
    }

    /**
     * Create default tasks for an employee.
     */
    protected function createDefaultTasks(Employee $employee): void
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $defaultTasks = $this->getDefaultTasksForEmployee($employee);

        foreach ($defaultTasks as $taskData) {
            OnboardingTask::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'title' => $taskData['title'],
                'description' => $taskData['description'] ?? null,
                'task_type' => $taskData['task_type'],
                'assigned_to' => $taskData['assigned_to'] ?? null,
                'status' => HrConstants::TASK_STATUS_PENDING,
                'priority' => $taskData['priority'] ?? HrConstants::TASK_PRIORITY_MEDIUM,
                'due_date' => $taskData['due_date'] ?? ($employee->start_date ? $employee->start_date->copy()->addDays(5) : now()->addDays(5)),
                'created_by' => $currentUser->id,
            ]);
        }
    }

    /**
     * Get default tasks based on employee details.
     */
    protected function getDefaultTasksForEmployee(Employee $employee): array
    {
        $tasks = [];

        // HR Tasks
        $tasks[] = [
            'title' => 'Upload Employment Contract',
            'description' => 'Upload signed employment contract for ' . $employee->full_name,
            'task_type' => HrConstants::TASK_TYPE_HR,
            'assigned_to' => $this->getHrUser($employee->tenant_id),
            'priority' => HrConstants::TASK_PRIORITY_HIGH,
        ];

        $tasks[] = [
            'title' => 'Complete Policy Acknowledgment',
            'description' => 'Employee must acknowledge company policies',
            'task_type' => HrConstants::TASK_TYPE_HR,
            'assigned_to' => $this->getHrUser($employee->tenant_id),
            'priority' => HrConstants::TASK_PRIORITY_MEDIUM,
        ];

        // IT Tasks
        $tasks[] = [
            'title' => 'Setup Email Account',
            'description' => 'Create email account: ' . $employee->work_email,
            'task_type' => HrConstants::TASK_TYPE_IT,
            'assigned_to' => $this->getItUser($employee->tenant_id),
            'priority' => HrConstants::TASK_PRIORITY_HIGH,
        ];

        $tasks[] = [
            'title' => 'Assign Laptop/Equipment',
            'description' => 'Assign necessary equipment for ' . $employee->full_name,
            'task_type' => HrConstants::TASK_TYPE_IT,
            'assigned_to' => $this->getItUser($employee->tenant_id),
            'priority' => HrConstants::TASK_PRIORITY_MEDIUM,
        ];

        // Manager Tasks
        if ($employee->manager_id) {
            $manager = Employee::find($employee->manager_id);
            if ($manager && $manager->user_id) {
                $tasks[] = [
                    'title' => 'Schedule Welcome Meeting',
                    'description' => 'Schedule welcome meeting with ' . $employee->full_name,
                    'task_type' => HrConstants::TASK_TYPE_MANAGER,
                    'assigned_to' => $manager->user_id,
                    'priority' => HrConstants::TASK_PRIORITY_MEDIUM,
                ];
            }
        }

        // Finance Tasks
        $tasks[] = [
            'title' => 'Complete Bank Details',
            'description' => 'Collect and verify bank account details',
            'task_type' => HrConstants::TASK_TYPE_FINANCE,
            'assigned_to' => $this->getFinanceUser($employee->tenant_id),
            'priority' => HrConstants::TASK_PRIORITY_MEDIUM,
        ];

        return $tasks;
    }

    /**
     * Get assigned user for a category.
     */
    protected function getAssignedUserForCategory(string $category, Employee $employee): ?int
    {
        return match ($category) {
            HrConstants::CHECKLIST_CATEGORY_HR => $this->getHrUser($employee->tenant_id),
            HrConstants::CHECKLIST_CATEGORY_IT => $this->getItUser($employee->tenant_id),
            HrConstants::CHECKLIST_CATEGORY_FINANCE => $this->getFinanceUser($employee->tenant_id),
            HrConstants::CHECKLIST_CATEGORY_MANAGER => $employee->manager_id ? Employee::find($employee->manager_id)?->user_id : null,
            default => null,
        };
    }

    /**
     * Get HR user for tenant.
     */
    protected function getHrUser(int $tenantId): ?int
    {
        $user = User::where('tenant_id', $tenantId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'hr_admin');
            })
            ->first();

        return $user?->id;
    }

    /**
     * Get IT user for tenant.
     */
    protected function getItUser(int $tenantId): ?int
    {
        // Try to find IT admin, fallback to system admin
        $user = User::where('tenant_id', $tenantId)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['it_admin', 'system_admin']);
            })
            ->first();

        return $user?->id;
    }

    /**
     * Get Finance user for tenant.
     */
    protected function getFinanceUser(int $tenantId): ?int
    {
        $user = User::where('tenant_id', $tenantId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'finance');
            })
            ->first();

        return $user?->id;
    }

    /**
     * Complete a checklist item.
     */
    public function completeChecklistItem(OnboardingChecklist $checklist, ?string $notes = null, ?array $metadata = null): OnboardingChecklist
    {
        $currentUser = Auth::user();

        DB::beginTransaction();
        try {
            $checklist->markAsCompleted($currentUser->id, $notes);

            if ($metadata) {
                $checklist->update(['metadata' => array_merge($checklist->metadata ?? [], $metadata)]);
            }

            // Log audit
            $checklist->load('template');
            $this->auditService->logEmployeeUpdated($checklist->employee_id, [], [
                'checklist_item_completed' => $checklist->template?->name ?? 'Unknown',
                'completed_by' => $currentUser->id,
            ]);

            DB::commit();
            return $checklist->fresh(['template', 'completedByUser']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete checklist item', [
                'checklist_id' => $checklist->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Complete an onboarding task.
     */
    public function completeTask(OnboardingTask $task, ?string $notes = null): OnboardingTask
    {
        $currentUser = Auth::user();

        DB::beginTransaction();
        try {
            $task->markAsCompleted($currentUser->id, $notes);

            // Log audit
            $this->auditService->logEmployeeUpdated($task->employee_id, [], [
                'task_completed' => $task->title,
                'completed_by' => $currentUser->id,
            ]);

            DB::commit();
            return $task->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete task', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get onboarding progress for an employee.
     */
    public function getOnboardingProgress(Employee $employee): array
    {
        $checklists = $employee->onboardingChecklists()->with('template')->get();
        $tasks = $employee->onboardingTasks;

        $totalChecklists = $checklists->count();
        $completedChecklists = $checklists->where('status', HrConstants::CHECKLIST_STATUS_COMPLETED)->count();
        $requiredChecklists = $checklists->filter(function ($checklist) {
            return $checklist->template && $checklist->template->is_required;
        })->count();
        $completedRequiredChecklists = $checklists->filter(function ($checklist) {
            return $checklist->status === HrConstants::CHECKLIST_STATUS_COMPLETED &&
                   $checklist->template &&
                   $checklist->template->is_required;
        })->count();

        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', HrConstants::TASK_STATUS_COMPLETED)->count();

        $totalItems = $totalChecklists + $totalTasks;
        $completedItems = $completedChecklists + $completedTasks;

        $progressPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 2) : 0;

        // Check if onboarding can be completed
        $canComplete = $completedRequiredChecklists === $requiredChecklists && $requiredChecklists > 0;

        return [
            'progress_percentage' => $progressPercentage,
            'total_items' => $totalItems,
            'completed_items' => $completedItems,
            'checklists' => [
                'total' => $totalChecklists,
                'completed' => $completedChecklists,
                'required' => $requiredChecklists,
                'completed_required' => $completedRequiredChecklists,
            ],
            'tasks' => [
                'total' => $totalTasks,
                'completed' => $completedTasks,
            ],
            'can_complete' => $canComplete,
        ];
    }

    /**
     * Complete onboarding and activate employee.
     */
    public function completeOnboarding(Employee $employee): Employee
    {
        $progress = $this->getOnboardingProgress($employee);

        if (!$progress['can_complete']) {
            throw new \Exception(HrConstants::ERROR_ONBOARDING_NOT_COMPLETE);
        }

        if ($employee->employment_status !== HrConstants::STATUS_ONBOARDING) {
            throw new \Exception('Employee is not in onboarding status');
        }

        DB::beginTransaction();
        try {
            $oldStatus = $employee->employment_status;

            $employee->update([
                'employment_status' => HrConstants::STATUS_ACTIVE,
            ]);

            // Log audit
            $this->auditService->logEmployeeUpdated($employee->id, [
                'employment_status' => $oldStatus,
            ], [
                'employment_status' => HrConstants::STATUS_ACTIVE,
                'onboarding_completed' => true,
            ]);

            DB::commit();
            return $employee->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete onboarding', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get tasks assigned to current user.
     */
    public function getMyTasks(array $filters = [], int $perPage = 15)
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $query = OnboardingTask::where('tenant_id', $tenantId)
            ->where('assigned_to', $currentUser->id)
            ->with(['employee', 'assignedUser', 'completedByUser']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by task type
        if (!empty($filters['task_type'])) {
            $query->where('task_type', $filters['task_type']);
        }

        // Filter by priority
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Filter by employee
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'due_date';
        $sortOrder = $filters['sortOrder'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get all onboarding employees.
     */
    public function getOnboardingEmployees(array $filters = [], int $perPage = 15)
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $query = Employee::where('tenant_id', $tenantId)
            ->where('employment_status', HrConstants::STATUS_ONBOARDING)
            ->with(['department', 'designation', 'manager', 'team']);

        // Apply role-based filtering (team isolation)
        // HR Admin, System Admin, and Tenant Admin see all employees
        if (!$currentUser->hasRole('hr_admin') && 
            !$currentUser->hasRole('system_admin') && 
            !$currentUser->hasRole('admin')) {
            
            // Line Manager sees only team members
            if ($currentUser->hasRole('line_manager')) {
                if ($currentUser->team_id) {
                    $query->where('team_id', $currentUser->team_id);
                } else {
                    // If line manager has no team, return empty (no team members)
                    $query->whereRaw('1 = 0');
                }
            }
            
            // Employee sees only themselves
            if ($currentUser->hasRole('employee')) {
                $query->where('user_id', $currentUser->id);
            }
            
            // Finance can see all (read-only) - no additional filter needed
            // If user has no matching role, they won't pass viewAny authorization anyway
        }

        // Filter by department
        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        // Filter by manager
        if (!empty($filters['manager_id'])) {
            $query->where('manager_id', $filters['manager_id']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('work_email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get all onboarding checklist templates for the current tenant.
     */
    public function getTemplates(array $filters = [], int $perPage = 15)
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $query = OnboardingChecklistTemplate::where('tenant_id', $tenantId)
            ->with('creator');

        // Filter by category
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Filter by is_required
        if (isset($filters['is_required'])) {
            $isRequired = is_bool($filters['is_required'])
                ? $filters['is_required']
                : filter_var($filters['is_required'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_required', $isRequired);
        }

        // Filter by is_active
        if (isset($filters['is_active'])) {
            $isActive = is_bool($filters['is_active'])
                ? $filters['is_active']
                : filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'order';
        $sortOrder = $filters['sortOrder'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Create a new onboarding checklist template.
     */
    public function createTemplate(array $data): OnboardingChecklistTemplate
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            $template = OnboardingChecklistTemplate::create([
                'tenant_id' => $tenantId,
                'created_by' => $currentUser->id,
                'name' => $data['name'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'is_required' => $data['is_required'] ?? true,
                'order' => $data['order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            DB::commit();
            return $template->load('creator');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create onboarding template', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update an onboarding checklist template.
     */
    public function updateTemplate(OnboardingChecklistTemplate $template, array $data): OnboardingChecklistTemplate
    {
        DB::beginTransaction();
        try {
            $template->update($data);

            DB::commit();
            return $template->fresh()->load('creator');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update onboarding template', [
                'template_id' => $template->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete an onboarding checklist template.
     */
    public function deleteTemplate(OnboardingChecklistTemplate $template): bool
    {
        DB::beginTransaction();
        try {
            // Check if template is being used by any active checklists
            $activeChecklists = OnboardingChecklist::where('checklist_template_id', $template->id)
                ->where('status', '!=', HrConstants::CHECKLIST_STATUS_COMPLETED)
                ->count();

            if ($activeChecklists > 0) {
                throw new \Exception('Cannot delete template. It is being used by ' . $activeChecklists . ' active checklist item(s).');
            }

            $template->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete onboarding template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

