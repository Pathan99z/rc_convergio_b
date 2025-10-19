<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\Subscription;
use App\Models\Commerce\SubscriptionPlan;
use App\Models\Commerce\SubscriptionInvoice;
use App\Models\Commerce\CommerceTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DemoAnalyticsController extends Controller
{
    /**
     * Get demo subscription analytics.
     */
    public function subscriptionAnalytics(Request $request): JsonResponse
    {
        try {
            $tenantId = optional($request->user())->tenant_id ?? 1;

            // Get subscription statistics
            $totalSubscriptions = Subscription::where('tenant_id', $tenantId)->count();
            $activeSubscriptions = Subscription::where('tenant_id', $tenantId)
                ->whereIn('status', ['active', 'trialing'])
                ->count();
            $cancelledSubscriptions = Subscription::where('tenant_id', $tenantId)
                ->where('status', 'cancelled')
                ->count();

            // Calculate MRR (Monthly Recurring Revenue)
            $mrr = Subscription::where('tenant_id', $tenantId)
                ->whereIn('status', ['active', 'trialing'])
                ->get()
                ->sum(function ($subscription) {
                    if ($subscription->plan->interval === 'yearly') {
                        return $subscription->plan->amount_cents / 12; // Convert yearly to monthly
                    }
                    return $subscription->plan->amount_cents;
                });

            // Calculate ARR (Annual Recurring Revenue)
            $arr = $mrr * 12;

            // Get subscription growth over time
            $growthData = $this->getSubscriptionGrowthData($tenantId);

            // Get plan performance
            $planPerformance = $this->getPlanPerformance($tenantId);

            // Get churn rate
            $churnRate = $this->calculateChurnRate($tenantId);

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_subscriptions' => $totalSubscriptions,
                        'active_subscriptions' => $activeSubscriptions,
                        'cancelled_subscriptions' => $cancelledSubscriptions,
                        'mrr' => round($mrr / 100, 2),
                        'arr' => round($arr / 100, 2),
                        'churn_rate' => $churnRate,
                    ],
                    'growth' => $growthData,
                    'plan_performance' => $planPerformance,
                    'demo_mode' => true,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get demo revenue analytics.
     */
    public function revenueAnalytics(Request $request): JsonResponse
    {
        try {
            $tenantId = optional($request->user())->tenant_id ?? 1;
            $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from')) : now()->subDays(30);
            $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to')) : now();

            // Get revenue data
            $totalRevenue = SubscriptionInvoice::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', 'paid')
                ->sum('amount_cents');

            // Get monthly revenue breakdown
            $monthlyRevenue = $this->getMonthlyRevenueData($tenantId, $dateFrom, $dateTo);

            // Get revenue by plan
            $revenueByPlan = $this->getRevenueByPlan($tenantId, $dateFrom, $dateTo);

            // Get payment success rate
            $successRate = $this->calculatePaymentSuccessRate($tenantId, $dateFrom, $dateTo);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_revenue' => round($totalRevenue / 100, 2),
                    'monthly_breakdown' => $monthlyRevenue,
                    'revenue_by_plan' => $revenueByPlan,
                    'payment_success_rate' => $successRate,
                    'currency' => 'USD',
                    'demo_mode' => true,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve revenue analytics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription growth data.
     */
    private function getSubscriptionGrowthData($tenantId)
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Subscription::where('tenant_id', $tenantId)
                ->where('created_at', '<=', $date->endOfMonth())
                ->count();
            
            $months[] = [
                'month' => $date->format('M Y'),
                'subscriptions' => $count,
            ];
        }

        return $months;
    }

    /**
     * Get plan performance data.
     */
    private function getPlanPerformance($tenantId)
    {
        return SubscriptionPlan::where('tenant_id', $tenantId)
            ->withCount(['subscriptions as active_count' => function ($query) {
                $query->whereIn('status', ['active', 'trialing']);
            }])
            ->get()
            ->map(function ($plan) {
                return [
                    'plan_name' => $plan->name,
                    'active_subscriptions' => $plan->active_count,
                    'revenue' => round(($plan->active_count * $plan->amount_cents) / 100, 2),
                    'interval' => $plan->interval,
                ];
            });
    }

    /**
     * Calculate churn rate.
     */
    private function calculateChurnRate($tenantId)
    {
        $totalSubscriptions = Subscription::where('tenant_id', $tenantId)->count();
        $cancelledSubscriptions = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'cancelled')
            ->count();

        if ($totalSubscriptions === 0) {
            return 0;
        }

        return round(($cancelledSubscriptions / $totalSubscriptions) * 100, 2);
    }

    /**
     * Get monthly revenue data.
     */
    private function getMonthlyRevenueData($tenantId, $dateFrom, $dateTo)
    {
        $months = [];
        $current = $dateFrom->copy()->startOfMonth();

        while ($current->lte($dateTo)) {
            $revenue = SubscriptionInvoice::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$current->copy()->startOfMonth(), $current->copy()->endOfMonth()])
                ->where('status', 'paid')
                ->sum('amount_cents');

            $months[] = [
                'month' => $current->format('M Y'),
                'revenue' => round($revenue / 100, 2),
            ];

            $current->addMonth();
        }

        return $months;
    }

    /**
     * Get revenue by plan.
     */
    private function getRevenueByPlan($tenantId, $dateFrom, $dateTo)
    {
        return SubscriptionPlan::where('tenant_id', $tenantId)
            ->with(['subscriptions' => function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo]);
            }])
            ->get()
            ->map(function ($plan) {
                $revenue = $plan->subscriptions->sum('plan.amount_cents');
                return [
                    'plan_name' => $plan->name,
                    'revenue' => round($revenue / 100, 2),
                    'subscription_count' => $plan->subscriptions->count(),
                ];
            });
    }

    /**
     * Calculate payment success rate.
     */
    private function calculatePaymentSuccessRate($tenantId, $dateFrom, $dateTo)
    {
        $totalTransactions = CommerceTransaction::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $successfulTransactions = CommerceTransaction::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'completed')
            ->count();

        if ($totalTransactions === 0) {
            return 100;
        }

        return round(($successfulTransactions / $totalTransactions) * 100, 2);
    }
}
