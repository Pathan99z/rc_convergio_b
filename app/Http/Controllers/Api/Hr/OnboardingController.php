<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\OnboardingChecklist;
use App\Models\Hr\OnboardingChecklistTemplate;
use App\Models\Hr\OnboardingTask;
use App\Services\Hr\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    public function __construct(
        private OnboardingService $onboardingService
    ) {}

    /**
     * Get all employees in onboarding status.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $filters = [
            'search' => $request->query('search'),
            'department_id' => $request->query('department_id'),
            'manager_id' => $request->query('manager_id'),
            'sortBy' => $request->query('sortBy', 'created_at'),
            'sortOrder' => $request->query('sortOrder', 'desc'),
        ];

        $perPage = min((int) $request->query('per_page', 15), 100);
        $employees = $this->onboardingService->getOnboardingEmployees($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $employees->items(),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    /**
     * Initialize onboarding for an employee (if not already initialized).
     */
    public function initialize(int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        
        $this->authorize('update', $employee);
        
        // Check if employee is in onboarding status
        if ($employee->employment_status !== HrConstants::STATUS_ONBOARDING) {
            return response()->json([
                'success' => false,
                'message' => 'Employee is not in onboarding status',
            ], 400);
        }
        
        // Check if onboarding already initialized
        $existingChecklist = OnboardingChecklist::where('employee_id', $employeeId)->exists();
        if ($existingChecklist) {
            return response()->json([
                'success' => false,
                'message' => 'Onboarding already initialized for this employee',
            ], 400);
        }
        
        try {
            $this->onboardingService->initializeOnboarding($employee);
            
            return response()->json([
                'success' => true,
                'message' => 'Onboarding initialized successfully. Checklist items and tasks have been created.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get onboarding details for an employee.
     */
    public function show(int $employeeId): JsonResponse
    {
        $employee = Employee::with([
            'onboardingChecklists.template',
            'onboardingChecklists.assignedUser',
            'onboardingChecklists.completedByUser',
            'onboardingTasks.assignedUser',
            'onboardingTasks.completedByUser',
            'department',
            'designation',
            'manager',
            'team',
        ])->findOrFail($employeeId);

        $this->authorize('view', $employee);

        if ($employee->employment_status !== HrConstants::STATUS_ONBOARDING) {
            return response()->json([
                'success' => false,
                'message' => 'Employee is not in onboarding status',
            ], 400);
        }

        $progress = $this->onboardingService->getOnboardingProgress($employee);

        // Get department and designation names safely
        // Use getRelation() to access the relationship object, not the attribute
        $departmentName = null;
        $designationName = null;
        
        if ($employee->relationLoaded('department')) {
            $department = $employee->getRelation('department');
            $departmentName = $department ? $department->name : ($employee->department ?? null);
        } else {
            $departmentName = $employee->department; // Fallback to string attribute
        }
        
        if ($employee->relationLoaded('designation')) {
            $designation = $employee->getRelation('designation');
            $designationName = $designation ? $designation->name : ($employee->job_title ?? null);
        } else {
            $designationName = $employee->job_title; // Fallback to string attribute
        }

        // Load documents for each checklist item
        $checklists = $employee->onboardingChecklists->map(function ($checklist) use ($employeeId) {
            $metadata = $checklist->metadata ?? [];
            $documentIds = $metadata['document_ids'] ?? [];
            
            $documents = [];
            if (!empty($documentIds)) {
                $documents = \App\Models\Hr\EmployeeDocument::where('employee_id', $employeeId)
                    ->whereIn('document_id', $documentIds)
                    ->with(['document', 'verifier', 'rejector'])
                    ->get()
                    ->map(function ($doc) use ($employeeId) {
                        return [
                            'id' => $doc->id,
                            'document_id' => $doc->document_id,
                            'title' => $doc->document->title ?? null,
                            'file_type' => $doc->document->file_type ?? null,
                            'file_size' => $doc->document->file_size ?? null,
                            'download_url' => $doc->document ? url("/api/hr/employees/{$employeeId}/documents/{$doc->document_id}/download") : null,
                            'preview_url' => $doc->document ? url("/api/hr/employees/{$employeeId}/documents/{$doc->document_id}/preview") : null,
                            'verification_status' => $doc->verification_status ?? 'pending',
                            'rejection_reason' => $doc->rejection_reason,
                            'verified_by' => $doc->verifier ? [
                                'id' => $doc->verifier->id,
                                'name' => $doc->verifier->name,
                            ] : null,
                            'verified_at' => $doc->verified_at?->toISOString(),
                            'rejected_by' => $doc->rejector ? [
                                'id' => $doc->rejector->id,
                                'name' => $doc->rejector->name,
                            ] : null,
                            'rejected_at' => $doc->rejected_at?->toISOString(),
                            'created_at' => $doc->created_at?->toISOString(),
                        ];
                    })
                    ->toArray();
            }
            
            $checklistArray = $checklist->toArray();
            $checklistArray['documents'] = $documents;
            return $checklistArray;
        });

        // Determine if user should see tasks
        // Employees should NOT see tasks (only checklist items)
        // HR Admin, System Admin can see all tasks
        $user = request()->user();
        $shouldShowTasks = false;
        
        if ($user->hasRole('hr_admin') || 
            $user->hasRole('system_admin') || 
            $user->hasRole('admin')) {
            // HR Admin can see all tasks
            $shouldShowTasks = true;
        } elseif ($user->hasRole('employee') && $employee->user_id === $user->id) {
            // Employee viewing their own onboarding - hide tasks
            $shouldShowTasks = false;
        } else {
            // Other roles (IT, Manager, Finance) - they should use /api/hr/onboarding/my-tasks
            // But if they're viewing employee onboarding, show only their assigned tasks
            $shouldShowTasks = false;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'employee_id' => $employee->employee_id,
                    'full_name' => $employee->full_name,
                    'work_email' => $employee->work_email,
                    'start_date' => $employee->start_date?->toDateString(),
                    'department' => $departmentName,
                    'designation' => $designationName,
                ],
                'progress' => $progress,
                'checklists' => $checklists, // Checklist items always visible (unchanged)
                'tasks' => $shouldShowTasks ? $employee->onboardingTasks : [], // Filter tasks based on role
            ],
        ]);
    }

    /**
     * Get onboarding progress for an employee.
     */
    public function progress(int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $this->authorize('view', $employee);

        $progress = $this->onboardingService->getOnboardingProgress($employee);

        return response()->json([
            'success' => true,
            'data' => $progress,
        ]);
    }

    /**
     * Get checklist items for an employee.
     */
    public function getChecklists(int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $this->authorize('view', $employee);

        $checklists = OnboardingChecklist::where('employee_id', $employeeId)
            ->with(['template', 'assignedUser', 'completedByUser'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Load documents for each checklist item if they have document_ids in metadata
        $checklists->each(function ($checklist) use ($employeeId) {
            $metadata = $checklist->metadata ?? [];
            $documentIds = $metadata['document_ids'] ?? [];
            
            if (!empty($documentIds)) {
                $documents = \App\Models\Hr\EmployeeDocument::where('employee_id', $employeeId)
                    ->whereIn('document_id', $documentIds)
                    ->with(['document', 'verifier', 'rejector'])
                    ->get()
                    ->map(function ($doc) use ($employeeId) {
                        return [
                            'id' => $doc->id,
                            'document_id' => $doc->document_id,
                            'title' => $doc->document->title ?? null,
                            'file_type' => $doc->document->file_type ?? null,
                            'file_size' => $doc->document->file_size ?? null,
                            'download_url' => $doc->document ? url("/api/hr/employees/{$employeeId}/documents/{$doc->document_id}/download") : null,
                            'preview_url' => $doc->document ? url("/api/hr/employees/{$employeeId}/documents/{$doc->document_id}/preview") : null,
                            'verification_status' => $doc->verification_status ?? 'pending',
                            'rejection_reason' => $doc->rejection_reason,
                            'verified_by' => $doc->verifier ? [
                                'id' => $doc->verifier->id,
                                'name' => $doc->verifier->name,
                            ] : null,
                            'verified_at' => $doc->verified_at?->toISOString(),
                            'rejected_by' => $doc->rejector ? [
                                'id' => $doc->rejector->id,
                                'name' => $doc->rejector->name,
                            ] : null,
                            'rejected_at' => $doc->rejected_at?->toISOString(),
                            'created_at' => $doc->created_at?->toISOString(),
                        ];
                    });
                
                $checklist->setAttribute('documents', $documents);
            } else {
                $checklist->setAttribute('documents', []);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $checklists,
        ]);
    }

    /**
     * Complete a checklist item.
     */
    public function completeChecklist(Request $request, int $employeeId, int $checklistId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        $this->authorize('update', $employee);

        $checklist = OnboardingChecklist::where('employee_id', $employeeId)
            ->findOrFail($checklistId);

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $checklist = $this->onboardingService->completeChecklistItem(
                $checklist,
                $request->input('notes'),
                $request->input('metadata')
            );

            return response()->json([
                'success' => true,
                'data' => $checklist->load(['template', 'completedByUser']),
                'message' => HrConstants::SUCCESS_ONBOARDING_CHECKLIST_COMPLETED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get tasks for an employee.
     * Employees should NOT see tasks - they should only see checklist items.
     * HR Admin can see all tasks.
     * IT/Manager/Finance should use /api/hr/onboarding/my-tasks to see their assigned tasks.
     */
    public function getTasks(int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $this->authorize('view', $employee);

        $user = request()->user();

        // Employees should NOT see tasks
        if ($user->hasRole('employee') && $employee->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tasks are not visible to employees. Please contact HR for task status.',
            ], 403);
        }

        // HR Admin, System Admin can see all tasks for the employee
        if ($user->hasRole('hr_admin') || 
            $user->hasRole('system_admin') || 
            $user->hasRole('admin')) {
            $tasks = OnboardingTask::where('employee_id', $employeeId)
                ->with(['assignedUser', 'completedByUser', 'creator'])
                ->orderBy('due_date', 'asc')
                ->orderBy('priority', 'desc')
                ->get();
        } else {
            // Other roles (IT, Manager, Finance) see only their assigned tasks
            $tasks = OnboardingTask::where('employee_id', $employeeId)
                ->where('assigned_to', $user->id)
                ->with(['assignedUser', 'completedByUser', 'creator'])
                ->orderBy('due_date', 'asc')
                ->orderBy('priority', 'desc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Create a new onboarding task.
     */
    public function createTask(Request $request, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        $this->authorize('update', $employee);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'task_type' => 'required|in:' . implode(',', [
                HrConstants::TASK_TYPE_HR,
                HrConstants::TASK_TYPE_MANAGER,
                HrConstants::TASK_TYPE_IT,
                HrConstants::TASK_TYPE_FINANCE,
                HrConstants::TASK_TYPE_LEGAL,
            ]),
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'nullable|in:' . implode(',', [
                HrConstants::TASK_PRIORITY_LOW,
                HrConstants::TASK_PRIORITY_MEDIUM,
                HrConstants::TASK_PRIORITY_HIGH,
                HrConstants::TASK_PRIORITY_URGENT,
            ]),
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $currentUser = auth()->user();
            $tenantId = $currentUser->tenant_id ?? $currentUser->id;

            $task = OnboardingTask::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'task_type' => $request->input('task_type'),
                'assigned_to' => $request->input('assigned_to'),
                'status' => HrConstants::TASK_STATUS_PENDING,
                'priority' => $request->input('priority', HrConstants::TASK_PRIORITY_MEDIUM),
                'due_date' => $request->input('due_date'),
                'created_by' => $currentUser->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $task->load(['assignedUser', 'creator']),
                'message' => HrConstants::SUCCESS_ONBOARDING_TASK_CREATED,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update an onboarding task.
     */
    public function updateTask(Request $request, int $employeeId, int $taskId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        $this->authorize('update', $employee);

        $task = OnboardingTask::where('employee_id', $employeeId)
            ->findOrFail($taskId);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:' . implode(',', [
                HrConstants::TASK_STATUS_PENDING,
                HrConstants::TASK_STATUS_IN_PROGRESS,
                HrConstants::TASK_STATUS_COMPLETED,
                HrConstants::TASK_STATUS_CANCELLED,
            ]),
            'priority' => 'sometimes|in:' . implode(',', [
                HrConstants::TASK_PRIORITY_LOW,
                HrConstants::TASK_PRIORITY_MEDIUM,
                HrConstants::TASK_PRIORITY_HIGH,
                HrConstants::TASK_PRIORITY_URGENT,
            ]),
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            // If status is being set to completed, use the service method
            if (isset($data['status']) && $data['status'] === HrConstants::TASK_STATUS_COMPLETED) {
                $task = $this->onboardingService->completeTask($task, $data['notes'] ?? null);
                unset($data['status'], $data['notes']);
            }

            // Update other fields
            if (!empty($data)) {
                $task->update($data);
            }

            return response()->json([
                'success' => true,
                'data' => $task->fresh()->load(['assignedUser', 'completedByUser']),
                'message' => HrConstants::SUCCESS_ONBOARDING_TASK_COMPLETED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Complete onboarding and activate employee.
     */
    public function completeOnboarding(int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        $this->authorize('update', $employee);

        try {
            $employee = $this->onboardingService->completeOnboarding($employee);

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'employee_id' => $employee->employee_id,
                        'full_name' => $employee->full_name,
                        'employment_status' => $employee->employment_status,
                    ],
                ],
                'message' => HrConstants::SUCCESS_ONBOARDING_COMPLETED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get my assigned onboarding tasks.
     */
    public function myTasks(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'task_type' => $request->query('task_type'),
            'priority' => $request->query('priority'),
            'employee_id' => $request->query('employee_id'),
            'sortBy' => $request->query('sortBy', 'due_date'),
            'sortOrder' => $request->query('sortOrder', 'asc'),
        ];

        $perPage = min((int) $request->query('per_page', 15), 100);
        $tasks = $this->onboardingService->getMyTasks($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $tasks->items(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    /**
     * Get all onboarding checklist templates.
     */
    public function getTemplates(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $filters = [
            'search' => $request->query('search'),
            'category' => $request->query('category'),
            'is_required' => $request->has('is_required') ? $request->boolean('is_required') : null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'sortBy' => $request->query('sortBy', 'order'),
            'sortOrder' => $request->query('sortOrder', 'asc'),
        ];

        $perPage = min((int) $request->query('per_page', 15), 100);
        $templates = $this->onboardingService->getTemplates($filters, $perPage);

        if ($templates instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return response()->json([
                'success' => true,
                'data' => $templates->items(),
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Create a new onboarding checklist template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', [
                HrConstants::CHECKLIST_CATEGORY_HR,
                HrConstants::CHECKLIST_CATEGORY_MANAGER,
                HrConstants::CHECKLIST_CATEGORY_IT,
                HrConstants::CHECKLIST_CATEGORY_FINANCE,
                HrConstants::CHECKLIST_CATEGORY_LEGAL,
            ]),
            'description' => 'nullable|string|max:1000',
            'is_required' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $template = $this->onboardingService->createTemplate($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => HrConstants::SUCCESS_ONBOARDING_TEMPLATE_CREATED,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update an onboarding checklist template.
     */
    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $this->authorize('update', Employee::class);

        $template = OnboardingChecklistTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|in:' . implode(',', [
                HrConstants::CHECKLIST_CATEGORY_HR,
                HrConstants::CHECKLIST_CATEGORY_MANAGER,
                HrConstants::CHECKLIST_CATEGORY_IT,
                HrConstants::CHECKLIST_CATEGORY_FINANCE,
                HrConstants::CHECKLIST_CATEGORY_LEGAL,
            ]),
            'description' => 'nullable|string|max:1000',
            'is_required' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $template = $this->onboardingService->updateTemplate($template, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => HrConstants::SUCCESS_ONBOARDING_TEMPLATE_UPDATED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete an onboarding checklist template.
     */
    public function destroyTemplate(int $id): JsonResponse
    {
        $this->authorize('delete', Employee::class);

        $template = OnboardingChecklistTemplate::findOrFail($id);

        try {
            $this->onboardingService->deleteTemplate($template);

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

