<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService, private TeamAccessService $teamAccessService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get comprehensive dashboard analytics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Get tenant_id from user (each user is their own tenant)
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'period' => [
                'nullable',
                'string',
                Rule::in(['week', 'month', 'quarter', 'year'])
            ],
            'modules' => 'nullable|array',
            'modules.*' => [
                'string',
                Rule::in([
                    'contacts', 'deals', 'campaigns', 'ads', 'events',
                    'meetings', 'tasks', 'companies', 'forecast',
                    'lead_scoring', 'journeys', 'visitor_intent'
                ])
            ],
        ]);

        $filters = [
            'period' => $validated['period'] ?? 'month',
        ];

        try {
            $analytics = $this->analyticsService->getDashboardAnalytics($tenantId, $filters['period']);

            // Filter modules if requested
            if (isset($validated['modules'])) {
                $filteredAnalytics = [];
                foreach ($validated['modules'] as $module) {
                    if (isset($analytics[$module])) {
                        $filteredAnalytics[$module] = $analytics[$module];
                    }
                }
                $analytics = $filteredAnalytics;
            }

            return response()->json([
                'data' => $analytics,
                'message' => 'Dashboard analytics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve dashboard analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics for a specific module.
     */
    public function module(Request $request, string $module): JsonResponse
    {
        $user = Auth::user();
        
        // Get tenant_id from user (each user is their own tenant)
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'period' => [
                'nullable',
                'string',
                Rule::in(['week', 'month', 'quarter', 'year'])
            ],
        ]);

        $filters = [
            'period' => $validated['period'] ?? 'month',
        ];

        try {
            $analytics = $this->analyticsService->getModuleAnalytics($tenantId, $module, $filters);

            return response()->json([
                'data' => $analytics,
                'module' => $module,
                'message' => ucfirst($module) . ' analytics retrieved successfully'
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Invalid module specified',
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve module analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available analytics modules.
     */
    public function modules(): JsonResponse
    {
        return response()->json([
            'data' => [
                'contacts' => 'Contacts',
                'deals' => 'Deals',
                'campaigns' => 'Campaigns',
                'ads' => 'Ad Accounts',
                'events' => 'Events',
                'meetings' => 'Meetings',
                'tasks' => 'Tasks',
                'companies' => 'Companies',
                'forecast' => 'Sales Forecast',
                'lead_scoring' => 'Lead Scoring',
                'journeys' => 'Customer Journeys',
                'visitor_intent' => 'Visitor Intent',
            ],
            'message' => 'Available analytics modules retrieved successfully'
        ]);
    }

    /**
     * Get available time periods.
     */
    public function periods(): JsonResponse
    {
        return response()->json([
            'data' => [
                'week' => 'This Week',
                'month' => 'This Month',
                'quarter' => 'This Quarter',
                'year' => 'This Year',
            ],
            'message' => 'Available time periods retrieved successfully'
        ]);
    }

    /**
     * Get contacts analytics.
     */
    public function contacts(Request $request): JsonResponse
    {
        return $this->module($request, 'contacts');
    }

    /**
     * Get companies analytics.
     */
    public function companies(Request $request): JsonResponse
    {
        return $this->module($request, 'companies');
    }

    /**
     * Get deals analytics.
     */
    public function deals(Request $request): JsonResponse
    {
        return $this->module($request, 'deals');
    }

    /**
     * Get campaigns analytics.
     */
    public function campaigns(Request $request): JsonResponse
    {
        return $this->module($request, 'campaigns');
    }

    /**
     * Get ads analytics.
     */
    public function ads(Request $request): JsonResponse
    {
        return $this->module($request, 'ads');
    }

    /**
     * Get events analytics.
     */
    public function events(Request $request): JsonResponse
    {
        return $this->module($request, 'events');
    }

    /**
     * Get meetings analytics.
     */
    public function meetings(Request $request): JsonResponse
    {
        return $this->module($request, 'meetings');
    }

    /**
     * Get tasks analytics.
     */
    public function tasks(Request $request): JsonResponse
    {
        return $this->module($request, 'tasks');
    }

    /**
     * Get forecast analytics.
     */
    public function forecast(Request $request): JsonResponse
    {
        return $this->module($request, 'forecast');
    }

    /**
     * Get lead scoring analytics.
     */
    public function leadScoring(Request $request): JsonResponse
    {
        return $this->module($request, 'lead_scoring');
    }

    /**
     * Get journeys analytics.
     */
    public function journeys(Request $request): JsonResponse
    {
        return $this->module($request, 'journeys');
    }

    /**
     * Get visitor intent analytics.
     */
    public function visitorIntent(Request $request): JsonResponse
    {
        return $this->module($request, 'visitor_intent');
    }
}
