<?php

namespace App\Http\Controllers\Api;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\KpiAssignment;
use App\Services\Hr\KpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeKpiController extends Controller
{
    protected KpiService $kpiService;

    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    /**
     * Get employee's KPI assignments.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $filters = $request->only(['status', 'review_period_value', 'sortBy', 'sortOrder', 'per_page']);
        $assignments = $this->kpiService->getEmployeeAssignments($employee->id, $filters);

        return response()->json([
            'success' => true,
            'data' => $assignments->items(),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ],
        ]);
    }

    /**
     * Get assignment details.
     */
    public function show(int $id): JsonResponse
    {
        $user = request()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $assignment = KpiAssignment::with([
            'template.items',
            'selfReview.items.templateItem',
            'managerReview.items.templateItem'
        ])->findOrFail($id);

        // Verify assignment belongs to employee
        if ($assignment->employee_id !== $employee->id) {
            return response()->json([
                'success' => false,
                'message' => HrConstants::ERROR_UNAUTHORIZED_ACCESS,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    /**
     * Submit self review.
     */
    public function submitSelfReview(Request $request, int $id): JsonResponse
    {
        $assignment = KpiAssignment::with(['template.items'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.kpi_template_item_id' => 'required|exists:hr_kpi_template_items,id',
            'items.*.score' => 'required|numeric|min:0|max:10',
            'items.*.comments' => 'nullable|string',
            'comments' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $review = $this->kpiService->submitSelfReview($assignment, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $review->load(['items.templateItem']),
                'message' => 'Self review submitted successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get employee's review history.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $filters = $request->only(['status', 'review_period_value', 'sortBy', 'sortOrder', 'per_page']);
        $filters['status'] = 'completed'; // Only show completed reviews
        $assignments = $this->kpiService->getEmployeeAssignments($employee->id, $filters);

        return response()->json([
            'success' => true,
            'data' => $assignments->items(),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ],
        ]);
    }
}

