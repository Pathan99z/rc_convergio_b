<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Hr\Department;
use App\Services\Hr\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    protected DepartmentService $departmentService;

    public function __construct(DepartmentService $departmentService)
    {
        $this->departmentService = $departmentService;
    }

    /**
     * Display a listing of departments.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $filters = $request->only(['search', 'sortBy', 'sortOrder']);
        
        // Convert string "true"/"false" to boolean for is_active filter
        if ($request->has('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }
        
        $perPage = $request->get('per_page', 15);

        $departments = $this->departmentService->getDepartments($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $departments->items(),
            'pagination' => [
                'current_page' => $departments->currentPage(),
                'per_page' => $departments->perPage(),
                'total' => $departments->total(),
                'last_page' => $departments->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
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
            $department = $this->departmentService->createDepartment($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $department,
                'message' => HrConstants::SUCCESS_DEPARTMENT_CREATED,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified department.
     */
    public function show(Department $department): JsonResponse
    {
        $this->authorize('view', $department);

        return response()->json([
            'success' => true,
            'data' => $department->load('creator'),
        ]);
    }

    /**
     * Update the specified department.
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
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
            $department = $this->departmentService->updateDepartment($department, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $department,
                'message' => HrConstants::SUCCESS_DEPARTMENT_UPDATED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove the specified department.
     */
    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        try {
            $this->departmentService->deleteDepartment($department);

            return response()->json([
                'success' => true,
                'message' => HrConstants::SUCCESS_DEPARTMENT_DELETED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

