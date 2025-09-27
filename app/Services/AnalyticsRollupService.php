<?php

namespace App\Services;

use App\Models\IntentEvent;
use App\Models\IntentDaily;
use App\Models\IntentTopPagesDaily;
use App\Models\IntentActionsDaily;
use App\Services\UrlNormalizerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsRollupService
{
    /**
     * Update rollups for a new event (incremental update).
     */
    public function updateRollupsForEvent(IntentEvent $event): void
    {
        try {
            $tenantId = $event->tenant_id;
            $day = $event->created_at->format('Y-m-d');
            $eventData = json_decode($event->event_data, true);
            
            // Use normalized URL if available, fallback to original, then to "Unknown Page"
            $pageUrl = $eventData['page_url_normalized'] ?? $eventData['page_url'] ?? 'Unknown Page';
            
            // If we still have "Unknown Page", try to normalize the original URL
            if ($pageUrl === 'Unknown Page' && !empty($eventData['page_url'])) {
                $urlNormalizer = new UrlNormalizerService();
                $pageUrl = $urlNormalizer->normalize($eventData['page_url']);
            }
            
            $action = $event->event_name;
            $score = $event->intent_score;

            DB::transaction(function () use ($tenantId, $day, $event, $pageUrl, $action, $score) {
                // Update daily rollup
                $dailyRollup = IntentDaily::getOrCreateForDay($tenantId, $day);
                $dailyRollup->incrementForEvent([
                    'intent_score' => $score,
                    'contact_id' => $event->contact_id,
                    'company_id' => $event->company_id,
                ]);

                // Update top pages rollup
                $pageRollup = IntentTopPagesDaily::getOrCreateForPage($tenantId, $day, $pageUrl);
                $pageRollup->incrementForVisit($score);

                // Update actions rollup
                $actionRollup = IntentActionsDaily::getOrCreateForAction($tenantId, $day, $action);
                $actionRollup->incrementForAction($score);
            });

        } catch (\Exception $e) {
            Log::error('Failed to update rollups for event', [
                'event_id' => $event->id,
                'tenant_id' => $event->tenant_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Re-materialize rollups for a specific date range (nightly job).
     */
    public function rematerializeRollups(int $tenantId, string $startDate, string $endDate): void
    {
        try {
            Log::info('Starting rollup rematerialization', [
                'tenant_id' => $tenantId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            $events = IntentEvent::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            if ($events->isEmpty()) {
                Log::info('No events found for rematerialization', [
                    'tenant_id' => $tenantId,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]);
                return;
            }

            // Group events by day
            $eventsByDay = $events->groupBy(function ($event) {
                return $event->created_at->format('Y-m-d');
            });

            foreach ($eventsByDay as $day => $dayEvents) {
                $this->rematerializeDay($tenantId, $day, $dayEvents);
            }

            Log::info('Completed rollup rematerialization', [
                'tenant_id' => $tenantId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_events' => $events->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to rematerialize rollups', [
                'tenant_id' => $tenantId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Re-materialize rollups for a specific day.
     */
    private function rematerializeDay(int $tenantId, string $day, $events): void
    {
        DB::transaction(function () use ($tenantId, $day, $events) {
            // Clear existing rollups for this day
            IntentDaily::forTenant($tenantId)->where('day', $day)->delete();
            IntentTopPagesDaily::forTenant($tenantId)->where('day', $day)->delete();
            IntentActionsDaily::forTenant($tenantId)->where('day', $day)->delete();

            // Recalculate rollups
            $dailyRollup = IntentDaily::getOrCreateForDay($tenantId, $day);
            $pageRollups = [];
            $actionRollups = [];

            foreach ($events as $event) {
                $eventData = json_decode($event->event_data, true);
                
                // Use normalized URL if available, fallback to original, then to "Unknown Page"
                $pageUrl = $eventData['page_url_normalized'] ?? $eventData['page_url'] ?? 'Unknown Page';
                
                // If we still have "Unknown Page", try to normalize the original URL
                if ($pageUrl === 'Unknown Page' && !empty($eventData['page_url'])) {
                    $urlNormalizer = new UrlNormalizerService();
                    $pageUrl = $urlNormalizer->normalize($eventData['page_url']);
                }
                
                $action = $event->event_name;
                $score = $event->intent_score;

                // Update daily rollup
                $dailyRollup->incrementForEvent([
                    'intent_score' => $score,
                    'contact_id' => $event->contact_id,
                    'company_id' => $event->company_id,
                ]);

                // Update page rollup
                if (!isset($pageRollups[$pageUrl])) {
                    $pageRollups[$pageUrl] = IntentTopPagesDaily::getOrCreateForPage($tenantId, $day, $pageUrl);
                }
                $pageRollups[$pageUrl]->incrementForVisit($score);

                // Update action rollup
                if (!isset($actionRollups[$action])) {
                    $actionRollups[$action] = IntentActionsDaily::getOrCreateForAction($tenantId, $day, $action);
                }
                $actionRollups[$action]->incrementForAction($score);
            }
        });
    }

    /**
     * Get analytics data from rollups with fallback to live queries.
     */
    public function getAnalyticsData(int $tenantId, array $filters = []): array
    {
        $startDate = $filters['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $endDate = $filters['date_to'] ?? now()->format('Y-m-d');

        try {
            // Try to get data from rollups first
            $rollupData = $this->getRollupAnalytics($tenantId, $startDate, $endDate);
            
            if ($this->isRollupDataComplete($rollupData, $startDate, $endDate)) {
                return $rollupData;
            }

            // Fallback to live queries if rollups are incomplete
            Log::info('Rollup data incomplete, falling back to live queries', [
                'tenant_id' => $tenantId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            return $this->getLiveAnalytics($tenantId, $filters);

        } catch (\Exception $e) {
            Log::error('Failed to get analytics data, falling back to live queries', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return $this->getLiveAnalytics($tenantId, $filters);
        }
    }

    /**
     * Get analytics data from rollups.
     */
    private function getRollupAnalytics(int $tenantId, string $startDate, string $endDate): array
    {
        // Get overview data from daily rollups
        $overview = IntentDaily::getAggregatedStats($tenantId, $startDate, $endDate);

        // Get top pages from rollups
        $topPages = IntentTopPagesDaily::getTopPages($tenantId, $startDate, $endDate, 10);

        // Get action breakdown from rollups
        $actionBreakdown = IntentActionsDaily::getActionBreakdown($tenantId, $startDate, $endDate);

        // Get top companies (this would need to be implemented in rollups or fallback to live)
        $topCompanies = $this->getTopCompaniesFromRollups($tenantId, $startDate, $endDate);

        return [
            'overview' => $overview,
            'action_breakdown' => $actionBreakdown,
            'top_pages' => $topPages,
            'intent_distribution' => $overview['intent_distribution'],
            'top_companies' => $topCompanies,
            'data_source' => 'rollups',
        ];
    }

    /**
     * Get top companies from rollups (simplified implementation).
     */
    private function getTopCompaniesFromRollups(int $tenantId, string $startDate, string $endDate): array
    {
        // For now, return empty array - this would need to be implemented
        // with a separate rollup table or fallback to live queries
        return [];
    }

    /**
     * Check if rollup data is complete for the date range.
     */
    private function isRollupDataComplete(array $rollupData, string $startDate, string $endDate): bool
    {
        // Check if we have data for all days in the range
        $expectedDays = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;
        $actualDays = IntentDaily::between($startDate, $endDate)->count();

        return $actualDays >= $expectedDays * 0.8; // Allow 20% missing data
    }

    /**
     * Get analytics data from live queries (fallback).
     */
    private function getLiveAnalytics(int $tenantId, array $filters): array
    {
        // This would use the existing live query logic from TrackingController
        // For now, return a placeholder that indicates fallback
        return [
            'overview' => [
                'total_events' => 0,
                'unique_contacts' => 0,
                'unique_companies' => 0,
                'average_score' => 0,
                'high_intent_events' => 0,
                'high_intent_percentage' => 0,
            ],
            'action_breakdown' => [],
            'top_pages' => [],
            'intent_distribution' => [
                'very_high' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'very_low' => 0,
            ],
            'top_companies' => [],
            'data_source' => 'live_fallback',
        ];
    }

    /**
     * Get rollup statistics for monitoring.
     */
    public function getRollupStats(int $tenantId): array
    {
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        $todayRollup = IntentDaily::forTenant($tenantId)->where('day', $today)->first();
        $yesterdayRollup = IntentDaily::forTenant($tenantId)->where('day', $yesterday)->first();

        return [
            'today_events' => $todayRollup?->total_events ?? 0,
            'yesterday_events' => $yesterdayRollup?->total_events ?? 0,
            'rollup_coverage' => $this->getRollupCoverage($tenantId),
            'last_updated' => $todayRollup?->updated_at ?? null,
        ];
    }

    /**
     * Get rollup coverage percentage for the last 30 days.
     */
    private function getRollupCoverage(int $tenantId): float
    {
        $startDate = now()->subDays(30)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $expectedDays = 30;
        $actualDays = IntentDaily::forTenant($tenantId)
            ->between($startDate, $endDate)
            ->count();

        return $actualDays > 0 ? round(($actualDays / $expectedDays) * 100, 2) : 0;
    }
}
