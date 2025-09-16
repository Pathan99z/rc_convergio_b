<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BuyerIntentEnhancementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BuyerIntentEnhancementController extends Controller
{
    protected BuyerIntentEnhancementService $enhancementService;

    public function __construct(BuyerIntentEnhancementService $enhancementService)
    {
        $this->enhancementService = $enhancementService;
    }

    /**
     * Export tracking/intent data.
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'event_type' => 'sometimes|string',
            'source' => 'sometimes|string',
            'min_score' => 'sometimes|integer|min:0|max:100',
            'max_score' => 'sometimes|integer|min:0|max:100',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->exportTrackingData($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Tracking data exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export tracking data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete tracking events.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'event_ids' => 'required|array|min:1|max:100',
            'event_ids.*' => 'integer|exists:intent_events,id',
            'delete_visitor_intents' => 'sometimes|boolean',
        ]);

        try {
            $result = $this->enhancementService->bulkDeleteEvents($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Bulk delete operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk delete events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tracking reports.
     */
    public function reports(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'type' => ['sometimes', Rule::in(['summary', 'detailed', 'trends', 'intent_levels'])],
            'event_type' => 'sometimes|string',
            'source' => 'sometimes|string',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->generateReports($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Tracking reports generated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tracking reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update tracking settings.
     */
    public function settings(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'tracking_enabled' => 'sometimes|boolean',
            'intent_scoring_enabled' => 'sometimes|boolean',
            'auto_lead_scoring' => 'sometimes|boolean',
            'tracking_domains' => 'sometimes|array',
            'tracking_domains.*' => 'string|url',
            'intent_thresholds' => 'sometimes|array',
            'intent_thresholds.low' => 'integer|min:0|max:100',
            'intent_thresholds.medium' => 'integer|min:0|max:100',
            'intent_thresholds.high' => 'integer|min:0|max:100',
            'retention_days' => 'sometimes|integer|min:1|max:365',
        ]);

        try {
            $result = $this->enhancementService->updateTrackingSettings($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Tracking settings updated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tracking settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

