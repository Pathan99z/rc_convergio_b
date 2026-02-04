<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Http\Resources\Hr\LeaveRequestResource;
use App\Models\Hr\LeaveRequest;
use App\Services\Hr\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    public function __construct(
        private LeaveService $leaveService
    ) {}

    /**
     * Get leave balances.
     */
    public function balances(Request $request): JsonResponse
    {
        $employeeId = $request->query('employee_id');
        
        $balances = $this->leaveService->getLeaveBalances($employeeId);

        return response()->json([
            'success' => true,
            'data' => $balances,
        ]);
    }

    /**
     * Adjust leave balance (HR Admin only).
     */
    public function adjustBalance(Request $request): JsonResponse
    {
        $this->authorize('adjustBalance', LeaveRequest::class);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:hr_employees,id',
            'leave_type_id' => 'required|exists:hr_leave_types,id',
            'type' => 'required|in:add,deduct,set',
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
            'effective_date' => 'required|date',
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
            $balance = $this->leaveService->adjustLeaveBalance(
                $data['employee_id'],
                $data['leave_type_id'],
                $data['type'],
                $data['amount'],
                $data['reason']
            );

            return response()->json([
                'success' => true,
                'data' => $balance,
                'message' => HrConstants::SUCCESS_LEAVE_BALANCE_ADJUSTED,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * List leave requests.
     */
    public function requests(Request $request): JsonResponse
    {
        $filters = [
            'employee_id' => $request->query('employee_id'),
            'leave_type_id' => $request->query('leave_type_id'),
            'status' => $request->query('status'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'sortBy' => $request->query('sortBy', 'created_at'),
            'sortOrder' => $request->query('sortOrder', 'desc'),
        ];

        $perPage = min((int) $request->query('per_page', 15), 100);
        $requests = $this->leaveService->getLeaveRequests($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => LeaveRequestResource::collection($requests->items()),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Create leave request.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:hr_employees,id',
            'leave_type_id' => 'required|exists:hr_leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days_requested' => 'required|numeric|min:0.5',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $leaveRequest = $this->leaveService->createLeaveRequest($validator->validated());

            return response()->json([
                'success' => true,
                'data' => new LeaveRequestResource($leaveRequest),
                'message' => HrConstants::SUCCESS_LEAVE_REQUEST_CREATED,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get leave request details.
     */
    public function show(int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::with(['employee', 'leaveType', 'approvedBy'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new LeaveRequestResource($leaveRequest),
        ]);
    }

    /**
     * Cancel leave request.
     */
    public function cancel(int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        try {
            $leaveRequest = $this->leaveService->cancelLeaveRequest($leaveRequest);

            return response()->json([
                'success' => true,
                'data' => new LeaveRequestResource($leaveRequest),
                'message' => 'Leave request cancelled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get leave calendar (Manager view).
     */
    public function calendar(Request $request): JsonResponse
    {
        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
        ];

        $calendar = $this->leaveService->getLeaveCalendar($filters);

        return response()->json([
            'success' => true,
            'data' => $calendar,
        ]);
    }

    /**
     * Get leave types.
     */
    public function types(): JsonResponse
    {
        $types = $this->leaveService->getLeaveTypes();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }
}


