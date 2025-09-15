<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Campaign;
use App\Models\AdAccount;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Company;
use App\Models\VisitorIntent;
use App\Models\LeadScoringRule;
use App\Models\Journey;
use App\Models\JourneyExecution;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get comprehensive dashboard analytics for a tenant.
     */
    public function getDashboardAnalytics(int $tenantId, array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        return [
            'contacts' => $this->getContactsAnalytics($tenantId, $dateRange),
            'deals' => $this->getDealsAnalytics($tenantId, $dateRange),
            'campaigns' => $this->getCampaignsAnalytics($tenantId, $dateRange),
            'ads' => $this->getAdsAnalytics($tenantId, $dateRange),
            'events' => $this->getEventsAnalytics($tenantId, $dateRange),
            'meetings' => $this->getMeetingsAnalytics($tenantId, $dateRange),
            'tasks' => $this->getTasksAnalytics($tenantId, $dateRange),
            'companies' => $this->getCompaniesAnalytics($tenantId, $dateRange),
            'forecast' => $this->getForecastAnalytics($tenantId, $dateRange),
            'lead_scoring' => $this->getLeadScoringAnalytics($tenantId, $dateRange),
            'journeys' => $this->getJourneysAnalytics($tenantId, $dateRange),
            'visitor_intent' => $this->getVisitorIntentAnalytics($tenantId, $dateRange),
            'generated_at' => now()->toISOString(),
            'period' => [
                'start' => $dateRange['start']->format('Y-m-d'),
                'end' => $dateRange['end']->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Get contacts analytics.
     */
    private function getContactsAnalytics(int $tenantId, array $dateRange): array
    {
        $total = Contact::forTenant($tenantId)->count();
        
        $newThisPeriod = Contact::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $withEmail = Contact::forTenant($tenantId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();
        
        $withPhone = Contact::forTenant($tenantId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->count();
        
        $highScore = Contact::forTenant($tenantId)
            ->where('lead_score', '>=', 80)
            ->count();
        
        return [
            'total' => $total,
            'new_this_period' => $newThisPeriod,
            'with_email' => $withEmail,
            'with_phone' => $withPhone,
            'high_score' => $highScore,
            'email_percentage' => $total > 0 ? round(($withEmail / $total) * 100, 1) : 0,
            'phone_percentage' => $total > 0 ? round(($withPhone / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get deals analytics.
     */
    private function getDealsAnalytics(int $tenantId, array $dateRange): array
    {
        $total = Deal::forTenant($tenantId)->count();
        
        $open = Deal::forTenant($tenantId)->where('status', 'open')->count();
        $won = Deal::forTenant($tenantId)->where('status', 'won')->count();
        $lost = Deal::forTenant($tenantId)->where('status', 'lost')->count();
        
        $totalValue = Deal::forTenant($tenantId)->sum('value') ?? 0;
        $openValue = Deal::forTenant($tenantId)->where('status', 'open')->sum('value') ?? 0;
        $wonValue = Deal::forTenant($tenantId)->where('status', 'won')->sum('value') ?? 0;
        
        $newThisPeriod = Deal::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $wonThisPeriod = Deal::forTenant($tenantId)
            ->where('status', 'won')
            ->whereBetween('closed_date', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $wonValueThisPeriod = Deal::forTenant($tenantId)
            ->where('status', 'won')
            ->whereBetween('closed_date', [$dateRange['start'], $dateRange['end']])
            ->sum('value') ?? 0;
        
        $winRate = $total > 0 ? round(($won / $total) * 100, 1) : 0;
        $avgDealSize = $won > 0 ? round($wonValue / $won, 2) : 0;
        
        return [
            'total' => $total,
            'open' => $open,
            'won' => $won,
            'lost' => $lost,
            'total_value' => round($totalValue, 2),
            'open_value' => round($openValue, 2),
            'won_value' => round($wonValue, 2),
            'new_this_period' => $newThisPeriod,
            'won_this_period' => $wonThisPeriod,
            'won_value_this_period' => round($wonValueThisPeriod, 2),
            'win_rate' => $winRate,
            'avg_deal_size' => $avgDealSize,
        ];
    }

    /**
     * Get campaigns analytics.
     */
    private function getCampaignsAnalytics(int $tenantId, array $dateRange): array
    {
        $total = Campaign::forTenant($tenantId)->count();
        
        $sent = Campaign::forTenant($tenantId)
            ->where('status', 'sent')
            ->count();
        
        $draft = Campaign::forTenant($tenantId)
            ->where('status', 'draft')
            ->count();
        
        $scheduled = Campaign::forTenant($tenantId)
            ->where('status', 'scheduled')
            ->count();
        
        // Get campaign performance metrics
        $campaigns = Campaign::forTenant($tenantId)
            ->where('status', 'sent')
            ->get();
        
        $totalSent = $campaigns->sum('sent_count') ?? 0;
        $totalOpens = $campaigns->sum('opened_count') ?? 0;
        $totalClicks = $campaigns->sum('clicked_count') ?? 0;
        $totalBounces = $campaigns->sum('bounced_count') ?? 0;
        $totalUnsubscribes = 0; // This would need to be calculated from campaign_recipients
        
        $openRate = $totalSent > 0 ? round(($totalOpens / $totalSent) * 100, 1) : 0;
        $clickRate = $totalSent > 0 ? round(($totalClicks / $totalSent) * 100, 1) : 0;
        $bounceRate = $totalSent > 0 ? round(($totalBounces / $totalSent) * 100, 1) : 0;
        $unsubscribeRate = $totalSent > 0 ? round(($totalUnsubscribes / $totalSent) * 100, 1) : 0;
        
        $sentThisPeriod = Campaign::forTenant($tenantId)
            ->where('status', 'sent')
            ->whereBetween('sent_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        return [
            'total' => $total,
            'sent' => $sent,
            'draft' => $draft,
            'scheduled' => $scheduled,
            'total_sent' => $totalSent,
            'total_opens' => $totalOpens,
            'total_clicks' => $totalClicks,
            'total_bounces' => $totalBounces,
            'total_unsubscribes' => $totalUnsubscribes,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
            'bounce_rate' => $bounceRate,
            'unsubscribe_rate' => $unsubscribeRate,
            'sent_this_period' => $sentThisPeriod,
        ];
    }

    /**
     * Get ads analytics.
     */
    private function getAdsAnalytics(int $tenantId, array $dateRange): array
    {
        $totalAccounts = AdAccount::forTenant($tenantId)->count();
        
        // Since ad_accounts table doesn't have performance columns yet,
        // we'll return basic account information
        $activeAccounts = AdAccount::forTenant($tenantId)
            ->where('is_active', true)
            ->count();
        
        $providers = AdAccount::forTenant($tenantId)
            ->select('provider')
            ->distinct()
            ->pluck('provider')
            ->toArray();
        
        return [
            'total_accounts' => $totalAccounts,
            'active_accounts' => $activeAccounts,
            'providers' => $providers,
            'impressions' => 0, // Placeholder - would come from external API
            'clicks' => 0, // Placeholder - would come from external API
            'spent' => 0, // Placeholder - would come from external API
            'conversions' => 0, // Placeholder - would come from external API
            'ctr' => 0, // Placeholder - would be calculated from external data
            'cpc' => 0, // Placeholder - would be calculated from external data
            'conversion_rate' => 0, // Placeholder - would be calculated from external data
            'cpa' => 0, // Placeholder - would be calculated from external data
        ];
    }

    /**
     * Get events analytics.
     */
    private function getEventsAnalytics(int $tenantId, array $dateRange): array
    {
        $total = Event::forTenant($tenantId)->count();
        
        $upcoming = Event::forTenant($tenantId)
            ->where('scheduled_at', '>', now())
            ->count();
        
        $completed = Event::forTenant($tenantId)
            ->where('scheduled_at', '<', now())
            ->count();
        
        $totalAttendees = EventAttendee::whereHas('event', function ($query) use ($tenantId) {
            $query->forTenant($tenantId);
        })->count();
        
        $newThisPeriod = Event::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'completed' => $completed,
            'attendees' => $totalAttendees,
            'total_capacity' => 0, // Placeholder - would need capacity field
            'utilization_rate' => 0, // Placeholder - would be calculated with capacity
            'new_this_period' => $newThisPeriod,
        ];
    }

    /**
     * Get meetings analytics.
     */
    private function getMeetingsAnalytics(int $tenantId, array $dateRange): array
    {
        $total = Meeting::forTenant($tenantId)->count();
        
        $scheduled = Meeting::forTenant($tenantId)->where('status', 'scheduled')->count();
        $completed = Meeting::forTenant($tenantId)->where('status', 'completed')->count();
        $cancelled = Meeting::forTenant($tenantId)->where('status', 'cancelled')->count();
        $noShow = Meeting::forTenant($tenantId)->where('status', 'no_show')->count();
        
        $upcoming = Meeting::forTenant($tenantId)
            ->where('scheduled_at', '>', now())
            ->where('status', 'scheduled')
            ->count();
        
        $thisPeriod = Meeting::forTenant($tenantId)
            ->whereBetween('scheduled_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $totalDuration = Meeting::forTenant($tenantId)->sum('duration_minutes') ?? 0;
        $avgDuration = $total > 0 ? round($totalDuration / $total, 1) : 0;
        
        return [
            'total' => $total,
            'scheduled' => $scheduled,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'no_show' => $noShow,
            'upcoming' => $upcoming,
            'this_period' => $thisPeriod,
            'total_duration_minutes' => $totalDuration,
            'avg_duration_minutes' => $avgDuration,
        ];
    }

    /**
     * Get tasks analytics.
     */
    private function getTasksAnalytics(int $tenantId, array $dateRange): array
    {
        $total = Task::forTenant($tenantId)->count();
        
        $pending = Task::forTenant($tenantId)->where('status', 'pending')->count();
        $inProgress = Task::forTenant($tenantId)->where('status', 'in_progress')->count();
        $completed = Task::forTenant($tenantId)->where('status', 'completed')->count();
        $cancelled = Task::forTenant($tenantId)->where('status', 'cancelled')->count();
        
        $overdue = Task::forTenant($tenantId)
            ->where('due_date', '<', now())
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();
        
        $completedThisPeriod = Task::forTenant($tenantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        
        return [
            'total' => $total,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'overdue' => $overdue,
            'completed_this_period' => $completedThisPeriod,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * Get companies analytics.
     */
    private function getCompaniesAnalytics(int $tenantId, array $dateRange): array
    {
        $total = Company::forTenant($tenantId)->count();
        
        $withWebsite = Company::forTenant($tenantId)
            ->whereNotNull('website')
            ->where('website', '!=', '')
            ->count();
        
        $withIndustry = Company::forTenant($tenantId)
            ->whereNotNull('industry')
            ->where('industry', '!=', '')
            ->count();
        
        $newThisPeriod = Company::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        return [
            'total' => $total,
            'with_website' => $withWebsite,
            'with_industry' => $withIndustry,
            'new_this_period' => $newThisPeriod,
            'website_percentage' => $total > 0 ? round(($withWebsite / $total) * 100, 1) : 0,
            'industry_percentage' => $total > 0 ? round(($withIndustry / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get forecast analytics.
     */
    private function getForecastAnalytics(int $tenantId, array $dateRange): array
    {
        $openDeals = Deal::forTenant($tenantId)
            ->where('status', 'open')
            ->whereNotNull('value')
            ->whereNotNull('probability')
            ->get();
        
        $totalDeals = $openDeals->count();
        $projectedValue = $openDeals->sum('value') ?? 0;
        $probabilityWeighted = $openDeals->sum(function ($deal) {
            return $deal->value * ($deal->probability / 100);
        });
        
        $avgDealSize = $totalDeals > 0 ? round($projectedValue / $totalDeals, 2) : 0;
        $avgProbability = $totalDeals > 0 ? round($openDeals->avg('probability'), 1) : 0;
        
        return [
            'total_deals' => $totalDeals,
            'projected_value' => round($projectedValue, 2),
            'probability_weighted' => round($probabilityWeighted, 2),
            'avg_deal_size' => $avgDealSize,
            'avg_probability' => $avgProbability,
        ];
    }

    /**
     * Get lead scoring analytics.
     */
    private function getLeadScoringAnalytics(int $tenantId, array $dateRange): array
    {
        $totalRules = LeadScoringRule::forTenant($tenantId)->count();
        $activeRules = LeadScoringRule::forTenant($tenantId)->where('is_active', true)->count();
        
        $highScoreContacts = Contact::forTenant($tenantId)->where('lead_score', '>=', 80)->count();
        $mediumScoreContacts = Contact::forTenant($tenantId)->whereBetween('lead_score', [50, 79])->count();
        $lowScoreContacts = Contact::forTenant($tenantId)->where('lead_score', '<', 50)->count();
        
        $avgScore = Contact::forTenant($tenantId)->avg('lead_score') ?? 0;
        
        return [
            'total_rules' => $totalRules,
            'active_rules' => $activeRules,
            'high_score_contacts' => $highScoreContacts,
            'medium_score_contacts' => $mediumScoreContacts,
            'low_score_contacts' => $lowScoreContacts,
            'avg_score' => round($avgScore, 1),
        ];
    }

    /**
     * Get journeys analytics.
     */
    private function getJourneysAnalytics(int $tenantId, array $dateRange): array
    {
        $total = Journey::forTenant($tenantId)->count();
        $active = Journey::forTenant($tenantId)->where('status', 'active')->count();
        $draft = Journey::forTenant($tenantId)->where('status', 'draft')->count();
        
        $totalExecutions = JourneyExecution::forTenant($tenantId)->count();
        $runningExecutions = JourneyExecution::forTenant($tenantId)->where('status', 'running')->count();
        $completedExecutions = JourneyExecution::forTenant($tenantId)->where('status', 'completed')->count();
        
        return [
            'total' => $total,
            'active' => $active,
            'draft' => $draft,
            'total_executions' => $totalExecutions,
            'running_executions' => $runningExecutions,
            'completed_executions' => $completedExecutions,
        ];
    }

    /**
     * Get visitor intent analytics.
     */
    private function getVisitorIntentAnalytics(int $tenantId, array $dateRange): array
    {
        $total = VisitorIntent::forTenant($tenantId)->count();
        
        $thisPeriod = VisitorIntent::forTenant($tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $highIntent = VisitorIntent::forTenant($tenantId)
            ->where('intent_score', '>=', 80)
            ->count();
        
        $avgIntentScore = VisitorIntent::forTenant($tenantId)->avg('intent_score') ?? 0;
        
        return [
            'total' => $total,
            'this_period' => $thisPeriod,
            'high_intent' => $highIntent,
            'avg_intent_score' => round($avgIntentScore, 1),
        ];
    }

    /**
     * Get date range based on filters.
     */
    private function getDateRange(array $filters): array
    {
        $period = $filters['period'] ?? 'month';
        
        switch ($period) {
            case 'week':
                $start = now()->startOfWeek();
                $end = now()->endOfWeek();
                break;
            case 'quarter':
                $start = now()->startOfQuarter();
                $end = now()->endOfQuarter();
                break;
            case 'year':
                $start = now()->startOfYear();
                $end = now()->endOfYear();
                break;
            case 'month':
            default:
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Get analytics for specific module.
     */
    public function getModuleAnalytics(int $tenantId, string $module, array $filters = []): array
    {
        $dateRange = $this->getDateRange($filters);
        
        switch ($module) {
            case 'contacts':
                return $this->getContactsAnalytics($tenantId, $dateRange);
            case 'deals':
                return $this->getDealsAnalytics($tenantId, $dateRange);
            case 'campaigns':
                return $this->getCampaignsAnalytics($tenantId, $dateRange);
            case 'ads':
                return $this->getAdsAnalytics($tenantId, $dateRange);
            case 'events':
                return $this->getEventsAnalytics($tenantId, $dateRange);
            case 'meetings':
                return $this->getMeetingsAnalytics($tenantId, $dateRange);
            case 'tasks':
                return $this->getTasksAnalytics($tenantId, $dateRange);
            case 'companies':
                return $this->getCompaniesAnalytics($tenantId, $dateRange);
            case 'forecast':
                return $this->getForecastAnalytics($tenantId, $dateRange);
            case 'lead_scoring':
                return $this->getLeadScoringAnalytics($tenantId, $dateRange);
            case 'journeys':
                return $this->getJourneysAnalytics($tenantId, $dateRange);
            case 'visitor_intent':
                return $this->getVisitorIntentAnalytics($tenantId, $dateRange);
            default:
                throw new \InvalidArgumentException("Unknown module: {$module}");
        }
    }
}
