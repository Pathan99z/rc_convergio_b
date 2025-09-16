<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Services\JourneysEnhancementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class JourneysEnhancementController extends Controller
{
    protected JourneysEnhancementService $enhancementService;

    public function __construct(JourneysEnhancementService $enhancementService)
    {
        $this->enhancementService = $enhancementService;
    }

    /**
     * Bulk delete journeys.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'journey_ids' => 'required|array|min:1|max:20',
            'journey_ids.*' => 'integer|exists:journeys,id',
        ]);

        try {
            $result = $this->enhancementService->bulkDeleteJourneys($tenantId, $validated['journey_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk delete operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk delete journeys',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk activate journeys.
     */
    public function bulkActivate(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'journey_ids' => 'required|array|min:1|max:20',
            'journey_ids.*' => 'integer|exists:journeys,id',
        ]);

        try {
            $result = $this->enhancementService->bulkActivateJourneys($tenantId, $validated['journey_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk activate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk activate journeys',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk pause journeys.
     */
    public function bulkPause(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'journey_ids' => 'required|array|min:1|max:20',
            'journey_ids.*' => 'integer|exists:journeys,id',
        ]);

        try {
            $result = $this->enhancementService->bulkPauseJourneys($tenantId, $validated['journey_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk pause operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk pause journeys',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export journeys.
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused', 'archived'])],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->exportJourneys($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Journeys exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export journeys',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import journeys.
     */
    public function import(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,json|max:10240', // 10MB max
        ]);

        try {
            $result = $this->enhancementService->importJourneys($tenantId, $validated['file'], $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Journeys imported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import journeys',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export single journey.
     */
    public function exportSingle(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->enhancementService->exportSingleJourney($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Journey exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export journey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pause single journey.
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->enhancementService->pauseJourney($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Journey paused successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to pause journey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume single journey.
     */
    public function resume(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->enhancementService->resumeJourney($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Journey resumed successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resume journey',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

