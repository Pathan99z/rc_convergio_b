<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeInductionAssignment;
use App\Services\Hr\InductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeInductionController extends Controller
{
    public function __construct(
        private InductionService $inductionService
    ) {}

    /**
     * Get employee's induction list and progress.
     * 
     * GET /api/employee/induction
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        
        // Find employee by user_id
        $employee = Employee::where('user_id', $currentUser->id)->first();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found for this user',
            ], 404);
        }

        // Get progress
        $progress = $this->inductionService->getEmployeeInductionProgress($employee);

        // Get assignments with content
        $assignments = EmployeeInductionAssignment::where('employee_id', $employee->id)
            ->with(['content.creator'])
            ->orderBy('assigned_at', 'desc')
            ->get();

        // Format assignments
        $items = $assignments->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'assignment_id' => $assignment->id,
                'content_id' => $assignment->induction_content_id,
                'title' => $assignment->content->title ?? 'N/A',
                'description' => $assignment->content->description ?? null,
                'category' => $assignment->content->category ?? null,
                'content_type' => $assignment->content->content_type ?? null,
                'status' => $assignment->status,
                'assigned_at' => $assignment->assigned_at,
                'started_at' => $assignment->started_at,
                'completed_at' => $assignment->completed_at,
                'due_date' => $assignment->due_date,
                'is_overdue' => $assignment->is_overdue,
                'is_mandatory' => $assignment->content->is_mandatory ?? false,
                'estimated_time' => $assignment->content->estimated_time ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'progress' => $progress,
                'items' => $items,
            ],
        ]);
    }

    /**
     * View specific induction content.
     * 
     * GET /api/employee/induction/{assignmentId}/view
     */
    public function view(int $assignmentId): JsonResponse
    {
        $currentUser = request()->user();
        
        $assignment = EmployeeInductionAssignment::with(['content.creator', 'employee'])
            ->findOrFail($assignmentId);

        // Verify ownership
        if ($assignment->employee->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this assignment',
            ], 403);
        }

        // Auto-mark as started if still pending
        if ($assignment->status === 'pending') {
            $assignment = $this->inductionService->markAssignmentAsStarted($assignment);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'assignment_id' => $assignment->id,
                'content' => [
                    'id' => $assignment->content->id,
                    'title' => $assignment->content->title,
                    'description' => $assignment->content->description,
                    'content_type' => $assignment->content->content_type,
                    'file_url' => $assignment->content->file_url,
                    'video_url' => $assignment->content->video_url,
                    'support_documents' => $assignment->content->support_documents,
                    'estimated_time' => $assignment->content->estimated_time,
                ],
                'status' => $assignment->status,
                'started_at' => $assignment->started_at,
                'due_date' => $assignment->due_date,
                'is_mandatory' => $assignment->content->is_mandatory,
            ],
        ]);
    }

    /**
     * Mark assignment as started.
     * 
     * POST /api/employee/induction/{assignmentId}/start
     */
    public function start(int $assignmentId): JsonResponse
    {
        $currentUser = request()->user();
        
        $assignment = EmployeeInductionAssignment::with(['employee'])->findOrFail($assignmentId);

        // Verify ownership
        if ($assignment->employee->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this assignment',
            ], 403);
        }

        try {
            $assignment = $this->inductionService->markAssignmentAsStarted($assignment);

            return response()->json([
                'success' => true,
                'message' => 'Content viewing started',
                'data' => [
                    'assignment_id' => $assignment->id,
                    'started_at' => $assignment->started_at,
                    'status' => $assignment->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Acknowledge/complete assignment.
     * 
     * POST /api/employee/induction/{assignmentId}/acknowledge
     * Body: { notes: "optional notes" }
     */
    public function acknowledge(Request $request, int $assignmentId): JsonResponse
    {
        $currentUser = request()->user();
        
        $assignment = EmployeeInductionAssignment::with(['employee'])->findOrFail($assignmentId);

        // Verify ownership
        if ($assignment->employee->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this assignment',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
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
            $assignment = $this->inductionService->acknowledgeAssignment(
                $assignment,
                $validator->validated()['notes'] ?? null
            );

            // Get updated progress
            $progress = $this->inductionService->getEmployeeInductionProgress($assignment->employee);

            return response()->json([
                'success' => true,
                'message' => 'Content acknowledged successfully',
                'data' => [
                    'assignment_id' => $assignment->id,
                    'completed_at' => $assignment->completed_at,
                    'status' => $assignment->status,
                ],
                'progress' => $progress,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

