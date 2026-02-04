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
     * Parse multipart/form-data from raw request body for PUT requests.
     * PHP doesn't automatically parse multipart/form-data for PUT requests.
     */
    private function parseMultipartFormData(Request $request): array
    {
        $data = [];
        $contentType = $request->header('Content-Type', '');
        
        // Only parse if it's multipart/form-data
        if (strpos($contentType, 'multipart/form-data') === false) {
            return $data;
        }

        // Get boundary from Content-Type header
        preg_match('/boundary=(.*)$/i', $contentType, $matches);
        if (!isset($matches[1])) {
            return $data;
        }
        
        $boundary = '--' . trim($matches[1]);
        $rawBody = $request->getContent();
        
        if (empty($rawBody)) {
            return $data;
        }

        // Split by boundary
        $parts = explode($boundary, $rawBody);
        
        foreach ($parts as $part) {
            // Skip empty parts and closing boundary
            if (empty(trim($part)) || trim($part) === '--') {
                continue;
            }

            // Parse the part header and body
            if (preg_match('/Content-Disposition: form-data; name="([^"]+)"(?:; filename="([^"]+)")?/i', $part, $headerMatches)) {
                $fieldName = $headerMatches[1];
                
                // Skip file uploads (we handle them separately via $request->file())
                if (isset($headerMatches[2])) {
                    continue;
                }

                // Extract the value (everything after the header)
                // Find the double CRLF that separates header from body
                $headerEnd = strpos($part, "\r\n\r\n");
                if ($headerEnd === false) {
                    $headerEnd = strpos($part, "\n\n");
                }
                
                if ($headerEnd !== false) {
                    $value = substr($part, $headerEnd + 4);
                    // Remove trailing CRLF and boundary markers
                    $value = rtrim($value, "\r\n");
                    $value = rtrim($value, "\n");
                    $value = rtrim($value, "--");
                    $value = trim($value);
                    
                    // Handle nested arrays (e.g., address[street])
                    if (preg_match('/^(.+)\[(.+)\]$/', $fieldName, $nestedMatches)) {
                        $parentKey = $nestedMatches[1];
                        $childKey = $nestedMatches[2];
                        
                        if (!isset($data[$parentKey])) {
                            $data[$parentKey] = [];
                        }
                        $data[$parentKey][$childKey] = $value;
                    } else {
                        $data[$fieldName] = $value;
                    }
                }
            }
        }

        return $data;
    }

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
            'manager_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '') {
                        // Convert string to integer if needed
                        $managerId = is_numeric($value) ? (int) $value : null;
                        
                        if ($managerId === null) {
                            return; // Let nullable handle it
                        }
                        
                        // Check if manager exists and belongs to same tenant
                        $currentUser = request()->user();
                        $tenantId = $currentUser->tenant_id ?? $currentUser->id;
                        
                        $manager = Employee::where('id', $managerId)
                            ->where('tenant_id', $tenantId)
                            ->where('employment_status', '!=', HrConstants::STATUS_OFFBOARDED)
                            ->whereNull('archived_at')
                            ->first();
                        
                        if (!$manager) {
                            $fail('The selected manager is invalid or does not belong to your organization.');
                        }
                    }
                },
            ],
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

        // CRITICAL FIX: Laravel doesn't parse multipart/form-data for PUT requests automatically
        // Try multiple methods to extract form data
        $requestData = [];
        $contentType = $request->header('Content-Type', '');
        $isMultipart = strpos($contentType, 'multipart/form-data') !== false;

        // Method 1: Try Laravel's input() method (works for POST, might work for PUT in some cases)
        $allInput = $request->all();
        if (!empty($allInput)) {
            $requestData = $allInput;
            \Log::info('Got data from $request->all()', ['keys' => array_keys($requestData)]);
        }

        // Method 2: For PUT requests with multipart/form-data, parse raw body manually
        if (empty($requestData) && $isMultipart && $request->method() === 'PUT') {
            $parsedData = $this->parseMultipartFormData($request);
            if (!empty($parsedData)) {
                $requestData = $parsedData;
                \Log::info('Got data from manual multipart parser', ['keys' => array_keys($requestData)]);
            }
        }

        // Method 3: Fallback - manually extract known fields using input()
        if (empty($requestData)) {
            // List of all possible fields that might be in the request
            $possibleFields = [
                'first_name', 'last_name', 'preferred_name', 'date_of_birth', 'gender', 'nationality',
                'marital_status', 'id_number', 'passport_number', 'work_email', 'personal_email',
                'phone_number', 'work_phone', 'office_address',
                'job_title', 'department', 'department_id', 'designation_id', 'employment_type',
                'employment_status', 'start_date', 'end_date', 'work_schedule', 'probation_end_date',
                'contract_end_date', 'manager_id', 'team_id', 'salary', 'bank_account'
            ];

            // Manually extract each field using input()
            foreach ($possibleFields as $field) {
                $value = $request->input($field);
                // Only add non-null values (null means field not present)
                if ($value !== null && $value !== '') {
                    $requestData[$field] = $value;
                }
            }

            \Log::warning('Using fallback field extraction', [
                'extracted_keys' => array_keys($requestData),
                'content_type' => $contentType,
                'method' => $request->method(),
            ]);
        }

        // Handle nested arrays (address, emergency_contact) - ensure they're properly structured
        // The parser should already handle address[street] format, but ensure it's an array
        if (isset($requestData['address']) && is_string($requestData['address'])) {
            // If address came as a string, try to parse it
            parse_str($requestData['address'], $parsedAddress);
            $requestData['address'] = $parsedAddress;
        }

        if (isset($requestData['emergency_contact']) && is_string($requestData['emergency_contact'])) {
            // If emergency_contact came as a string, try to parse it
            parse_str($requestData['emergency_contact'], $parsedEmergency);
            $requestData['emergency_contact'] = $parsedEmergency;
        }

        // Debug: Log what we extracted
        \Log::info('Extracted request data', [
            'extracted_keys' => array_keys($requestData),
            'has_manager_id' => isset($requestData['manager_id']),
            'manager_id_value' => $requestData['manager_id'] ?? 'NOT_SET',
            'manager_id_type' => isset($requestData['manager_id']) ? gettype($requestData['manager_id']) : 'N/A',
            'content_type' => $contentType,
            'method' => $request->method(),
            'is_multipart' => $isMultipart,
        ]);

        $validator = Validator::make($requestData, [
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
            'manager_id' => [
                'nullable',
                function ($attribute, $value, $fail) use ($id, $employee) {
                    if ($value !== null && $value !== '') {
                        // Convert string to integer if needed
                        $managerId = is_numeric($value) ? (int) $value : null;
                        
                        if ($managerId === null) {
                            return; // Let nullable handle it
                        }
                        
                        // Check if manager exists and belongs to same tenant
                        $manager = Employee::where('id', $managerId)
                            ->where('tenant_id', $employee->tenant_id)
                            ->where('id', '!=', $id) // Prevent self-reference
                            ->where('employment_status', '!=', HrConstants::STATUS_OFFBOARDED)
                            ->whereNull('archived_at')
                            ->first();
                        
                        if (!$manager) {
                            $fail('The selected manager is invalid or does not belong to your organization.');
                        }
                    }
                },
            ],
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
            $validated = $validator->validated();
            
            // CRITICAL FIX: Check $requestData instead of $request->has() for PUT requests
            // For PUT requests with multipart/form-data, $request->has() doesn't work
            if (isset($requestData['manager_id']) && !array_key_exists('manager_id', $validated)) {
                $validated['manager_id'] = $requestData['manager_id'];
                \Log::info('Added manager_id from requestData to validated data', [
                    'manager_id' => $validated['manager_id'],
                    'type' => gettype($validated['manager_id']),
                ]);
            }
            
            // Normalize manager_id: convert string to int, empty string to null
            if (isset($validated['manager_id'])) {
                if ($validated['manager_id'] === '' || $validated['manager_id'] === null) {
                    $validated['manager_id'] = null;
                } else {
                    $validated['manager_id'] = is_numeric($validated['manager_id']) 
                        ? (int) $validated['manager_id'] 
                        : null;
                }
                
                \Log::info('Manager ID normalized', [
                    'normalized_value' => $validated['manager_id'],
                    'type' => gettype($validated['manager_id']),
                ]);
            } else {
                \Log::warning('Manager ID not in validated data', [
                    'validated_keys' => array_keys($validated),
                    'requestData_has_manager_id' => isset($requestData['manager_id']),
                ]);
            }
            
            // Debug logging to track manager_id through the flow
            \Log::info('Employee update - validated data', [
                'employee_id' => $employee->id,
                'has_manager_id' => array_key_exists('manager_id', $validated),
                'manager_id_value' => $validated['manager_id'] ?? 'NOT_SET',
                'manager_id_type' => isset($validated['manager_id']) ? gettype($validated['manager_id']) : 'N/A',
                'all_keys' => array_keys($validated),
            ]);
            
            $employee = $this->employeeService->updateEmployee($employee, $validated);

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
            // Get the department relationship object (loaded via ->with())
            $departmentRelation = $employee->relationLoaded('department') 
                ? $employee->getRelation('department') 
                : null;
            
            // Get the designation relationship object (loaded via ->with())
            $designationRelation = $employee->relationLoaded('designation') 
                ? $employee->getRelation('designation') 
                : null;
            
            // Get department name for display (prefer relationship, fallback to string field)
            $departmentName = $departmentRelation 
                ? $departmentRelation->name 
                : ($employee->department ?: 'N/A');
            
            return [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'full_name' => $employee->full_name,
                'job_title' => $employee->job_title,
                'department' => $employee->department, // Keep string field for backward compatibility
                'department_detail' => $departmentRelation ? [
                    'id' => $departmentRelation->id,
                    'name' => $departmentRelation->name,
                    'code' => $departmentRelation->code,
                ] : null,
                'designation_detail' => $designationRelation ? [
                    'id' => $designationRelation->id,
                    'name' => $designationRelation->name,
                    'code' => $designationRelation->code,
                ] : null,
                'display' => sprintf(
                    '%s - %s - %s',
                    $employee->full_name,
                    $employee->job_title ?: 'N/A',
                    $departmentName
                ),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedManagers,
        ]);
    }
}

