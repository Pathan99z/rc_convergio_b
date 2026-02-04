<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Http\Resources\Hr\PerformanceNoteResource;
use App\Models\Hr\Employee;
use App\Models\Hr\PerformanceNote;
use App\Services\Hr\PerformanceNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PerformanceNoteController extends Controller
{
    public function __construct(
        private PerformanceNoteService $performanceNoteService
    ) {}

    /**
     * List performance notes for an employee.
     */
    public function index(int $employeeId, Request $request): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $filters = [
            'sortBy' => $request->query('sortBy', 'created_at'),
            'sortOrder' => $request->query('sortOrder', 'desc'),
        ];

        $perPage = min((int) $request->query('per_page', 15), 100);
        $notes = $this->performanceNoteService->getPerformanceNotes($employeeId, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => PerformanceNoteResource::collection($notes->items()),
            'meta' => [
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
            ],
        ]);
    }

    /**
     * Create performance note.
     */
    public function store(int $employeeId, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'note' => 'required|string|max:5000',
            'visibility' => 'required|in:' . implode(',', [
                HrConstants::VISIBILITY_HR_ONLY,
                HrConstants::VISIBILITY_MANAGER,
                HrConstants::VISIBILITY_EMPLOYEE,
            ]),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $note = $this->performanceNoteService->createPerformanceNote($employeeId, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => new PerformanceNoteResource($note),
                'message' => HrConstants::SUCCESS_PERFORMANCE_NOTE_CREATED,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get performance note details.
     */
    public function show(int $employeeId, int $id): JsonResponse
    {
        $note = PerformanceNote::with(['employee', 'creator'])
            ->where('employee_id', $employeeId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new PerformanceNoteResource($note),
        ]);
    }

    /**
     * Update performance note.
     */
    public function update(int $employeeId, int $id, Request $request): JsonResponse
    {
        $note = PerformanceNote::where('employee_id', $employeeId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'note' => 'sometimes|required|string|max:5000',
            'visibility' => 'sometimes|required|in:' . implode(',', [
                HrConstants::VISIBILITY_HR_ONLY,
                HrConstants::VISIBILITY_MANAGER,
                HrConstants::VISIBILITY_EMPLOYEE,
            ]),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $note = $this->performanceNoteService->updatePerformanceNote($note, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => new PerformanceNoteResource($note),
                'message' => 'Performance note updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete performance note.
     */
    public function destroy(int $employeeId, int $id): JsonResponse
    {
        $note = PerformanceNote::where('employee_id', $employeeId)->findOrFail($id);

        try {
            $this->performanceNoteService->deletePerformanceNote($note);

            return response()->json([
                'success' => true,
                'message' => 'Performance note deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}


