<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sequences\StoreSequenceRequest;
use App\Http\Requests\Sequences\UpdateSequenceRequest;
use App\Http\Resources\SequenceResource;
use App\Models\Sequence;
use App\Services\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class SequencesController extends Controller
{
    public function __construct(
        private SequenceService $sequenceService
    ) {}

    /**
     * Display a listing of sequences.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'tenant_id' => optional($request->user())->tenant_id ?? $request->user()->id,
            'q' => $request->get('q'),
            'name' => $request->get('name'),
            'target_type' => $request->get('target_type'),
            'is_active' => $request->get('is_active'),
            'sortBy' => $request->get('sortBy', 'created_at'),
            'sortOrder' => $request->get('sortOrder', 'desc'),
        ];

        $sequences = $this->sequenceService->getSequences($filters, $request->get('per_page', 15));

        return SequenceResource::collection($sequences);
    }

    /**
     * Store a newly created sequence.
     */
    public function store(StoreSequenceRequest $request): JsonResource
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['tenant_id'] = optional($request->user())->tenant_id ?? $request->user()->id;

        $sequence = $this->sequenceService->createSequence($data);

        return new SequenceResource($sequence);
    }

    /**
     * Display the specified sequence.
     */
    public function show(Sequence $sequence): JsonResource
    {
        $this->authorize('view', $sequence);

        $sequence = $this->sequenceService->getSequenceWithSteps($sequence);

        return new SequenceResource($sequence);
    }

    /**
     * Update the specified sequence.
     */
    public function update(UpdateSequenceRequest $request, Sequence $sequence): JsonResource
    {
        $this->authorize('update', $sequence);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $sequence = $this->sequenceService->updateSequence($sequence, $data);

        return new SequenceResource($sequence);
    }

    /**
     * Remove the specified sequence.
     */
    public function destroy(Sequence $sequence): JsonResponse
    {
        $this->authorize('delete', $sequence);

        $this->sequenceService->deleteSequence($sequence);

        return response()->json([
            'message' => 'Sequence deleted successfully.'
        ]);
    }
}
