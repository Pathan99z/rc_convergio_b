<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\TeamAccessService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CampaignsController extends Controller
{
    public function __construct(
        private CacheRepository $cache, 
        private DB $db,
        private TeamAccessService $teamAccessService
    ) {}

    public function metrics(Request $request): JsonResponse
    {
        $range = (string) $request->query('range', '14d');
        $userId = $request->user()->id;
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        // Handle tenant_id fallback logic (same as other controllers)
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        
        $cacheKey = "campaigns:metrics:user:{$userId}:tenant:{$tenantId}:range:{$range}";

        $data = $this->cache->remember($cacheKey, 300, function () use ($range, $tenantId, $request) {
            if (! $this->db->connection()->getSchemaBuilder()->hasTable('campaigns')) {
                return [
                    'total_campaigns' => 0,
                    'delivered' => 0,
                    'opens' => 0,
                    'clicks' => 0,
                    'bounces' => 0,
                    'range' => $range,
                ];
            }

            $now = Carbon::now();
            $since = match (true) {
                str_ends_with($range, 'd') => $now->copy()->subDays((int) rtrim($range, 'd')),
                str_ends_with($range, 'w') => $now->copy()->subWeeks((int) rtrim($range, 'w')),
                default => $now->copy()->subDays(14),
            };

            // Use the campaigns table which has metrics columns directly
            // Add tenant isolation for security
            $q = $this->db->table('campaigns')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $since);
            
            // ✅ FIX: Apply team filtering to campaign metrics
            $user = $request->user();
            if ($this->teamAccessService->enabled() && $user->team_id) {
                $q->where(function($query) use ($user) {
                    $query->where('team_id', $user->team_id)
                          ->orWhereNull('team_id'); // Admin-created campaigns
                });
            }
            
            // Get total campaigns count for the tenant (all time, not just within range)
            $totalCampaignsQuery = $this->db->table('campaigns')
                ->where('tenant_id', $tenantId);
            
            // ✅ FIX: Apply team filtering to total campaigns count
            if ($this->teamAccessService->enabled() && $user->team_id) {
                $totalCampaignsQuery->where(function($query) use ($user) {
                    $query->where('team_id', $user->team_id)
                          ->orWhereNull('team_id'); // Admin-created campaigns
                });
            }
            
            $totalCampaigns = $totalCampaignsQuery->count();
            
            $delivered = (clone $q)->sum('delivered_count');
            $opens = (clone $q)->sum('opened_count');
            $clicks = (clone $q)->sum('clicked_count');
            $bounces = (clone $q)->sum('bounced_count');

            return [
                'total_campaigns' => $totalCampaigns,
                'delivered' => $delivered,
                'opens' => $opens,
                'clicks' => $clicks,
                'bounces' => $bounces,
                'range' => $range,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function trends(Request $request): JsonResponse
    {
        $range = (string) $request->query('range', '30d');
        $interval = (string) $request->query('interval', 'daily');
        $userId = $request->user()->id;
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        // Handle tenant_id fallback logic (same as other controllers)
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        
        $cacheKey = "campaigns:trends:user:{$userId}:tenant:{$tenantId}:range:{$range}:interval:{$interval}";

        $data = $this->cache->remember($cacheKey, 300, function () use ($range, $interval, $tenantId, $request) {
            if (! $this->db->connection()->getSchemaBuilder()->hasTable('campaign_recipients')) {
                return [];
            }

            $now = Carbon::now();
            $since = match (true) {
                str_ends_with($range, 'd') => $now->copy()->subDays((int) rtrim($range, 'd')),
                str_ends_with($range, 'w') => $now->copy()->subWeeks((int) rtrim($range, 'w')),
                default => $now->copy()->subDays(30),
            };

            // Determine date format based on interval
            $dateFormat = $interval === 'weekly' ? '%Y-%u' : '%Y-%m-%d';
            $dateGroupBy = $interval === 'weekly' ? 'YEARWEEK(sent_at)' : 'DATE(sent_at)';

            // Query campaign_recipients for time-series data
            $trendsQuery = $this->db->table('campaign_recipients')
                ->join('campaigns', 'campaign_recipients.campaign_id', '=', 'campaigns.id')
                ->select([
                    $this->db->raw("DATE_FORMAT(campaign_recipients.sent_at, '{$dateFormat}') as date"),
                    $this->db->raw('COUNT(CASE WHEN campaign_recipients.sent_at IS NOT NULL THEN 1 END) as sent'),
                    $this->db->raw('COUNT(CASE WHEN campaign_recipients.delivered_at IS NOT NULL THEN 1 END) as delivered'),
                    $this->db->raw('COUNT(CASE WHEN campaign_recipients.opened_at IS NOT NULL THEN 1 END) as opens'),
                    $this->db->raw('COUNT(CASE WHEN campaign_recipients.clicked_at IS NOT NULL THEN 1 END) as clicks'),
                    $this->db->raw('COUNT(CASE WHEN campaign_recipients.bounced_at IS NOT NULL THEN 1 END) as bounces'),
                ])
                ->where('campaigns.tenant_id', $tenantId)
                ->where('campaign_recipients.sent_at', '>=', $since)
                ->whereNotNull('campaign_recipients.sent_at');
            
            // ✅ FIX: Apply team filtering to trends
            $user = $request->user();
            if ($this->teamAccessService->enabled() && $user->team_id) {
                $trendsQuery->where(function($query) use ($user) {
                    $query->where('campaigns.team_id', $user->team_id)
                          ->orWhereNull('campaigns.team_id'); // Admin-created campaigns
                });
            }
            
            $trends = $trendsQuery
                ->groupBy($this->db->raw("DATE_FORMAT(campaign_recipients.sent_at, '{$dateFormat}')"))
                ->orderBy('date')
                ->get();

            // Convert to array and format dates
            $result = [];
            foreach ($trends as $trend) {
                $date = $trend->date;
                
                // Format date for weekly intervals
                if ($interval === 'weekly') {
                    // Convert YYYY-WW format to a readable date (start of week)
                    $year = substr($date, 0, 4);
                    $week = substr($date, 5, 2);
                    $date = Carbon::now()->setISODate($year, $week)->format('Y-m-d');
                }

                $result[] = [
                    'date' => $date,
                    'sent' => (int) $trend->sent,
                    'delivered' => (int) $trend->delivered,
                    'opens' => (int) $trend->opens,
                    'clicks' => (int) $trend->clicks,
                    'bounces' => (int) $trend->bounces,
                ];
            }

            return $result;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}


