<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveBalance;
use App\Models\Hr\LeaveType;
use App\Models\User;
use App\Services\UserService;
use App\Services\DocumentService;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;

class EmployeeService
{
    protected EmployeeIdService $employeeIdService;
    protected HrAuditService $auditService;
    protected UserService $userService;
    protected DocumentService $documentService;

    public function __construct(
        EmployeeIdService $employeeIdService,
        HrAuditService $auditService,
        UserService $userService,
        DocumentService $documentService
    ) {
        $this->employeeIdService = $employeeIdService;
        $this->auditService = $auditService;
        $this->userService = $userService;
        $this->documentService = $documentService;
    }

    /**
     * Get paginated employees with filters.
     */
    public function getEmployees(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();
        
        $query = Employee::query()
            ->with(['manager', 'team', 'user', 'creator']);

        // Apply role-based filtering
        if ($currentUser->hasRole('line_manager')) {
            // Managers see only their team
            $query->where('team_id', $currentUser->team_id);
        } elseif ($currentUser->hasRole('employee')) {
            // Employees see only themselves
            $query->where('user_id', $currentUser->id);
        }
        // HR Admin and System Admin see all (no additional filter)

        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('work_email', 'like', "%{$search}%");
            });
        }

        // Skip filter if value is empty or "all"
        if (!empty($filters['department']) && $filters['department'] !== 'all') {
            $query->where('department', $filters['department']);
        }

        // Skip filter if value is empty or "all"
        if (!empty($filters['employment_status']) && $filters['employment_status'] !== 'all') {
            $query->where('employment_status', $filters['employment_status']);
        }

        // Skip filter if value is empty or "all"
        if (!empty($filters['employment_type']) && $filters['employment_type'] !== 'all') {
            $query->where('employment_type', $filters['employment_type']);
        }

        if (isset($filters['include_archived']) && $filters['include_archived']) {
            $query->withTrashed();
        } else {
            $query->whereNull('archived_at');
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new employee.
     */
    public function createEmployee(array $data, ?UploadedFile $profilePicture = null): Employee
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            // Generate employee ID
            $employeeId = $this->employeeIdService->generate($tenantId);

            // Check if work email already exists
            if (Employee::where('work_email', $data['work_email'])->exists()) {
                throw new \Exception(HrConstants::ERROR_EMPLOYEE_ALREADY_EXISTS);
            }

            // Handle user account creation/linking
            $userId = $data['user_id'] ?? null;
            if (!empty($data['create_user_account']) && $data['create_user_account']) {
                $userId = $this->handleUserAccountCreation($data, $tenantId);
            }

            // Handle profile picture upload
            $profilePictureId = null;
            if ($profilePicture) {
                $profilePictureId = $this->uploadProfilePicture($profilePicture, $tenantId, $currentUser->id);
            }

            // Populate job_title and department from IDs if provided (for backward compatibility)
            $jobTitle = $data['job_title'] ?? null;
            $departmentName = $data['department'] ?? null;

            // If designation_id is provided, get the designation name for job_title (backward compatibility)
            if (!empty($data['designation_id']) && empty($jobTitle)) {
                $designation = \App\Models\Hr\Designation::where('id', $data['designation_id'])
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($designation) {
                    $jobTitle = $designation->name;
                }
            }

            // If department_id is provided, get the department name (backward compatibility)
            if (!empty($data['department_id']) && empty($departmentName)) {
                $department = \App\Models\Hr\Department::where('id', $data['department_id'])
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($department) {
                    $departmentName = $department->name;
                }
            }

            // Ensure job_title and department are not null (database constraint)
            $jobTitle = $jobTitle ?? '';
            $departmentName = $departmentName ?? '';

            // Create employee
            $employee = Employee::create([
                'tenant_id' => $tenantId,
                'team_id' => $data['team_id'] ?? $currentUser->team_id,
                'employee_id' => $employeeId,
                'user_id' => $userId,
                'profile_picture_id' => $profilePictureId,
                // Personal Information
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'preferred_name' => $data['preferred_name'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'id_number' => $data['id_number'] ?? null,
                'passport_number' => $data['passport_number'] ?? null,
                // Contact Details
                'work_email' => $data['work_email'],
                'personal_email' => $data['personal_email'] ?? null,
                'phone_number' => $data['phone_number'],
                'work_phone' => $data['work_phone'] ?? null,
                'office_address' => $data['office_address'] ?? null,
                'address' => $data['address'] ?? null,
                'emergency_contact' => $data['emergency_contact'] ?? null,
                // Job Information
                'job_title' => $jobTitle, // Use designation name or provided value, default to empty string
                'department' => $departmentName, // Use department name or provided value, default to empty string
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'employment_type' => $data['employment_type'] ?? HrConstants::TYPE_FULL_TIME,
                'employment_status' => $data['employment_status'] ?? HrConstants::STATUS_ONBOARDING,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'work_schedule' => $data['work_schedule'] ?? null,
                'probation_end_date' => $data['probation_end_date'] ?? null,
                'contract_end_date' => $data['contract_end_date'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                // Additional Fields
                'salary' => $data['salary'] ?? null,
                'bank_account' => $data['bank_account'] ?? null,
                'created_by' => $currentUser->id,
            ]);

            // Initialize leave balances if provided
            if (!empty($data['leave_balances'])) {
                $this->initializeLeaveBalances($employee->id, $data['leave_balances']);
            }

            // Initialize onboarding if employee status is onboarding
            if ($employee->employment_status === HrConstants::STATUS_ONBOARDING) {
                try {
                    $onboardingService = app(\App\Services\Hr\OnboardingService::class);
                    $onboardingService->initializeOnboarding($employee);
                } catch (\Exception $e) {
                    // Log error but don't fail employee creation
                    Log::warning('Failed to initialize onboarding for employee', [
                        'employee_id' => $employee->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Log audit
            $this->auditService->logEmployeeCreated($employee->id, [
                'employee_id' => $employeeId,
                'name' => $employee->full_name,
                'user_account_created' => !empty($userId),
            ]);

            DB::commit();
            return $employee->load(['manager', 'team', 'user', 'creator', 'department', 'designation', 'profilePicture']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create employee', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Handle user account creation or linking.
     */
    protected function handleUserAccountCreation(array $data, int $tenantId): ?int
    {
        $userAccountData = $data['user_account'] ?? [];
        $email = $userAccountData['email'] ?? $data['work_email'];

        // Check if user already exists with this email
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            // User exists - link to employee
            // Verify tenant matches (security check)
            if ($existingUser->tenant_id !== $tenantId) {
                throw new \Exception('User account exists but belongs to different tenant');
            }

            // Assign role if provided
            if (!empty($userAccountData['role'])) {
                $role = \Spatie\Permission\Models\Role::where('name', $userAccountData['role'])->first();
                if ($role && !$existingUser->hasRole($userAccountData['role'])) {
                    $existingUser->assignRole($role);
                }
            }

            // Update team if provided
            if (!empty($userAccountData['team_id'])) {
                $existingUser->update(['team_id' => $userAccountData['team_id']]);
            }

            return $existingUser->id;
        }

        // User doesn't exist - create new user account
        $userData = [
            'name' => "{$data['first_name']} {$data['last_name']}",
            'email' => $email,
            'password' => $userAccountData['password'] ?? $this->generateRandomPassword(),
            'status' => 'active',
            'tenant_id' => $tenantId,
            'team_id' => $userAccountData['team_id'] ?? $data['team_id'] ?? null,
        ];

        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'status' => $userData['status'],
            'tenant_id' => $userData['tenant_id'],
            'team_id' => $userData['team_id'],
        ]);

        // Assign role if provided
        if (!empty($userAccountData['role'])) {
            $role = \Spatie\Permission\Models\Role::where('name', $userAccountData['role'])->first();
            if ($role) {
                $user->assignRole($role);
            }
        } else {
            // Default to employee role
            $employeeRole = \Spatie\Permission\Models\Role::where('name', 'employee')->first();
            if ($employeeRole) {
                $user->assignRole($employeeRole);
            }
        }

        // Send welcome email with credentials (if password was generated)
        if (empty($userAccountData['password'])) {
            // TODO: Send email with generated password
            Log::info('User account created for employee', [
                'user_id' => $user->id,
                'employee_email' => $email,
                'password_generated' => true,
            ]);
        }

        return $user->id;
    }

    /**
     * Upload profile picture.
     */
    protected function uploadProfilePicture(UploadedFile $file, int $tenantId, int $userId): int
    {
        try {
            $document = $this->documentService->uploadDocument($file, [
                'title' => 'Employee Profile Picture',
                'visibility' => 'team',
                'related_type' => 'App\\Models\\Hr\\Employee',
            ]);

            return $document->id;
        } catch (\Exception $e) {
            Log::error('Failed to upload profile picture', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception(HrConstants::ERROR_PROFILE_PICTURE_UPLOAD_FAILED);
        }
    }

    /**
     * Generate random password.
     */
    protected function generateRandomPassword(int $length = 12): string
    {
        return \Illuminate\Support\Str::random($length);
    }

    /**
     * Update an employee.
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        $oldValues = $employee->toArray();
        $tenantId = $employee->tenant_id;

        DB::beginTransaction();
        try {
            // Check if work email is being changed and already exists
            if (isset($data['work_email']) && $data['work_email'] !== $employee->work_email) {
                if (Employee::where('work_email', $data['work_email'])->where('id', '!=', $employee->id)->exists()) {
                    throw new \Exception(HrConstants::ERROR_EMPLOYEE_ALREADY_EXISTS);
                }
            }

            // Populate job_title and department from IDs if provided (for backward compatibility)
            if (isset($data['designation_id']) && !isset($data['job_title'])) {
                // If designation_id is being updated, get the designation name for job_title
                $designation = \App\Models\Hr\Designation::where('id', $data['designation_id'])
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($designation) {
                    $data['job_title'] = $designation->name;
                }
            }

            if (isset($data['department_id']) && !isset($data['department'])) {
                // If department_id is being updated, get the department name
                $department = \App\Models\Hr\Department::where('id', $data['department_id'])
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($department) {
                    $data['department'] = $department->name;
                }
            }

            // Ensure job_title and department are not null if they're being updated
            if (isset($data['job_title']) && $data['job_title'] === null) {
                $data['job_title'] = '';
            }
            if (isset($data['department']) && $data['department'] === null) {
                $data['department'] = '';
            }

            $employee->update($data);
            $employee->refresh();

            // Log audit
            $this->auditService->logEmployeeUpdated($employee->id, $oldValues, $employee->toArray());

            DB::commit();
            return $employee->load(['manager', 'team', 'user', 'creator']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update employee', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Archive an employee.
     */
    public function archiveEmployee(Employee $employee, string $reason): Employee
    {
        if ($employee->employment_status === HrConstants::STATUS_ACTIVE) {
            throw new \Exception(HrConstants::ERROR_CANNOT_ARCHIVE_ACTIVE);
        }

        DB::beginTransaction();
        try {
            $employee->update([
                'employment_status' => HrConstants::STATUS_OFFBOARDED,
                'archived_at' => now(),
            ]);

            // Log audit
            $this->auditService->logEmployeeArchived($employee->id, $reason);

            DB::commit();
            return $employee;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Activate an employee (from onboarding to active).
     */
    public function activateEmployee(Employee $employee): Employee
    {
        DB::beginTransaction();
        try {
            $oldStatus = $employee->employment_status;
            
            $employee->update([
                'employment_status' => HrConstants::STATUS_ACTIVE,
            ]);

            // Log audit
            $this->auditService->logEmployeeUpdated($employee->id, ['employment_status' => $oldStatus], ['employment_status' => HrConstants::STATUS_ACTIVE]);

            DB::commit();
            return $employee;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Initialize leave balances for an employee.
     */
    protected function initializeLeaveBalances(int $employeeId, array $balances): void
    {
        $tenantId = Auth::user()->tenant_id ?? Auth::id();
        
        foreach ($balances as $leaveTypeId => $balance) {
            LeaveBalance::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'balance' => $balance,
            ]);
        }
    }
}

