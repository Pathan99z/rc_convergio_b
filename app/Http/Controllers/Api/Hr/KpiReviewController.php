<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Hr\KpiAssignment;
use App\Services\Hr\KpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KpiReviewController extends Controller
{
    protected KpiService $kpiService;

    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    /**
     * Get manager's team assignments.
     */
    public function myTeam(Request $request): JsonResponse
    {
        $user = $request->user();
        $managerEmployee = \App\Models\Hr\Employee::where('user_id', $user->id)->firstOrFail();

        $filters = $request->only(['status', 'sortBy', 'sortOrder', 'per_page']);
        $assignments = $this->kpiService->getManagerTeamAssignments($user->id, $filters);

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
        $assignment = KpiAssignment::with([
            'employee',
            'template.items',
            'selfReview.items.templateItem',
            'managerReview.items.templateItem'
        ])->findOrFail($id);

        // Verify access
        $user = request()->user();
        if ($user->hasRole('employee')) {
            $employee = \App\Models\Hr\Employee::where('user_id', $user->id)->first();
            if (!$employee || $assignment->employee_id !== $employee->id) {
                return response()->json([
                    'success' => false,
                    'message' => HrConstants::ERROR_UNAUTHORIZED_ACCESS,
                ], 403);
            }
        } elseif (!$user->hasRole('hr_admin') && !$user->hasRole('system_admin') && !$user->hasRole('admin')) {
            // Manager - check if employee is in their team
            $managerEmployee = \App\Models\Hr\Employee::where('user_id', $user->id)->first();
            if (!$managerEmployee || $assignment->employee->manager_id !== $managerEmployee->id) {
                return response()->json([
                    'success' => false,
                    'message' => HrConstants::ERROR_UNAUTHORIZED_ACCESS,
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    /**
     * Submit manager review.
     */
    public function submitManagerReview(Request $request, int $id): JsonResponse
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
            $review = $this->kpiService->submitManagerReview($assignment, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $review->load(['items.templateItem']),
                'message' => 'Manager review submitted successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

