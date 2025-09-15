<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ForecastController extends Controller
{
    protected ForecastService $forecastService;

    public function __construct(ForecastService $forecastService)
    {
        $this->forecastService = $forecastService;
    }

    /**
     * Get sales forecast for the authenticated user's tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'timeframe' => [
                'nullable',
                'string',
                Rule::in(['monthly', 'quarterly', 'yearly'])
            ],
            'include_trends' => 'nullable|boolean',
            'include_pipeline_breakdown' => 'nullable|boolean',
            'include_accuracy' => 'nullable|boolean',
        ]);

        $timeframe = $validated['timeframe'] ?? 'monthly';
        $includeTrends = $validated['include_trends'] ?? false;
        $includePipelineBreakdown = $validated['include_pipeline_breakdown'] ?? false;
        $includeAccuracy = $validated['include_accuracy'] ?? false;

        try {
            $forecast = $this->forecastService->generateForecast($tenantId, $timeframe);

            // Add additional data if requested
            if ($includeTrends) {
                $forecast['trends'] = $this->forecastService->getForecastTrends($tenantId, 6);
            }

            if ($includePipelineBreakdown) {
                $forecast['pipeline_breakdown'] = $this->forecastService->getForecastByPipeline($tenantId, $timeframe);
            }

            if ($includeAccuracy) {
                $forecast['accuracy'] = $this->forecastService->getForecastAccuracy($tenantId, 3);
            }

            return response()->json([
                'data' => $forecast,
                'message' => 'Sales forecast retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate sales forecast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get multi-timeframe forecast.
     */
    public function multiTimeframe(): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $forecast = $this->forecastService->getMultiTimeframeForecast($tenantId);

            return response()->json([
                'data' => $forecast,
                'message' => 'Multi-timeframe forecast retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate multi-timeframe forecast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get forecast trends over time.
     */
    public function trends(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'months' => 'nullable|integer|min:1|max:12',
        ]);

        $months = $validated['months'] ?? 6;

        try {
            $trends = $this->forecastService->getForecastTrends($tenantId, $months);

            return response()->json([
                'data' => [
                    'trends' => $trends,
                    'period_months' => $months,
                ],
                'message' => 'Forecast trends retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate forecast trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get forecast by pipeline.
     */
    public function byPipeline(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'timeframe' => [
                'nullable',
                'string',
                Rule::in(['monthly', 'quarterly', 'yearly'])
            ],
        ]);

        $timeframe = $validated['timeframe'] ?? 'monthly';

        try {
            $forecast = $this->forecastService->getForecastByPipeline($tenantId, $timeframe);

            return response()->json([
                'data' => $forecast,
                'message' => 'Pipeline forecast retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate pipeline forecast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get forecast accuracy metrics.
     */
    public function accuracy(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'months' => 'nullable|integer|min:1|max:12',
        ]);

        $months = $validated['months'] ?? 3;

        try {
            $accuracy = $this->forecastService->getForecastAccuracy($tenantId, $months);

            return response()->json([
                'data' => $accuracy,
                'message' => 'Forecast accuracy retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate forecast accuracy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available timeframes.
     */
    public function timeframes(): JsonResponse
    {
        return response()->json([
            'data' => [
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'yearly' => 'Yearly',
            ],
            'message' => 'Available timeframes retrieved successfully'
        ]);
    }
}
