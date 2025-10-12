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
    public function export(Request $request)
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
            $format = $validated['format'] ?? 'json';
            
            // For Excel format, return a proper Excel file
            if ($format === 'excel') {
                return $this->exportExcelFile($tenantId, $validated);
            }
            
            // For other formats, return JSON response with download URL
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
    public function exportFormat(Request $request, string $format)
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
            // For Excel format, return a proper Excel file
            if ($format === 'excel') {
                return $this->exportExcelFile($tenantId, $validated);
            }
            
            // For other formats, return JSON response with download URL
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
     * Export Excel file directly.
     */
    private function exportExcelFile(int $tenantId, array $validated)
    {
        // Get forecast data
        $forecast = $this->enhancementService->getForecastData($tenantId, $validated);
        
        // Create CSV content (Excel can read CSV files)
        $csvContent = $this->createCSVContent($forecast);
        
        // Generate filename
        $filename = 'forecast_summary_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        // Return file download response
        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Create CSV content from forecast data.
     */
    private function createCSVContent(array $forecast): string
    {
        $csv = [];
        
        // Add forecast summary
        $csv[] = ['FORECAST SUMMARY'];
        $csv[] = ['Timeframe', $forecast['timeframe'] ?? 'N/A'];
        $csv[] = ['Total Deals', $forecast['total_deals'] ?? 0];
        $csv[] = ['Projected Value', '$' . number_format($forecast['projected_value'] ?? 0, 2)];
        $csv[] = ['Probability Weighted', '$' . number_format($forecast['probability_weighted'] ?? 0, 2)];
        $csv[] = ['Average Deal Size', '$' . number_format($forecast['average_deal_size'] ?? 0, 2)];
        $csv[] = ['Average Probability', ($forecast['average_probability'] ?? 0) . '%'];
        $csv[] = ['Conversion Rate', ($forecast['conversion_rate'] ?? 0) . '%'];
        $csv[] = []; // Empty row
        
        // Add stage breakdown
        if (isset($forecast['stage_breakdown']) && !empty($forecast['stage_breakdown'])) {
            $csv[] = ['STAGE BREAKDOWN'];
            $csv[] = ['Stage ID', 'Count', 'Total Value', 'Probability Weighted', 'Average Probability'];
            foreach ($forecast['stage_breakdown'] as $stageId => $stageData) {
                $csv[] = [
                    $stageId,
                    $stageData['count'] ?? 0,
                    '$' . number_format($stageData['total_value'] ?? 0, 2),
                    '$' . number_format($stageData['probability_weighted'] ?? 0, 2),
                    ($stageData['average_probability'] ?? 0) . '%'
                ];
            }
            $csv[] = []; // Empty row
        }
        
        // Add owner breakdown
        if (isset($forecast['owner_breakdown']) && !empty($forecast['owner_breakdown'])) {
            $csv[] = ['OWNER BREAKDOWN'];
            $csv[] = ['Owner ID', 'Count', 'Total Value', 'Probability Weighted', 'Average Probability'];
            foreach ($forecast['owner_breakdown'] as $ownerId => $ownerData) {
                $csv[] = [
                    $ownerId,
                    $ownerData['count'] ?? 0,
                    '$' . number_format($ownerData['total_value'] ?? 0, 2),
                    '$' . number_format($ownerData['probability_weighted'] ?? 0, 2),
                    ($ownerData['average_probability'] ?? 0) . '%'
                ];
            }
            $csv[] = []; // Empty row
        }
        
        // Add trends if available
        if (isset($forecast['trends']) && !empty($forecast['trends'])) {
            $csv[] = ['TRENDS DATA'];
            $csv[] = ['Month', 'Month Name', 'Total Deals', 'Projected Value', 'Probability Weighted'];
            foreach ($forecast['trends'] as $trend) {
                $csv[] = [
                    $trend['month'] ?? 'N/A',
                    $trend['month_name'] ?? 'N/A',
                    $trend['total_deals'] ?? 0,
                    '$' . number_format($trend['projected_value'] ?? 0, 2),
                    '$' . number_format($trend['probability_weighted'] ?? 0, 2)
                ];
            }
            $csv[] = []; // Empty row
        }
        
        // Add pipeline breakdown if available
        if (isset($forecast['pipeline_breakdown']) && !empty($forecast['pipeline_breakdown'])) {
            $csv[] = ['PIPELINE BREAKDOWN'];
            $csv[] = ['Pipeline ID', 'Pipeline Name', 'Count', 'Total Value', 'Probability Weighted', 'Average Probability'];
            foreach ($forecast['pipeline_breakdown'] as $pipelineId => $pipelineData) {
                $csv[] = [
                    $pipelineId,
                    $pipelineData['pipeline_name'] ?? 'N/A',
                    $pipelineData['count'] ?? 0,
                    '$' . number_format($pipelineData['total_value'] ?? 0, 2),
                    '$' . number_format($pipelineData['probability_weighted'] ?? 0, 2),
                    ($pipelineData['average_probability'] ?? 0) . '%'
                ];
            }
        }
        
        // Convert to CSV string
        $output = '';
        foreach ($csv as $row) {
            $output .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        return $output;
    }
}

