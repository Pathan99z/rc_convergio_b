<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsEnhancementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AnalyticsEnhancementController extends Controller
{
    protected AnalyticsEnhancementService $enhancementService;

    public function __construct(AnalyticsEnhancementService $enhancementService)
    {
        $this->enhancementService = $enhancementService;
    }

    /**
     * Export analytics data.
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'modules' => 'sometimes|array',
            'modules.*' => [
                'string',
                Rule::in([
                    'contacts', 'deals', 'campaigns', 'ads', 'events',
                    'meetings', 'tasks', 'companies', 'forecast',
                    'lead_scoring', 'journeys', 'visitor_intent'
                ])
            ],
            'period' => ['sometimes', Rule::in(['week', 'month', 'quarter', 'year'])],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->exportAnalytics($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Analytics data exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export analytics data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics reports.
     */
    public function reports(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'type' => ['sometimes', Rule::in(['summary', 'detailed', 'comparison', 'trends'])],
            'modules' => 'sometimes|array',
            'modules.*' => [
                'string',
                Rule::in([
                    'contacts', 'deals', 'campaigns', 'ads', 'events',
                    'meetings', 'tasks', 'companies', 'forecast',
                    'lead_scoring', 'journeys', 'visitor_intent'
                ])
            ],
            'period' => ['sometimes', Rule::in(['week', 'month', 'quarter', 'year'])],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->generateReports($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Analytics reports generated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate analytics reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export specific module analytics.
     */
    public function exportModule(Request $request, string $module): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'period' => ['sometimes', Rule::in(['week', 'month', 'quarter', 'year'])],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        // Validate module
        $validModules = [
            'contacts', 'deals', 'campaigns', 'ads', 'events',
            'meetings', 'tasks', 'companies', 'forecast',
            'lead_scoring', 'journeys', 'visitor_intent'
        ];

        if (!in_array($module, $validModules)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid module. Valid modules: ' . implode(', ', $validModules)
            ], 400);
        }

        $validated['modules'] = [$module];

        try {
            $result = $this->enhancementService->exportAnalytics($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Module analytics exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export module analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule a report.
     */
    public function scheduleReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['required', Rule::in(['scheduled', 'on_demand'])],
            'config' => 'required|array',
            'schedule' => 'required_if:type,scheduled|array',
            'format' => ['required', Rule::in(['json', 'csv', 'excel', 'pdf'])],
        ]);

        try {
            $result = $this->enhancementService->scheduleReport($tenantId, $validated, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Report scheduled successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scheduled reports.
     */
    public function scheduledReports(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['active', 'paused', 'completed', 'failed'])],
            'type' => ['sometimes', Rule::in(['scheduled', 'on_demand'])],
        ]);

        try {
            $result = $this->enhancementService->getScheduledReports($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Scheduled reports retrieved successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve scheduled reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete scheduled report.
     */
    public function deleteScheduledReport(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->enhancementService->deleteScheduledReport($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Scheduled report deleted successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete scheduled report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

