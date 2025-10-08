<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SequenceEnrollmentResource;
use App\Http\Resources\SequenceLogResource;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\SequenceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\SequenceStep;
use App\Jobs\ExecuteSequenceStepJob;
use Illuminate\Support\Facades\Log;


class SequenceEnrollmentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Get logs for a specific sequence
     */
    public function logs(Sequence $sequence): AnonymousResourceCollection
    {
        $this->authorize('view', $sequence);

        $logs = SequenceLog::query()
            ->whereHas('enrollment', function ($query) use ($sequence) {
                $query->where('sequence_id', $sequence->id);
            })
            ->with(['enrollment', 'step'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return SequenceLogResource::collection($logs);
    }

    /**
     * Get logs for a specific enrollment
     */
    public function enrollmentLogs(int $enrollment): AnonymousResourceCollection
    {
        $logs = SequenceLog::query()
            ->where('enrollment_id', $enrollment)
            ->with(['enrollment', 'step'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return SequenceLogResource::collection($logs);
    }

    /**
     * Enroll a contact/deal/company in a sequence
     */
    public function enroll(Request $request, Sequence $sequence): JsonResponse
    {
        $this->authorize('view', $sequence);

        $request->validate([
            'target_type' => 'required|string|in:contact,deal,company',
            'target_id' => 'required|integer',
            'target_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $data = $request->only(['target_type', 'target_id', 'target_name', 'notes']);
        $data['sequence_id'] = $sequence->id;
        $data['tenant_id'] = $sequence->tenant_id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'active';
        $data['started_at'] = now();
        $data['current_step'] = 1;

        $enrollment = SequenceEnrollment::create($data);

        // 🚀 AUTOMATIC SEQUENCE EXECUTION - Like campaigns!
        $this->startSequenceExecution($enrollment);


        return response()->json([
            'message' => 'Enrollment created successfully.',
            'data' => new SequenceEnrollmentResource($enrollment)
        ]);
    }

    /**
     * Pause an enrollment
     */
    public function pause(Request $request, int $enrollment): JsonResponse
    {
        // Validate enrollment ID
        if ($enrollment <= 0) {
            return response()->json([
                'message' => 'Invalid enrollment ID provided.',
                'error' => 'The enrollment ID must be a positive integer.'
            ], 400);
        }

        $enrollmentModel = SequenceEnrollment::findOrFail($enrollment);
        $this->authorize('update', $enrollmentModel);

        $enrollmentModel->update([
            'status' => 'paused',
            'updated_by' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Enrollment paused successfully.',
            'data' => new SequenceEnrollmentResource($enrollmentModel->fresh())
        ]);
    }

    /**
     * Resume an enrollment
     */
    public function resume(Request $request, int $enrollment): JsonResponse
    {
        // Validate enrollment ID
        if ($enrollment <= 0) {
            return response()->json([
                'message' => 'Invalid enrollment ID provided.',
                'error' => 'The enrollment ID must be a positive integer.'
            ], 400);
        }

        $enrollmentModel = SequenceEnrollment::findOrFail($enrollment);
        $this->authorize('update', $enrollmentModel);

        $enrollmentModel->update([
            'status' => 'active',
            'updated_by' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Enrollment resumed successfully.',
            'data' => new SequenceEnrollmentResource($enrollmentModel->fresh())
        ]);
    }

    /**
     * Cancel an enrollment
     */
    public function cancel(Request $request, int $enrollment): JsonResponse
    {
        // Validate enrollment ID
        if ($enrollment <= 0) {
            return response()->json([
                'message' => 'Invalid enrollment ID provided.',
                'error' => 'The enrollment ID must be a positive integer.'
            ], 400);
        }

        $enrollmentModel = SequenceEnrollment::findOrFail($enrollment);
        $this->authorize('update', $enrollmentModel);

        $enrollmentModel->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'updated_by' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Enrollment canceled successfully.',
            'data' => new SequenceEnrollmentResource($enrollmentModel->fresh())
        ]);
    }

    /**
     * Start automatic sequence execution
     */
    private function startSequenceExecution(SequenceEnrollment $enrollment): void
    {
        try {
            // Get the first step of the sequence
            $firstStep = SequenceStep::where('sequence_id', $enrollment->sequence_id)
                ->where('step_order', 1)
                ->first();

            if (!$firstStep) {
                Log::warning('No first step found for sequence', [
                    'sequence_id' => $enrollment->sequence_id,
                    'enrollment_id' => $enrollment->id
                ]);
                return;
            }

            // Check if sequence is active
            if (!$enrollment->sequence->is_active) {
                Log::info('Sequence is inactive, skipping execution', [
                    'sequence_id' => $enrollment->sequence_id,
                    'enrollment_id' => $enrollment->id
                ]);
                return;
            }

            // Dispatch the first step execution job
            ExecuteSequenceStepJob::dispatch($enrollment->id, $firstStep->id);

            Log::info('Sequence execution started automatically', [
                'enrollment_id' => $enrollment->id,
                'sequence_id' => $enrollment->sequence_id,
                'first_step_id' => $firstStep->id,
                'target_type' => $enrollment->target_type,
                'target_id' => $enrollment->target_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start sequence execution', [
                'enrollment_id' => $enrollment->id,
                'sequence_id' => $enrollment->sequence_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
