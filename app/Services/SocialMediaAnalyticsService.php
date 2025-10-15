<?php

namespace App\Services;

use App\Models\SocialMediaAnalytic;
use App\Models\SocialAccount;
use App\Services\SocialMedia\FacebookService;
use App\Services\SocialMedia\TwitterService;
use App\Services\SocialMedia\InstagramService;
use App\Services\SocialMedia\LinkedInService;
use Illuminate\Support\Facades\Log;

class SocialMediaAnalyticsService
{
    protected array $platformServices;

    public function __construct()
    {
        $this->platformServices = [
            'facebook' => new FacebookService(),
            'twitter' => new TwitterService(),
            'instagram' => new InstagramService(),
            'linkedin' => new LinkedInService(),
        ];
    }

    /**
     * Fetch and store analytics for a specific platform
     */
    public function fetchPlatformAnalytics(int $userId, string $platform): array
    {
        try {
            $service = $this->platformServices[$platform] ?? null;
            
            if (!$service) {
                throw new \Exception("Platform not supported: {$platform}");
            }

            $account = SocialAccount::where('user_id', $userId)
                                   ->where('platform', $platform)
                                   ->where('is_active', true)
                                   ->first();

            if (!$account) {
                throw new \Exception("No active account found for platform: {$platform}");
            }

            // Fetch platform-specific metrics
            $metrics = $this->fetchPlatformMetrics($platform, $account);

            // Store metrics in database
            foreach ($metrics as $metricName => $metricValue) {
                SocialMediaAnalytic::create([
                    'user_id' => $userId,
                    'platform' => $platform,
                    'metric_name' => $metricName,
                    'metric_value' => (string) $metricValue,
                    'metric_date' => now()->toDateString(),
                    'additional_data' => ['fetched_at' => now()->toIso8601String()]
                ]);
            }

            return [
                'success' => true,
                'metrics' => $metrics,
                'platform' => $platform
            ];

        } catch (\Exception $e) {
            Log::error('Failed to fetch platform analytics', [
                'platform' => $platform,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'platform' => $platform
            ];
        }
    }

    /**
     * Fetch analytics for all connected platforms
     */
    public function fetchAllAnalytics(int $userId): array
    {
        $results = [];

        $accounts = SocialAccount::where('user_id', $userId)
                                ->where('is_active', true)
                                ->get();

        foreach ($accounts as $account) {
            $results[$account->platform] = $this->fetchPlatformAnalytics($userId, $account->platform);
        }

        return $results;
    }

    /**
     * Get historical analytics data
     */
    public function getHistoricalAnalytics(int $userId, string $platform, string $metricName, $startDate, $endDate): array
    {
        $analytics = SocialMediaAnalytic::where('user_id', $userId)
                                       ->where('platform', $platform)
                                       ->where('metric_name', $metricName)
                                       ->whereBetween('metric_date', [$startDate, $endDate])
                                       ->orderBy('metric_date')
                                       ->get();

        return $analytics->map(function ($analytic) {
            return [
                'date' => $analytic->metric_date->toDateString(),
                'value' => $analytic->metric_value,
                'additional_data' => $analytic->additional_data
            ];
        })->toArray();
    }

    /**
     * Get analytics summary for a platform
     */
    public function getPlatformSummary(int $userId, string $platform): array
    {
        $latestMetrics = SocialMediaAnalytic::where('user_id', $userId)
                                           ->where('platform', $platform)
                                           ->where('metric_date', now()->toDateString())
                                           ->get()
                                           ->pluck('metric_value', 'metric_name')
                                           ->toArray();

        return [
            'platform' => $platform,
            'metrics' => $latestMetrics,
            'last_updated' => now()->toIso8601String()
        ];
    }

    /**
     * Fetch metrics from platform API
     */
    protected function fetchPlatformMetrics(string $platform, SocialAccount $account): array
    {
        switch ($platform) {
            case 'facebook':
                return $this->fetchFacebookMetrics($account);
            
            case 'twitter':
                return $this->fetchTwitterMetrics($account);
            
            case 'instagram':
                return $this->fetchInstagramMetrics($account);
            
            case 'linkedin':
                return $this->fetchLinkedInMetrics($account);
            
            default:
                return [];
        }
    }

    /**
     * Fetch Facebook metrics
     */
    protected function fetchFacebookMetrics(SocialAccount $account): array
    {
        // In production, make actual API calls to Facebook Graph API
        return [
            'followers' => rand(100, 10000),
            'page_impressions' => rand(1000, 50000),
            'page_engaged_users' => rand(100, 5000),
            'page_views' => rand(500, 20000),
        ];
    }

    /**
     * Fetch Twitter metrics
     */
    protected function fetchTwitterMetrics(SocialAccount $account): array
    {
        return [
            'followers' => rand(100, 10000),
            'tweet_impressions' => rand(1000, 50000),
            'profile_visits' => rand(100, 5000),
            'mentions' => rand(10, 500),
        ];
    }

    /**
     * Fetch Instagram metrics
     */
    protected function fetchInstagramMetrics(SocialAccount $account): array
    {
        return [
            'followers' => rand(100, 10000),
            'impressions' => rand(1000, 50000),
            'reach' => rand(500, 25000),
            'profile_views' => rand(100, 5000),
        ];
    }

    /**
     * Fetch LinkedIn metrics
     */
    protected function fetchLinkedInMetrics(SocialAccount $account): array
    {
        return [
            'followers' => rand(100, 10000),
            'impressions' => rand(1000, 50000),
            'engagement_rate' => rand(1, 10),
            'shares' => rand(10, 500),
        ];
    }
}


