<?php

namespace App\Services;

use App\Models\Deal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ForecastService
{
    /**
     * Generate sales forecast based on deals and their probabilities.
     */
    public function generateForecast(int $tenantId, string $timeframe = 'monthly'): array
    {
        $dateRange = $this->getDateRange($timeframe);
        
        // Get deals within the timeframe
        $deals = Deal::forTenant($tenantId)
            ->where('status', 'open')
            ->whereBetween('expected_close_date', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('value')
            ->whereNotNull('probability')
            ->get();

        $totalDeals = $deals->count();
        $projectedValue = $deals->sum('value');
        $probabilityWeighted = $deals->sum(function ($deal) {
            return $deal->value * ($deal->probability / 100);
        });

        // Calculate additional metrics
        $averageDealSize = $totalDeals > 0 ? $projectedValue / $totalDeals : 0;
        $averageProbability = $totalDeals > 0 ? $deals->avg('probability') : 0;

        // Group by stage for detailed breakdown
        $stageBreakdown = $deals->groupBy('stage_id')->map(function ($stageDeals) {
            $stageValue = $stageDeals->sum('value');
            $stageProbability = $stageDeals->sum(function ($deal) {
                return $deal->value * ($deal->probability / 100);
            });
            
            return [
                'count' => $stageDeals->count(),
                'total_value' => $stageValue,
                'probability_weighted' => $stageProbability,
                'average_probability' => $stageDeals->avg('probability'),
            ];
        });

        // Group by owner for team performance
        $ownerBreakdown = $deals->groupBy('owner_id')->map(function ($ownerDeals) {
            $ownerValue = $ownerDeals->sum('value');
            $ownerProbability = $ownerDeals->sum(function ($deal) {
                return $deal->value * ($deal->probability / 100);
            });
            
            return [
                'count' => $ownerDeals->count(),
                'total_value' => $ownerValue,
                'probability_weighted' => $ownerProbability,
                'average_probability' => $ownerDeals->avg('probability'),
            ];
        });

        // Calculate conversion rate based on historical data
        $conversionRate = $this->calculateConversionRate($tenantId, $dateRange);

        return [
            'timeframe' => $timeframe,
            'period' => [
                'start' => $dateRange['start']->format('Y-m-d'),
                'end' => $dateRange['end']->format('Y-m-d'),
            ],
            'total_deals' => $totalDeals,
            'projected_value' => round($projectedValue, 2),
            'probability_weighted' => round($probabilityWeighted, 2),
            'average_deal_size' => round($averageDealSize, 2),
            'average_probability' => round($averageProbability, 2),
            'conversion_rate' => round($conversionRate, 2),
            'stage_breakdown' => $stageBreakdown,
            'owner_breakdown' => $ownerBreakdown,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get date range based on timeframe.
     */
    private function getDateRange(string $timeframe): array
    {
        $now = Carbon::now();
        
        switch ($timeframe) {
            case 'quarterly':
                $start = $now->copy()->startOfQuarter();
                $end = $now->copy()->endOfQuarter();
                break;
            case 'yearly':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                break;
            case 'monthly':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Calculate conversion rate based on historical data.
     */
    private function calculateConversionRate(int $tenantId, array $dateRange): float
    {
        // Look at deals from the same period last year
        $lastYearStart = $dateRange['start']->copy()->subYear();
        $lastYearEnd = $dateRange['end']->copy()->subYear();

        $historicalDeals = Deal::forTenant($tenantId)
            ->whereBetween('expected_close_date', [$lastYearStart, $lastYearEnd])
            ->get();

        if ($historicalDeals->isEmpty()) {
            return 0;
        }

        $totalHistorical = $historicalDeals->count();
        $wonHistorical = $historicalDeals->where('status', 'won')->count();

        return $totalHistorical > 0 ? ($wonHistorical / $totalHistorical) * 100 : 0;
    }

    /**
     * Get forecast for multiple timeframes.
     */
    public function getMultiTimeframeForecast(int $tenantId): array
    {
        $timeframes = ['monthly', 'quarterly', 'yearly'];
        $forecasts = [];

        foreach ($timeframes as $timeframe) {
            $forecasts[$timeframe] = $this->generateForecast($tenantId, $timeframe);
        }

        return [
            'forecasts' => $forecasts,
            'summary' => [
                'total_deals_all_timeframes' => collect($forecasts)->sum('total_deals'),
                'total_projected_value' => collect($forecasts)->sum('projected_value'),
                'total_probability_weighted' => collect($forecasts)->sum('probability_weighted'),
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get forecast trends over time.
     */
    public function getForecastTrends(int $tenantId, int $months = 6): array
    {
        $trends = [];
        $currentMonth = Carbon::now();

        for ($i = 0; $i < $months; $i++) {
            $month = $currentMonth->copy()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $deals = Deal::forTenant($tenantId)
                ->where('status', 'open')
                ->whereBetween('expected_close_date', [$start, $end])
                ->whereNotNull('value')
                ->whereNotNull('probability')
                ->get();

            $trends[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->format('F Y'),
                'total_deals' => $deals->count(),
                'projected_value' => round($deals->sum('value'), 2),
                'probability_weighted' => round($deals->sum(function ($deal) {
                    return $deal->value * ($deal->probability / 100);
                }), 2),
            ];
        }

        return array_reverse($trends); // Return in chronological order
    }

    /**
     * Get forecast by pipeline.
     */
    public function getForecastByPipeline(int $tenantId, string $timeframe = 'monthly'): array
    {
        $dateRange = $this->getDateRange($timeframe);
        
        $deals = Deal::forTenant($tenantId)
            ->with('pipeline')
            ->where('status', 'open')
            ->whereBetween('expected_close_date', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('value')
            ->whereNotNull('probability')
            ->get();

        $pipelineBreakdown = $deals->groupBy('pipeline_id')->map(function ($pipelineDeals, $pipelineId) {
            $pipeline = $pipelineDeals->first()->pipeline;
            
            return [
                'pipeline_id' => $pipelineId,
                'pipeline_name' => $pipeline ? $pipeline->name : 'Unknown',
                'count' => $pipelineDeals->count(),
                'total_value' => round($pipelineDeals->sum('value'), 2),
                'probability_weighted' => round($pipelineDeals->sum(function ($deal) {
                    return $deal->value * ($deal->probability / 100);
                }), 2),
                'average_probability' => round($pipelineDeals->avg('probability'), 2),
            ];
        });

        return [
            'timeframe' => $timeframe,
            'period' => [
                'start' => $dateRange['start']->format('Y-m-d'),
                'end' => $dateRange['end']->format('Y-m-d'),
            ],
            'pipeline_breakdown' => $pipelineBreakdown,
            'total_pipelines' => $pipelineBreakdown->count(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get forecast accuracy metrics.
     */
    public function getForecastAccuracy(int $tenantId, int $months = 3): array
    {
        $accuracy = [];
        $currentMonth = Carbon::now();

        for ($i = 1; $i <= $months; $i++) {
            $month = $currentMonth->copy()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            // Get deals that were expected to close in this month
            $expectedDeals = Deal::forTenant($tenantId)
                ->whereBetween('expected_close_date', [$start, $end])
                ->whereNotNull('value')
                ->whereNotNull('probability')
                ->get();

            // Get deals that actually closed in this month
            $actualClosed = Deal::forTenant($tenantId)
                ->whereBetween('closed_date', [$start, $end])
                ->where('status', 'won')
                ->whereNotNull('value')
                ->get();

            $expectedValue = $expectedDeals->sum(function ($deal) {
                return $deal->value * ($deal->probability / 100);
            });

            $actualValue = $actualClosed->sum('value');

            $accuracy[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->format('F Y'),
                'expected_deals' => $expectedDeals->count(),
                'actual_closed' => $actualClosed->count(),
                'expected_value' => round($expectedValue, 2),
                'actual_value' => round($actualValue, 2),
                'accuracy_percentage' => $expectedValue > 0 ? round(($actualValue / $expectedValue) * 100, 2) : 0,
            ];
        }

        return [
            'accuracy_metrics' => $accuracy,
            'average_accuracy' => round(collect($accuracy)->avg('accuracy_percentage'), 2),
            'generated_at' => now()->toISOString(),
        ];
    }
}
