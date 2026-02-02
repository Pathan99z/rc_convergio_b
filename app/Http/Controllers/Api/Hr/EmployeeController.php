<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Http\Resources\Hr\EmployeeResource;
use App\Models\Hr\Employee;
use App\Services\Hr\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeService $employeeService
    ) {}

    /**
     * List employees with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $filters = [
            'search' => $request->query('search'),
            'department' => $request->query('department'),
            'employment_status' => $request->query('employment_status'),
            'employment_type' => $request->query('employment_type'),
            'include_archived' => $request->boolean('include_archived'),
            'sortBy' => $request->query('sortBy', 'created_at'),
            'sortOrder' => $request->query('sortOrder', 'desc'),
        ];

        $perPage = min((int) $request->query('per_page', 15), 100);
        $employees = $this->employeeService->getEmployees($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => EmployeeResource::collection($employees->items()),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    /**
     * Create a new employee.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $validator = Validator::make($request->all(), [
            // Personal Information
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'preferred_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string|max:255',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'id_number' => 'nullable|string|max:255',
            'passport_number' => 'nullable|string|max:255',
            
            // Contact Details
            'work_email' => 'required|email|unique:hr_employees,work_email',
            'personal_email' => 'nullable|email',
            'phone_number' => 'required|string|max:255',
            'work_phone' => 'nullable|string|max:255',
            'office_address' => 'nullable|string',
            'address' => 'nullable|array',
            'emergency_contact' => 'nullable|array',
            
            // Job Information
            'job_title' => 'nullable|string|max:255', // Keep for backward compatibility
            'department' => 'nullable|string|max:255', // Keep for backward compatibility
            'department_id' => 'nullable|exists:hr_departments,id',
            'designation_id' => 'nullable|exists:hr_designations,id',
            'employment_type' => 'nullable|in:' . implode(',', [
                HrConstants::TYPE_FULL_TIME,
                HrConstants::TYPE_PART_TIME,
                HrConstants::TYPE_CONTRACT,
                HrConstants::TYPE_INTERN,
            ]),
            'employment_status' => 'nullable|in:' . implode(',', [
                HrConstants::STATUS_ONBOARDING,
                HrConstants::STATUS_ACTIVE,
                HrConstants::STATUS_ON_LEAVE,
                HrConstants::STATUS_SUSPENDED,
                HrConstants::STATUS_OFFBOARDED,
            ]),
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'work_schedule' => 'nullable|string|max:255',
            'probation_end_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'manager_id' => 'nullable|exists:hr_employees,id',
            'team_id' => 'nullable|exists:teams,id',
            
            // Additional Fields
            'salary' => 'nullable|string',
            'bank_account' => 'nullable|string',
            'leave_balances' => 'nullable|array',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            
            // User Account (Optional)
            'create_user_account' => 'nullable|boolean',
            'user_account' => 'nullable|array',
            'user_account.email' => 'nullable|email',
            'user_account.password' => 'nullable|string|min:8',
            'user_account.role' => 'nullable|string|exists:roles,name',
            'user_account.team_id' => 'nullable|exists:teams,id',
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
            $profilePicture = $request->hasFile('profile_picture') ? $request->file('profile_picture') : null;
            
            $employee = $this->employeeService->createEmployee($data, $profilePicture);

            return response()->json([
                'success' => true,
                'data' => new EmployeeResource($employee->load(['department', 'designation', 'profilePicture'])),
                'message' => HrConstants::SUCCESS_EMPLOYEE_CREATED,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get employee details.
     */
    public function show(int $id): JsonResponse
    {
        $employee = Employee::with([
            'manager',
            'team',
            'user',
            'creator',
            'department',
            'designation',
            'profilePicture',
            'leaveBalances.leaveType',
            'directReports',
        ])->findOrFail($id);

        $this->authorize('view', $employee);

        return response()->json([
            'success' => true,
            'data' => new EmployeeResource($employee),
        ]);
    }

    /**
     * Update employee.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        $validator = Validator::make($request->all(), [
            // Personal Information
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'preferred_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string|max:255',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'id_number' => 'nullable|string|max:255',
            'passport_number' => 'nullable|string|max:255',
            
            // Contact Details
            'work_email' => 'sometimes|required|email|unique:hr_employees,work_email,' . $id,
            'personal_email' => 'nullable|email',
            'phone_number' => 'sometimes|required|string|max:255',
            'work_phone' => 'nullable|string|max:255',
            'office_address' => 'nullable|string',
            'address' => 'nullable|array',
            'emergency_contact' => 'nullable|array',
            
            // Job Information
            'job_title' => 'sometimes|required|string|max:255',
            'department' => 'sometimes|required|string|max:255',
            'department_id' => 'nullable|exists:hr_departments,id',
            'designation_id' => 'nullable|exists:hr_designations,id',
            'employment_type' => 'nullable|in:' . implode(',', [
                HrConstants::TYPE_FULL_TIME,
                HrConstants::TYPE_PART_TIME,
                HrConstants::TYPE_CONTRACT,
                HrConstants::TYPE_INTERN,
            ]),
            'employment_status' => 'nullable|in:' . implode(',', [
                HrConstants::STATUS_ONBOARDING,
                HrConstants::STATUS_ACTIVE,
                HrConstants::STATUS_ON_LEAVE,
                HrConstants::STATUS_SUSPENDED,
                HrConstants::STATUS_OFFBOARDED,
            ]),
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date',
            'work_schedule' => 'nullable|string|max:255',
            'probation_end_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'manager_id' => 'nullable|exists:hr_employees,id',
            'team_id' => 'nullable|exists:teams,id',
            
            // Additional Fields
            'salary' => 'nullable|string',
            'bank_account' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $employee = $this->employeeService->updateEmployee($employee, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => new EmployeeResource($employee),
                'message' => HrConstants::SUCCESS_EMPLOYEE_UPDATED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Archive employee.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('delete', $employee);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'last_working_date' => 'required|date',
            'revoke_access' => 'nullable|boolean',
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
            $employee->update(['end_date' => $data['last_working_date']]);
            
            if ($data['revoke_access'] ?? false) {
                // Revoke user access if linked
                if ($employee->user_id) {
                    $employee->user->update(['status' => 'inactive']);
                }
            }

            $this->employeeService->archiveEmployee($employee, $data['reason']);

            return response()->json([
                'success' => true,
                'message' => HrConstants::SUCCESS_EMPLOYEE_ARCHIVED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Activate employee (from onboarding to active).
     */
    public function activate(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $this->authorize('update', $employee);

        try {
            $employee = $this->employeeService->activateEmployee($employee);

            return response()->json([
                'success' => true,
                'data' => new EmployeeResource($employee),
                'message' => HrConstants::SUCCESS_EMPLOYEE_ACTIVATED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Search employees.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $search = $request->query('q', '');
        if (empty($search)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $filters = ['search' => $search];
        $employees = $this->employeeService->getEmployees($filters, 20);

        return response()->json([
            'success' => true,
            'data' => EmployeeResource::collection($employees->items()),
        ]);
    }

    /**
     * Get available managers for dropdown (excludes current employee if editing).
     * Only shows employees with designations where is_manager = true.
     */
    public function managers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $currentUser = $request->user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;
        $excludeId = $request->query('exclude_id');

        $query = Employee::where('tenant_id', $tenantId)
            ->where('employment_status', '!=', HrConstants::STATUS_OFFBOARDED)
            ->whereNull('archived_at')
            ->whereHas('designation', function ($q) {
                // Only show employees with designations marked as manager
                $q->where('is_manager', true)
                  ->where('is_active', true);
            })
            ->with(['department', 'designation']);

        // Exclude current employee if editing
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $managers = $query->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $formattedManagers = $managers->map(function ($employee) {
            return [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'full_name' => $employee->full_name,
                'job_title' => $employee->job_title,
                'department' => $employee->department,
                'department_detail' => $employee->department ? [
                    'id' => $employee->department->id,
                    'name' => $employee->department->name,
                    'code' => $employee->department->code,
                ] : null,
                'designation_detail' => $employee->designation ? [
                    'id' => $employee->designation->id,
                    'name' => $employee->designation->name,
                    'code' => $employee->designation->code,
                ] : null,
                'display' => sprintf(
                    '%s - %s - %s',
                    $employee->full_name,
                    $employee->job_title ?: 'N/A',
                    $employee->department ?: 'N/A'
                ),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedManagers,
        ]);
    }
}

