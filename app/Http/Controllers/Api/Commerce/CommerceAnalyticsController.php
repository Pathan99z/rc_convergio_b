<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Services\Commerce\AnalyticsService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CommerceAnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService,
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Get comprehensive analytics.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get tenant_id from header or use user's organization as fallback
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            // Validate date filters
            $dateFrom = $request->input('date_from') 
                ? Carbon::parse($request->input('date_from')) 
                : now()->subDays(30);
            
            $dateTo = $request->input('date_to') 
                ? Carbon::parse($request->input('date_to')) 
                : now();

            $teamId = $request->input('team_id');

            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'team_id' => $teamId,
            ];

            $analytics = $this->analyticsService->getAnalytics($tenantId, $filters);

            return response()->json([
                'success' => true,
                'message' => 'Analytics retrieved successfully',
                'data' => $analytics,
                'filters' => $filters,
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics retrieval error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics',
            ], 500);
        }
    }

    /**
     * Get overview statistics.
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            $dateFrom = $request->input('date_from') 
                ? Carbon::parse($request->input('date_from')) 
                : now()->subDays(30);
            
            $dateTo = $request->input('date_to') 
                ? Carbon::parse($request->input('date_to')) 
                : now();

            $teamId = $request->input('team_id');

            $stats = $this->analyticsService->getOverviewStats($tenantId, $dateFrom, $dateTo, $teamId);

            return response()->json([
                'success' => true,
                'message' => 'Overview statistics retrieved successfully',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Overview statistics error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve overview statistics',
            ], 500);
        }
    }

    /**
     * Get revenue analytics.
     */
    public function revenue(Request $request): JsonResponse
    {
        try {
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            $dateFrom = $request->input('date_from') 
                ? Carbon::parse($request->input('date_from')) 
                : now()->subDays(30);
            
            $dateTo = $request->input('date_to') 
                ? Carbon::parse($request->input('date_to')) 
                : now();

            $teamId = $request->input('team_id');

            $revenue = $this->analyticsService->getRevenueAnalytics($tenantId, $dateFrom, $dateTo, $teamId);

            return response()->json([
                'success' => true,
                'message' => 'Revenue analytics retrieved successfully',
                'data' => $revenue,
            ]);

        } catch (\Exception $e) {
            Log::error('Revenue analytics error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve revenue analytics',
            ], 500);
        }
    }

    /**
     * Get payment link analytics.
     */
    public function paymentLinks(Request $request): JsonResponse
    {
        try {
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            $dateFrom = $request->input('date_from') 
                ? Carbon::parse($request->input('date_from')) 
                : now()->subDays(30);
            
            $dateTo = $request->input('date_to') 
                ? Carbon::parse($request->input('date_to')) 
                : now();

            $teamId = $request->input('team_id');

            $analytics = $this->analyticsService->getPaymentLinkAnalytics($tenantId, $dateFrom, $dateTo, $teamId);

            return response()->json([
                'success' => true,
                'message' => 'Payment link analytics retrieved successfully',
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment link analytics error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment link analytics',
            ], 500);
        }
    }

    /**
     * Get order analytics.
     */
    public function orders(Request $request): JsonResponse
    {
        try {
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            $dateFrom = $request->input('date_from') 
                ? Carbon::parse($request->input('date_from')) 
                : now()->subDays(30);
            
            $dateTo = $request->input('date_to') 
                ? Carbon::parse($request->input('date_to')) 
                : now();

            $teamId = $request->input('team_id');

            $analytics = $this->analyticsService->getOrderAnalytics($tenantId, $dateFrom, $dateTo, $teamId);

            return response()->json([
                'success' => true,
                'message' => 'Order analytics retrieved successfully',
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            Log::error('Order analytics error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order analytics',
            ], 500);
        }
    }

    /**
     * Get transaction analytics.
     */
    public function transactions(Request $request): JsonResponse
    {
        try {
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            $dateFrom = $request->input('date_from') 
                ? Carbon::parse($request->input('date_from')) 
                : now()->subDays(30);
            
            $dateTo = $request->input('date_to') 
                ? Carbon::parse($request->input('date_to')) 
                : now();

            $teamId = $request->input('team_id');

            $analytics = $this->analyticsService->getTransactionAnalytics($tenantId, $dateFrom, $dateTo, $teamId);

            return response()->json([
                'success' => true,
                'message' => 'Transaction analytics retrieved successfully',
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            Log::error('Transaction analytics error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transaction analytics',
            ], 500);
        }
    }

    /**
     * Get trends data.
     */
    public function trends(Request $request): JsonResponse
    {
        try {
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            $dateFrom = $request->input('date_from') 
                ? Carbon::parse($request->input('date_from')) 
                : now()->subDays(30);
            
            $dateTo = $request->input('date_to') 
                ? Carbon::parse($request->input('date_to')) 
                : now();

            $teamId = $request->input('team_id');

            $trends = $this->analyticsService->getTrendsData($tenantId, $dateFrom, $dateTo, $teamId);

            return response()->json([
                'success' => true,
                'message' => 'Trends data retrieved successfully',
                'data' => $trends,
            ]);

        } catch (\Exception $e) {
            Log::error('Trends data error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trends data',
            ], 500);
        }
    }

    /**
     * Export analytics data.
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            $format = $request->input('format', 'json'); // json, csv, xlsx
            $dateFrom = $request->input('date_from') 
                ? Carbon::parse($request->input('date_from')) 
                : now()->subDays(30);
            
            $dateTo = $request->input('date_to') 
                ? Carbon::parse($request->input('date_to')) 
                : now();

            $teamId = $request->input('team_id');

            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'team_id' => $teamId,
            ];

            $analytics = $this->analyticsService->getAnalytics($tenantId, $filters);

            // For now, return JSON. In production, you might want to generate CSV/Excel files
            return response()->json([
                'success' => true,
                'message' => 'Analytics data exported successfully',
                'data' => $analytics,
                'export_info' => [
                    'format' => $format,
                    'filters' => $filters,
                    'exported_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics export error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export analytics data',
            ], 500);
        }
    }
}
