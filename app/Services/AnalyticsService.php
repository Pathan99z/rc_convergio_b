<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Company;
use App\Models\Deal;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\VisitorIntent;
use App\Models\LeadScoringRule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get comprehensive analytics for the dashboard.
     */
    public function getDashboardAnalytics(int $tenantId): array
    {
        $contacts = $this->getContactAnalytics($tenantId);
        $companies = $this->getCompanyAnalytics($tenantId);
        $deals = $this->getDealAnalytics($tenantId);
        $activities = $this->getActivityAnalytics($tenantId);
        $campaigns = $this->getCampaignAnalytics($tenantId);
        $events = $this->getEventAnalytics($tenantId);
        $intent = $this->getIntentAnalytics($tenantId);
        $leadScoring = $this->getLeadScoringAnalytics($tenantId);

        return [
            'contacts' => $contacts,
            'companies' => $companies,
            'deals' => $deals,
            'activities' => $activities,
            'campaigns' => $campaigns,
            'events' => $events,
            'intent' => $intent,
            'lead_scoring' => $leadScoring,
        ];
    }

    /**
     * Get contact analytics.
     */
    public function getContactAnalytics(int $tenantId): array
    {
        $total = Contact::forTenant($tenantId)->count();
        $thisMonth = Contact::forTenant($tenantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        $lastMonth = Contact::forTenant($tenantId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        
        $highScore = Contact::forTenant($tenantId)
            ->where('lead_score', '>=', 80)
            ->count();
        
        $avgScore = Contact::forTenant($tenantId)->avg('lead_score') ?? 0;
        
        return [
            'total' => $total,
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'high_score' => $highScore,
            'avg_score' => round($avgScore, 1),
            'growth_rate' => $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0,
        ];
    }

    /**
     * Get company analytics.
     */
    public function getCompanyAnalytics(int $tenantId): array
    {
        $total = Company::forTenant($tenantId)->count();
        $thisMonth = Company::forTenant($tenantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        $lastMonth = Company::forTenant($tenantId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        
        return [
            'total' => $total,
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'growth_rate' => $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0,
        ];
    }

    /**
     * Get deal analytics.
     */
    public function getDealAnalytics(int $tenantId): array
    {
        $total = Deal::forTenant($tenantId)->count();
        $thisMonth = Deal::forTenant($tenantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        $won = Deal::forTenant($tenantId)->where('status', 'won')->count();
        $lost = Deal::forTenant($tenantId)->where('status', 'lost')->count();
        $active = Deal::forTenant($tenantId)->where('status', 'active')->count();
        
        $totalValue = Deal::forTenant($tenantId)->sum('value');
        $wonValue = Deal::forTenant($tenantId)->where('status', 'won')->sum('value');
        
        return [
            'total' => $total,
            'this_month' => $thisMonth,
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
    public function getActivityAnalytics(int $tenantId): array
    {
        $total = Activity::forTenant($tenantId)->count();
        $thisMonth = Activity::forTenant($tenantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        $lastMonth = Activity::forTenant($tenantId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        
        $byType = Activity::forTenant($tenantId)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');
        
        return [
            'total' => $total,
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'by_type' => $byType,
            'growth_rate' => $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0,
        ];
    }

    /**
     * Get campaign analytics.
     */
    public function getCampaignAnalytics(int $tenantId): array
    {
        $total = Campaign::forTenant($tenantId)->count();
        $active = Campaign::forTenant($tenantId)->where('status', 'active')->count();
        $completed = Campaign::forTenant($tenantId)->where('status', 'completed')->count();
        $draft = Campaign::forTenant($tenantId)->where('status', 'draft')->count();
        
        $thisMonth = Campaign::forTenant($tenantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed,
            'draft' => $draft,
            'this_month' => $thisMonth,
        ];
    }

    /**
     * Get event analytics.
     */
    public function getEventAnalytics(int $tenantId): array
    {
        $total = Event::forTenant($tenantId)->count();
        $upcoming = Event::forTenant($tenantId)
            ->where('start_date', '>=', now())
            ->count();
        
        $thisMonth = Event::forTenant($tenantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        $totalAttendees = Event::forTenant($tenantId)
            ->withCount('attendees')
            ->get()
            ->sum('attendees_count');
        
        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'this_month' => $thisMonth,
            'total_attendees' => $totalAttendees,
        ];
    }

    /**
     * Get intent analytics.
     */
    public function getIntentAnalytics(int $tenantId): array
    {
        $total = VisitorIntent::forTenant($tenantId)->count();
        $thisPeriod = VisitorIntent::forTenant($tenantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        
        $highIntent = VisitorIntent::forTenant($tenantId)
            ->where('score', '>=', 80)
            ->count();
        
        $avgIntentScore = VisitorIntent::forTenant($tenantId)->avg('score') ?? 0;
        
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
    public function getLeadScoringAnalytics(int $tenantId): array
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
        return Campaign::forTenant($tenantId)
            ->withCount('recipients')
            ->orderBy('recipients_count', 'desc')
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
        return Activity::forTenant($tenantId)
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
}