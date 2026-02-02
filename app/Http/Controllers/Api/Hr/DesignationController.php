<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Hr\Designation;
use App\Services\Hr\DesignationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DesignationController extends Controller
{
    protected DesignationService $designationService;

    public function __construct(DesignationService $designationService)
    {
        $this->designationService = $designationService;
    }

    /**
     * Display a listing of designations.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Designation::class);

        $filters = $request->only(['search', 'department_id', 'sortBy', 'sortOrder']);
        
        // Convert string "true"/"false" to boolean for is_active filter
        if ($request->has('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }
        
        $perPage = $request->get('per_page', 15);

        $designations = $this->designationService->getDesignations($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $designations->items(),
            'pagination' => [
                'current_page' => $designations->currentPage(),
                'per_page' => $designations->perPage(),
                'total' => $designations->total(),
                'last_page' => $designations->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created designation.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Designation::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:hr_departments,id',
            'is_active' => 'nullable|boolean',
            'is_manager' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $designation = $this->designationService->createDesignation($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $designation,
                'message' => HrConstants::SUCCESS_DESIGNATION_CREATED,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified designation.
     */
    public function show(Designation $designation): JsonResponse
    {
        $this->authorize('view', $designation);

        return response()->json([
            'success' => true,
            'data' => $designation->load(['creator', 'department']),
        ]);
    }

    /**
     * Update the specified designation.
     */
    public function update(Request $request, Designation $designation): JsonResponse
    {
        $this->authorize('update', $designation);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:hr_departments,id',
            'is_active' => 'nullable|boolean',
            'is_manager' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $designation = $this->designationService->updateDesignation($designation, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $designation,
                'message' => HrConstants::SUCCESS_DESIGNATION_UPDATED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove the specified designation.
     */
    public function destroy(Designation $designation): JsonResponse
    {
        $this->authorize('delete', $designation);

        try {
            $this->designationService->deleteDesignation($designation);

            return response()->json([
                'success' => true,
                'message' => HrConstants::SUCCESS_DESIGNATION_DELETED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get designations by department.
     */
    public function getByDepartment(Request $request, $departmentId): JsonResponse
    {
        $this->authorize('viewAny', Designation::class);

        try {
            $currentUser = \Illuminate\Support\Facades\Auth::user();
            $tenantId = $currentUser->tenant_id ?? $currentUser->id;

            // Verify department belongs to tenant
            $department = \App\Models\Hr\Department::where('id', $departmentId)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $designations = Designation::where('department_id', $departmentId)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $designations,
                'department' => [
                    'id' => $department->id,
                    'name' => $department->name,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

