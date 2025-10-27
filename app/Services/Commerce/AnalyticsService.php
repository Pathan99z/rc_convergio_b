<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceOrder;
use App\Models\Commerce\CommercePaymentLink;
use App\Models\Commerce\CommerceTransaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get comprehensive analytics for a tenant.
     */
    public function getAnalytics(int $tenantId, array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30);
        $dateTo = $filters['date_to'] ?? now();
        $teamId = $filters['team_id'] ?? null;

        return [
            'overview' => $this->getOverviewStats($tenantId, $dateFrom, $dateTo, $teamId),
            'revenue' => $this->getRevenueAnalytics($tenantId, $dateFrom, $dateTo, $teamId),
            'payment_links' => $this->getPaymentLinkAnalytics($tenantId, $dateFrom, $dateTo, $teamId),
            'orders' => $this->getOrderAnalytics($tenantId, $dateFrom, $dateTo, $teamId),
            'transactions' => $this->getTransactionAnalytics($tenantId, $dateFrom, $dateTo, $teamId),
            'trends' => $this->getTrendsData($tenantId, $dateFrom, $dateTo, $teamId),
        ];
    }

    /**
     * Get overview statistics.
     */
    public function getOverviewStats(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = $this->buildBaseQuery($tenantId, $dateFrom, $dateTo, $teamId);

        $stats = [
            'total_revenue' => 0,
            'total_orders' => 0,
            'total_payment_links' => 0,
            'conversion_rate' => 0,
            'average_order_value' => 0,
            'total_transactions' => 0,
        ];

        // Revenue from completed orders
        $revenueQuery = CommerceOrder::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $revenueQuery->where('team_id', $teamId);
        }

        $stats['total_revenue'] = $revenueQuery->sum('total_amount');

        // Total orders
        $ordersQuery = CommerceOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $ordersQuery->where('team_id', $teamId);
        }

        $stats['total_orders'] = $ordersQuery->count();

        // Total payment links
        $linksQuery = CommercePaymentLink::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $linksQuery->where('team_id', $teamId);
        }

        $stats['total_payment_links'] = $linksQuery->count();

        // Conversion rate (completed payment links / total payment links)
        $completedLinks = CommercePaymentLink::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $completedLinks->where('team_id', $teamId);
        }

        $completedCount = $completedLinks->count();
        $stats['conversion_rate'] = $stats['total_payment_links'] > 0 
            ? round(($completedCount / $stats['total_payment_links']) * 100, 2) 
            : 0;

        // Average order value
        $stats['average_order_value'] = $stats['total_orders'] > 0 
            ? round($stats['total_revenue'] / $stats['total_orders'], 2) 
            : 0;

        // Total transactions
        $transactionsQuery = CommerceTransaction::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $transactionsQuery->where('team_id', $teamId);
        }

        $stats['total_transactions'] = $transactionsQuery->count();

        return $stats;
    }

    /**
     * Get revenue analytics.
     */
    public function getRevenueAnalytics(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceOrder::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return [
            'total_revenue' => $query->sum('total_amount'),
            'revenue_by_status' => $this->getRevenueByStatus($tenantId, $dateFrom, $dateTo, $teamId),
            'revenue_by_currency' => $this->getRevenueByCurrency($tenantId, $dateFrom, $dateTo, $teamId),
            'daily_revenue' => $this->getDailyRevenue($tenantId, $dateFrom, $dateTo, $teamId),
        ];
    }

    /**
     * Get payment link analytics.
     */
    public function getPaymentLinkAnalytics(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommercePaymentLink::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return [
            'total_links' => $query->count(),
            'links_by_status' => $this->getPaymentLinksByStatus($tenantId, $dateFrom, $dateTo, $teamId),
            'conversion_rate' => $this->getConversionRate($tenantId, $dateFrom, $dateTo, $teamId),
            'expired_links' => $this->getExpiredLinks($tenantId, $dateFrom, $dateTo, $teamId),
        ];
    }

    /**
     * Get order analytics.
     */
    public function getOrderAnalytics(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return [
            'total_orders' => $query->count(),
            'orders_by_status' => $this->getOrdersByStatus($tenantId, $dateFrom, $dateTo, $teamId),
            'average_order_value' => $this->getAverageOrderValue($tenantId, $dateFrom, $dateTo, $teamId),
            'top_products' => $this->getTopProducts($tenantId, $dateFrom, $dateTo, $teamId),
        ];
    }

    /**
     * Get transaction analytics.
     */
    public function getTransactionAnalytics(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceTransaction::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return [
            'total_transactions' => $query->count(),
            'transactions_by_type' => $this->getTransactionsByType($tenantId, $dateFrom, $dateTo, $teamId),
            'transactions_by_provider' => $this->getTransactionsByProvider($tenantId, $dateFrom, $dateTo, $teamId),
            'failed_transactions' => $this->getFailedTransactions($tenantId, $dateFrom, $dateTo, $teamId),
        ];
    }

    /**
     * Get trends data for charts.
     */
    public function getTrendsData(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        return [
            'daily_revenue' => $this->getDailyRevenue($tenantId, $dateFrom, $dateTo, $teamId),
            'daily_orders' => $this->getDailyOrders($tenantId, $dateFrom, $dateTo, $teamId),
            'daily_payment_links' => $this->getDailyPaymentLinks($tenantId, $dateFrom, $dateTo, $teamId),
            'monthly_revenue' => $this->getMonthlyRevenue($tenantId, $dateFrom, $dateTo, $teamId),
        ];
    }

    /**
     * Get revenue by status.
     */
    private function getRevenueByStatus(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select('status', DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('status')
            ->pluck('revenue', 'status')
            ->toArray();
    }

    /**
     * Get revenue by currency.
     */
    private function getRevenueByCurrency(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceOrder::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select('currency', DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('currency')
            ->pluck('revenue', 'currency')
            ->toArray();
    }

    /**
     * Get daily revenue.
     */
    private function getDailyRevenue(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceOrder::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('revenue', 'date')
            ->toArray();
    }

    /**
     * Get payment links by status.
     */
    private function getPaymentLinksByStatus(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommercePaymentLink::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get conversion rate.
     */
    private function getConversionRate(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): float
    {
        $totalQuery = CommercePaymentLink::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $totalQuery->where('team_id', $teamId);
        }

        $total = $totalQuery->count();

        $completedQuery = CommercePaymentLink::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $completedQuery->where('team_id', $teamId);
        }

        $completed = $completedQuery->count();

        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    /**
     * Get expired links.
     */
    private function getExpiredLinks(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): int
    {
        $query = CommercePaymentLink::where('tenant_id', $tenantId)
            ->where('status', '!=', 'completed')
            ->where('expires_at', '<', now())
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->count();
    }

    /**
     * Get orders by status.
     */
    private function getOrdersByStatus(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get average order value.
     */
    private function getAverageOrderValue(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): float
    {
        $query = CommerceOrder::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $count = $query->count();
        $total = $query->sum('total_amount');

        return $count > 0 ? round($total / $count, 2) : 0;
    }

    /**
     * Get top products.
     */
    private function getTopProducts(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = DB::table('commerce_order_items')
            ->join('commerce_orders', 'commerce_order_items.order_id', '=', 'commerce_orders.id')
            ->where('commerce_orders.tenant_id', $tenantId)
            ->where('commerce_orders.status', 'paid')
            ->whereBetween('commerce_orders.created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('commerce_orders.team_id', $teamId);
        }

        return $query->select(
                'commerce_order_items.name',
                DB::raw('SUM(commerce_order_items.quantity) as total_quantity'),
                DB::raw('SUM(commerce_order_items.subtotal) as total_revenue')
            )
            ->groupBy('commerce_order_items.name')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get transactions by type.
     */
    private function getTransactionsByType(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceTransaction::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select('transaction_type', DB::raw('COUNT(*) as count'))
            ->groupBy('transaction_type')
            ->pluck('count', 'transaction_type')
            ->toArray();
    }

    /**
     * Get transactions by provider.
     */
    private function getTransactionsByProvider(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceTransaction::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select('provider', DB::raw('COUNT(*) as count'))
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->toArray();
    }

    /**
     * Get failed transactions.
     */
    private function getFailedTransactions(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): int
    {
        $query = CommerceTransaction::where('tenant_id', $tenantId)
            ->where('status', 'failed')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->count();
    }

    /**
     * Get daily orders.
     */
    private function getDailyOrders(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('orders', 'date')
            ->toArray();
    }

    /**
     * Get daily payment links.
     */
    private function getDailyPaymentLinks(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommercePaymentLink::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as links')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('links', 'date')
            ->toArray();
    }

    /**
     * Get monthly revenue.
     */
    private function getMonthlyRevenue(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null): array
    {
        $query = CommerceOrder::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'revenue' => $item->revenue,
                ];
            })
            ->pluck('revenue', 'period')
            ->toArray();
    }

    /**
     * Build base query with common filters.
     */
    private function buildBaseQuery(int $tenantId, Carbon $dateFrom, Carbon $dateTo, ?int $teamId = null)
    {
        // This is a helper method for building common query patterns
        // Implementation depends on specific use case
        return null;
    }
}
