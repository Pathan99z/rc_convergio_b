<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Company;
use App\Models\Deal;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\VisitorIntent;
use App\Models\LeadScoringRule;
use App\Services\TeamAccessService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    public function __construct(
        private TeamAccessService $teamAccessService
    ) {}
    /**
     * Get date range based on period.
     */
    private function getDateRange(string $period): array
    {
        $now = now();
        
        switch ($period) {
            case 'week':
                return [
                    'start' => $now->copy()->startOfWeek(),
                    'end' => $now->copy()->endOfWeek(),
                    'previous_start' => $now->copy()->subWeek()->startOfWeek(),
                    'previous_end' => $now->copy()->subWeek()->endOfWeek(),
                ];
            case 'month':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                    'previous_start' => $now->copy()->subMonth()->startOfMonth(),
                    'previous_end' => $now->copy()->subMonth()->endOfMonth(),
                ];
            case 'quarter':
                return [
                    'start' => $now->copy()->startOfQuarter(),
                    'end' => $now->copy()->endOfQuarter(),
                    'previous_start' => $now->copy()->subQuarter()->startOfQuarter(),
                    'previous_end' => $now->copy()->subQuarter()->endOfQuarter(),
                ];
            case 'year':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now->copy()->endOfYear(),
                    'previous_start' => $now->copy()->subYear()->startOfYear(),
                    'previous_end' => $now->copy()->subYear()->endOfYear(),
                ];
            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                    'previous_start' => $now->copy()->subMonth()->startOfMonth(),
                    'previous_end' => $now->copy()->subMonth()->endOfMonth(),
                ];
        }
    }

    /**
     * Get comprehensive analytics for the dashboard.
     */
    public function getDashboardAnalytics(int $tenantId, string $period = 'month'): array
    {
        $contacts = $this->getContactAnalytics($tenantId, $period);
        $companies = $this->getCompanyAnalytics($tenantId, $period);
        $deals = $this->getDealAnalytics($tenantId, $period);
        $activities = $this->getActivityAnalytics($tenantId, $period);
        $campaigns = $this->getCampaignAnalytics($tenantId, $period);
        $ads = $this->getAdAnalytics($tenantId);
        $events = $this->getEventAnalytics($tenantId, $period);
        $meetings = $this->getMeetingAnalytics($tenantId, $period);
        $tasks = $this->getTaskAnalytics($tenantId, $period);
        $forecast = $this->getForecastAnalytics($tenantId);
        $intent = $this->getIntentAnalytics($tenantId, $period);
        $leadScoring = $this->getLeadScoringAnalytics($tenantId, $period);
        $journeys = $this->getJourneyAnalytics($tenantId);

        return [
            'contacts' => $contacts,
            'companies' => $companies,
            'deals' => $deals,
            'activities' => $activities,
            'campaigns' => $campaigns,
            'ads' => $ads,
            'events' => $events,
            'meetings' => $meetings,
            'tasks' => $tasks,
            'forecast' => $forecast,
            'intent' => $intent,
            'lead_scoring' => $leadScoring,
            'journeys' => $journeys,
        ];
    }

    /**
     * Get contact analytics.
     */
    public function getContactAnalytics(int $tenantId, string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);
        
        // Total should be filtered by the selected period
        $totalQuery = Contact::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->teamAccessService->applyTeamFilter($totalQuery);
        $total = $totalQuery->count();
            
        $thisPeriodQuery = Contact::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->teamAccessService->applyTeamFilter($thisPeriodQuery);
        $thisPeriod = $thisPeriodQuery->count();
        
        $lastPeriodQuery = Contact::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']]);
        $this->teamAccessService->applyTeamFilter($lastPeriodQuery);
        $lastPeriod = $lastPeriodQuery->count();
        
        // For high score and avg score, we still use all-time data as these are attributes, not time-based
        $highScoreQuery = Contact::forTenant($tenantId)
            ->where('lead_score', '>=', 80);
        $this->teamAccessService->applyTeamFilter($highScoreQuery);
        $highScore = $highScoreQuery
            ->count();
        
        $avgScoreQuery = Contact::forTenant($tenantId);
        $this->teamAccessService->applyTeamFilter($avgScoreQuery);
        $avgScore = $avgScoreQuery->avg('lead_score') ?? 0;
        
        return [
            'total' => $total,
            'this_month' => $thisPeriod,
            'last_month' => $lastPeriod,
            'high_score' => $highScore,
            'avg_score' => round($avgScore, 1),
            'growth_rate' => $lastPeriod > 0 ? round((($thisPeriod - $lastPeriod) / $lastPeriod) * 100, 1) : 0,
        ];
    }

    /**
     * Get company analytics.
     */
    public function getCompanyAnalytics(int $tenantId, string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);
        
        // Total should be filtered by the selected period
        $totalQuery = Company::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->teamAccessService->applyTeamFilter($totalQuery);
        $total = $totalQuery->count();
            
        $thisPeriodQuery = Company::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        $this->teamAccessService->applyTeamFilter($thisPeriodQuery);
        $thisPeriod = $thisPeriodQuery->count();
        
        $lastPeriodQuery = Company::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']]);
        $this->teamAccessService->applyTeamFilter($lastPeriodQuery);
        $lastPeriod = $lastPeriodQuery->count();
        
        return [
            'total' => $total,
            'this_month' => $thisPeriod,
            'last_month' => $lastPeriod,
            'growth_rate' => $lastPeriod > 0 ? round((($thisPeriod - $lastPeriod) / $lastPeriod) * 100, 1) : 0,
        ];
    }

    /**
     * Get deal analytics.
     */
    public function getDealAnalytics(int $tenantId, string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);
        
        // Total should be filtered by the selected period
        $total = Deal::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
            
        $thisPeriod = Deal::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        // For status-based counts, we use all-time data as deals can change status over time
        $won = Deal::forTenant($tenantId)->where('status', 'won')->count();
        $lost = Deal::forTenant($tenantId)->where('status', 'lost')->count();
        $active = Deal::forTenant($tenantId)->where('status', 'open')->count();
        
        // For values, we use all-time data as deals can have values regardless of when created
        $totalValue = Deal::forTenant($tenantId)->sum('value');
        $wonValue = Deal::forTenant($tenantId)->where('status', 'won')->sum('value');
        
        return [
            'total' => $total,
            'this_month' => $thisPeriod,
            'won' => $won,
            'lost' => $lost,
            'active' => $active,
            'total_value' => $totalValue,
            'won_value' => $wonValue,
            'win_rate' => $total > 0 ? round(($won / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get activity analytics.
     */
    public function getActivityAnalytics(int $tenantId, string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);
        
        // Total should be filtered by the selected period
        $total = Activity::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
            
        $thisPeriod = Activity::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $lastPeriod = Activity::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
            ->count();
        
        // For by_type breakdown, we use all-time data as it shows activity patterns
        $byType = Activity::forTenant($tenantId)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');
        
        return [
            'total' => $total,
            'this_month' => $thisPeriod,
            'last_month' => $lastPeriod,
            'by_type' => $byType,
            'growth_rate' => $lastPeriod > 0 ? round((($thisPeriod - $lastPeriod) / $lastPeriod) * 100, 1) : 0,
        ];
    }

    /**
     * Get campaign analytics.
     */
    public function getCampaignAnalytics(int $tenantId, string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);
        
        // Total should be filtered by the selected period
        $total = Campaign::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
            
        $thisPeriod = Campaign::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        // For status-based counts, we use all-time data as campaigns can change status over time
        $active = Campaign::forTenant($tenantId)->where('status', 'active')->count();
        $completed = Campaign::forTenant($tenantId)->where('status', 'completed')->count();
        $draft = Campaign::forTenant($tenantId)->where('status', 'draft')->count();
        
        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed,
            'draft' => $draft,
            'this_month' => $thisPeriod,
        ];
    }

    /**
     * Get event analytics.
     */
    public function getEventAnalytics(int $tenantId, string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);
        
        // Total should be filtered by the selected period
        $total = Event::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
            
        $thisPeriod = Event::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        // For upcoming events and attendees, we use all-time data as these are current states
        $upcoming = Event::forTenant($tenantId)
            ->where('scheduled_at', '>=', now())
            ->count();
        
        $totalAttendees = Event::forTenant($tenantId)
            ->withCount('attendees')
            ->get()
            ->sum('attendees_count');
        
        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'this_month' => $thisPeriod,
            'total_attendees' => $totalAttendees,
        ];
    }

    /**
     * Get intent analytics.
     */
    public function getIntentAnalytics(int $tenantId, string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);
        
        // Total should be filtered by the selected period
        $total = VisitorIntent::withoutGlobalScope('Illuminate\Database\Eloquent\SoftDeletingScope')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
            
        $thisPeriod = VisitorIntent::withoutGlobalScope('Illuminate\Database\Eloquent\SoftDeletingScope')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        // For high intent and avg score, we use all-time data as these are attributes
        $highIntent = VisitorIntent::withoutGlobalScope('Illuminate\Database\Eloquent\SoftDeletingScope')
            ->where('tenant_id', $tenantId)
            ->where('score', '>=', 80)
            ->count();
        
        $avgIntentScore = VisitorIntent::withoutGlobalScope('Illuminate\Database\Eloquent\SoftDeletingScope')
            ->where('tenant_id', $tenantId)->avg('score') ?? 0;
        
        return [
            'total' => $total,
            'this_period' => $thisPeriod,
            'high_intent' => $highIntent,
            'avg_intent_score' => round($avgIntentScore, 1),
        ];
    }

    /**
     * Get lead scoring analytics.
     */
    public function getLeadScoringAnalytics(int $tenantId, string $period = 'month'): array
    {
        $totalContacts = Contact::forTenant($tenantId)->count();
        $scoredContacts = Contact::forTenant($tenantId)
            ->whereNotNull('lead_score')
            ->where('lead_score', '>', 0)
            ->count();
        
        $activeRules = LeadScoringRule::forTenant($tenantId)->where('is_active', true)->count();
        
        $highScoreContacts = Contact::forTenant($tenantId)->where('lead_score', '>=', 80)->count();
        $mediumScoreContacts = Contact::forTenant($tenantId)->whereBetween('lead_score', [50, 79])->count();
        $lowScoreContacts = Contact::forTenant($tenantId)->where('lead_score', '<', 50)->count();
        
        $avgScore = Contact::forTenant($tenantId)->avg('lead_score') ?? 0;
        
        return [
            'total_contacts' => $totalContacts,
            'scored_contacts' => $scoredContacts,
            'active_rules' => $activeRules,
            'high_score' => $highScoreContacts,
            'medium_score' => $mediumScoreContacts,
            'low_score' => $lowScoreContacts,
            'avg_score' => round($avgScore, 1),
            'scoring_coverage' => $totalContacts > 0 ? round(($scoredContacts / $totalContacts) * 100, 1) : 0,
        ];
    }

    /**
     * Get trend data for charts.
     */
    public function getTrendData(int $tenantId, string $type, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        $endDate = now();
        
        $data = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $nextDate = $date->copy()->addDay();
            
            switch ($type) {
                case 'contacts':
                    $count = Contact::forTenant($tenantId)
                        ->whereBetween('created_at', [$date, $nextDate])
                        ->count();
                    break;
                case 'companies':
                    $count = Company::forTenant($tenantId)
                        ->whereBetween('created_at', [$date, $nextDate])
                        ->count();
                    break;
                case 'deals':
                    $count = Deal::forTenant($tenantId)
                        ->whereBetween('created_at', [$date, $nextDate])
                        ->count();
                    break;
                case 'activities':
                    $count = Activity::forTenant($tenantId)
                        ->whereBetween('created_at', [$date, $nextDate])
                        ->count();
                    break;
                default:
                    $count = 0;
            }
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $count,
            ];
        }
        
        return $data;
    }

    /**
     * Get top performing campaigns.
     */
    public function getTopCampaigns(int $tenantId, int $limit = 5): array
    {
        $query = Campaign::forTenant($tenantId)
            ->withCount('recipients')
            ->orderBy('recipients_count', 'desc');
        $this->teamAccessService->applyTeamFilter($query);
        return $query
            ->limit($limit)
            ->get()
            ->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'recipients' => $campaign->recipients_count,
                    'status' => $campaign->status,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent activities.
     */
    public function getRecentActivities(int $tenantId, int $limit = 10): array
    {
        $query = Activity::forTenant($tenantId);
        $this->teamAccessService->applyTeamFilter($query);
        return $query
            ->with(['contact', 'company'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'description' => $activity->description,
                    'contact' => $activity->contact ? [
                        'id' => $activity->contact->id,
                        'name' => $activity->contact->first_name . ' ' . $activity->contact->last_name,
                    ] : null,
                    'company' => $activity->company ? [
                        'id' => $activity->company->id,
                        'name' => $activity->company->name,
                    ] : null,
                    'created_at' => $activity->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get analytics for a specific module.
     */
    public function getModuleAnalytics(int $tenantId, string $module, array $filters = []): array
    {
        $period = $filters['period'] ?? 'month';
        
        // Handle special cases for modules with underscores or different names
        switch ($module) {
            case 'contacts':
                return $this->getContactAnalytics($tenantId, $period);
            case 'companies':
                return $this->getCompanyAnalytics($tenantId, $period);
            case 'deals':
                return $this->getDealAnalytics($tenantId, $period);
            case 'campaigns':
                return $this->getCampaignAnalytics($tenantId, $period);
            case 'ads':
                return $this->getAdAnalytics($tenantId);
            case 'events':
                return $this->getEventAnalytics($tenantId, $period);
            case 'meetings':
                return $this->getMeetingAnalytics($tenantId, $period);
            case 'tasks':
                return $this->getTaskAnalytics($tenantId, $period);
            case 'forecast':
                return $this->getForecastAnalytics($tenantId);
            case 'journeys':
                return $this->getJourneyAnalytics($tenantId);
            case 'visitor_intent':
            case 'visitor-intent':
                return $this->getIntentAnalytics($tenantId, $period);
            case 'lead_scoring':
            case 'lead-scoring':
                return $this->getLeadScoringAnalytics($tenantId, $period);
            default:
                // Try dynamic method name as fallback
                $method = 'get' . ucfirst($module) . 'Analytics';
                if (method_exists($this, $method)) {
                    return $this->$method($tenantId);
                }
                throw new \InvalidArgumentException("Invalid module: {$module}");
        }
    }

    /**
     * Get ad analytics.
     */
    public function getAdAnalytics(int $tenantId): array
    {
        // Return basic structure - can be enhanced with actual ad data when available
        return [
            'total' => 0,
            'active' => 0,
            'paused' => 0,
            'total_spend' => 0,
            'total_impressions' => 0,
            'total_clicks' => 0,
            'avg_ctr' => 0,
            'avg_cpc' => 0,
            'this_month' => 0,
            'last_month' => 0,
            'growth_rate' => 0,
        ];
    }

    /**
     * Get meeting analytics.
     */
    public function getMeetingAnalytics(int $tenantId, string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);
        
        // Total should be filtered by the selected period
        $total = Meeting::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
            
        $thisPeriod = Meeting::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $lastPeriod = Meeting::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
            ->count();
        
        // For status-based counts, we use all-time data as meetings can change status over time
        $upcoming = Meeting::forTenant($tenantId)
            ->where('scheduled_at', '>', now())
            ->where('status', 'scheduled')
            ->count();
        
        $completed = Meeting::forTenant($tenantId)
            ->where('status', 'completed')
            ->count();
        
        $cancelled = Meeting::forTenant($tenantId)
            ->where('status', 'cancelled')
            ->count();
        
        // For duration calculations, we use all-time data
        $totalDuration = Meeting::forTenant($tenantId)
            ->whereNotNull('duration_minutes')
            ->sum('duration_minutes');
        
        $avgDuration = Meeting::forTenant($tenantId)
            ->whereNotNull('duration_minutes')
            ->avg('duration_minutes') ?? 0;
        
        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'this_month' => $thisPeriod,
            'last_month' => $lastPeriod,
            'total_duration_minutes' => $totalDuration,
            'avg_duration_minutes' => round($avgDuration, 1),
            'growth_rate' => $lastPeriod > 0 ? round((($thisPeriod - $lastPeriod) / $lastPeriod) * 100, 1) : 0,
        ];
    }

    /**
     * Get task analytics.
     */
    public function getTaskAnalytics(int $tenantId, string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);
        
        // Total should be filtered by the selected period
        $total = Task::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
            
        $thisPeriod = Task::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $lastPeriod = Task::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['previous_start'], $dateRange['previous_end']])
            ->count();
        
        // For status-based counts, we use all-time data as tasks can change status over time
        $completed = Task::forTenant($tenantId)
            ->where('status', 'completed')
            ->count();
        
        $pending = Task::forTenant($tenantId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();
        
        $overdue = Task::forTenant($tenantId)
            ->where('due_date', '<', now())
            ->where('status', '!=', 'completed')
            ->count();
        
        // Calculate completion rate based on all-time data
        $totalTasks = Task::forTenant($tenantId)->count();
        $completionRate = $totalTasks > 0 ? round(($completed / $totalTasks) * 100, 1) : 0;
        
        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'this_month' => $thisPeriod,
            'last_month' => $lastPeriod,
            'completion_rate' => $completionRate,
            'growth_rate' => $lastPeriod > 0 ? round((($thisPeriod - $lastPeriod) / $lastPeriod) * 100, 1) : 0,
        ];
    }

    /**
     * Get forecast analytics.
     */
    public function getForecastAnalytics(int $tenantId): array
    {
        $dealsQuery = Deal::forTenant($tenantId)->where('status', 'active');
        $this->teamAccessService->applyTeamFilter($dealsQuery);
        $deals = $dealsQuery->get();
        
        $totalValue = $deals->sum('value');
        $weightedValue = $deals->sum(function ($deal) {
            $probability = $deal->probability ?? 50; // Default 50% if not set
            return ($deal->value * $probability) / 100;
        });
        
        $thisMonthQuery = Deal::forTenant($tenantId)
            ->where('status', 'active')
            ->whereMonth('expected_close_date', now()->month)
            ->whereYear('expected_close_date', now()->year);
        $this->teamAccessService->applyTeamFilter($thisMonthQuery);
        $thisMonth = $thisMonthQuery->sum('value');
            
        $nextMonthQuery = Deal::forTenant($tenantId)
            ->where('status', 'active')
            ->whereMonth('expected_close_date', now()->addMonth()->month)
            ->whereYear('expected_close_date', now()->addMonth()->year);
        $this->teamAccessService->applyTeamFilter($nextMonthQuery);
        $nextMonth = $nextMonthQuery
            ->sum('value');
        
        return [
            'total_pipeline_value' => $totalValue,
            'weighted_pipeline_value' => $weightedValue,
            'this_month_forecast' => $thisMonth,
            'next_month_forecast' => $nextMonth,
            'active_deals' => $deals->count(),
            'avg_deal_size' => $deals->count() > 0 ? $deals->avg('value') : 0,
            'forecast_accuracy' => 0, // Can be calculated based on historical data
        ];
    }

    /**
     * Get journey analytics.
     */
    public function getJourneyAnalytics(int $tenantId): array
    {
        // Return basic structure - can be enhanced with actual journey data when available
        return [
            'total_journeys' => 0,
            'active_journeys' => 0,
            'completed_journeys' => 0,
            'total_contacts_in_journeys' => 0,
            'avg_journey_duration_days' => 0,
            'conversion_rate' => 0,
            'this_month' => 0,
            'last_month' => 0,
            'growth_rate' => 0,
        ];
    }
}