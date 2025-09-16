<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ForecastEnhancementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ForecastEnhancementController extends Controller
{
    protected ForecastEnhancementService $enhancementService;

    public function __construct(ForecastEnhancementService $enhancementService)
    {
        $this->enhancementService = $enhancementService;
    }

    /**
     * Export forecast data.
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'timeframe' => ['sometimes', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'include_trends' => 'sometimes|boolean',
            'include_pipeline_breakdown' => 'sometimes|boolean',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->exportForecast($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Forecast data exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export forecast data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import forecast data.
     */
    public function import(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,json|max:10240', // 10MB max
        ]);

        try {
            $result = $this->enhancementService->importForecast($tenantId, $validated['file']);

            return response()->json([
                'success' => true,
                'message' => 'Forecast data imported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import forecast data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get forecast reports.
     */
    public function reports(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'type' => ['sometimes', Rule::in(['summary', 'detailed', 'trends', 'accuracy'])],
            'timeframe' => ['sometimes', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->generateReports($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Forecast reports generated successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate forecast reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export forecast data in specific format.
     */
    public function exportFormat(Request $request, string $format): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'timeframe' => ['sometimes', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'include_trends' => 'sometimes|boolean',
            'include_pipeline_breakdown' => 'sometimes|boolean',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        // Validate format
        if (!in_array($format, ['csv', 'excel', 'json', 'pdf'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid format. Supported formats: csv, excel, json, pdf'
            ], 400);
        }

        $validated['format'] = $format;

        try {
            $result = $this->enhancementService->exportForecast($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Forecast data exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export forecast data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

