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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $userId = $request->user()->id;
        
        $filters = [
            'tenant_id' => $tenantId,
            'q' => $request->get('q'),
            'name' => $request->get('name'),
            'target_type' => $request->get('target_type'),
            'is_active' => $request->get('is_active'),
            'sortBy' => $request->get('sortBy', 'created_at'),
            'sortOrder' => $request->get('sortOrder', 'desc'),
        ];

        // Create cache key with tenant and user isolation
        $cacheKey = "sequences_list_{$tenantId}_{$userId}_" . md5(serialize($filters + ['per_page' => $request->get('per_page', 15)]));
        
        // Cache sequences list for 5 minutes (300 seconds)
        $sequences = Cache::remember($cacheKey, 300, function () use ($filters, $request) {
            return $this->sequenceService->getSequences($filters, $request->get('per_page', 15));
        });

        return SequenceResource::collection($sequences);
    }

    /**
     * Store a newly created sequence.
     */
    public function store(StoreSequenceRequest $request): JsonResource
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $data['tenant_id'] = $tenantId;

        $sequence = $this->sequenceService->createSequence($data);

        // Clear cache after creating sequence
        $this->clearSequencesCache($tenantId, $request->user()->id);

        return new SequenceResource($sequence);
    }

    /**
     * Display the specified sequence.
     */
    public function show(Sequence $sequence): JsonResource
    {
        $this->authorize('view', $sequence);

        $tenantId = $sequence->tenant_id;
        $userId = auth()->id();
        
        // Create cache key with tenant, user, and sequence ID isolation
        $cacheKey = "sequence_show_{$tenantId}_{$userId}_{$sequence->id}";
        
        // Cache sequence detail for 15 minutes (900 seconds)
        $sequence = Cache::remember($cacheKey, 900, function () use ($sequence) {
            return $this->sequenceService->getSequenceWithSteps($sequence);
        });

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

        // Clear cache after updating sequence
        $this->clearSequencesCache($sequence->tenant_id, $request->user()->id);
        Cache::forget("sequence_show_{$sequence->tenant_id}_{$request->user()->id}_{$sequence->id}");

        return new SequenceResource($sequence);
    }

    /**
     * Remove the specified sequence.
     */
    public function destroy(Sequence $sequence): JsonResponse
    {
        $this->authorize('delete', $sequence);

        $tenantId = $sequence->tenant_id;
        $userId = auth()->id();
        $sequenceId = $sequence->id;

        $this->sequenceService->deleteSequence($sequence);

        // Clear cache after deleting sequence
        $this->clearSequencesCache($tenantId, $userId);
        Cache::forget("sequence_show_{$tenantId}_{$userId}_{$sequenceId}");

        return response()->json([
            'message' => 'Sequence deleted successfully.'
        ]);
    }

    /**
     * Clear sequences cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearSequencesCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for sequences list
            $commonParams = [
                '',
                md5(serialize(['sortBy' => 'created_at', 'sortOrder' => 'desc', 'per_page' => 15])),
                md5(serialize(['sortBy' => 'updated_at', 'sortOrder' => 'desc', 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("sequences_list_{$tenantId}_{$userId}_{$params}");
            }

            Log::info('Sequences cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear sequences cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
