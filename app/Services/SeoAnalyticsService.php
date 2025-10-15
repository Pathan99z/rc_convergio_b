<?php

namespace App\Services;

use App\Models\SeoMetric;
use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoToken;
use App\Services\GoogleSearchConsoleService;
use Google\Client as Google_Client;
use Google\Service\SearchConsole as Google_Service_SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Support\Facades\Log;
use Exception;

class SeoAnalyticsService
{
    protected $googleService;

    public function __construct(GoogleSearchConsoleService $googleService)
    {
        $this->googleService = $googleService;
    }

    /**
     * Sync metrics from Google Search Console to local database
     */
    public function syncMetricsForUser($userId, $days = 30)
    {
        try {
            $token = SeoToken::getForUser($userId);
            
            if (!$token) {
                throw new Exception('No Google Search Console token found for user');
            }

            // Get user's site
            $siteUrl = $token->site_url ?? config('services.google_search.site_url');
            
            if (!$siteUrl) {
                throw new Exception('Site URL not configured');
            }

            // Fetch data from Google
            $startDate = now()->subDays($days)->toDateString();
            $endDate = now()->toDateString();

            $metrics = $this->fetchDateMetrics($userId, $startDate, $endDate);
            
            // Store metrics
            foreach ($metrics as $metric) {
                SeoMetric::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'date' => $metric['date']
                    ],
                    [
                        'clicks' => $metric['clicks'],
                        'impressions' => $metric['impressions'],
                        'ctr' => $metric['ctr'],
                        'position' => $metric['position']
                    ]
                );
            }

            Log::info("Successfully synced SEO metrics for user {$userId}");
            
            return ['success' => true, 'synced_days' => count($metrics)];
        } catch (Exception $e) {
            Log::error("Failed to sync metrics for user {$userId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync pages from Google Search Console
     */
    public function syncPagesForUser($userId, $days = 7)
    {
        try {
            $token = SeoToken::getForUser($userId);
            
            if (!$token) {
                throw new Exception('No Google Search Console token found');
            }

            $pages = $this->fetchPageMetrics($userId, $days);
            
            foreach ($pages as $pageData) {
                SeoPage::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'page_url' => $pageData['url']
                    ],
                    [
                        'title' => $pageData['title'] ?? $this->extractTitleFromUrl($pageData['url']),
                        'clicks' => $pageData['clicks'],
                        'impressions' => $pageData['impressions'],
                        'ctr' => $pageData['ctr'],
                        'position' => $pageData['position'],
                        'last_fetched_at' => now()
                    ]
                );
            }

            Log::info("Successfully synced {$pages->count()} pages for user {$userId}");
            
            return ['success' => true, 'synced_pages' => count($pages)];
        } catch (Exception $e) {
            Log::error("Failed to sync pages for user {$userId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate SEO recommendations based on data
     */
    public function generateRecommendations($userId)
    {
        try {
            $pages = SeoPage::where('user_id', $userId)->get();
            $recommendationsCount = 0;

            foreach ($pages as $page) {
                // Low CTR recommendation
                if ($page->ctr < 2.0 && $page->impressions > 100) {
                    $this->createRecommendation($userId, $page->page_url, 'low_ctr', 
                        "Page has low CTR ({$page->ctr}%). Consider improving meta title and description.",
                        'medium'
                    );
                    $recommendationsCount++;
                }

                // High impressions but low clicks
                if ($page->impressions > 1000 && $page->clicks < 50) {
                    $this->createRecommendation($userId, $page->page_url, 'wasted_impressions',
                        "Page receives {$page->impressions} impressions but only {$page->clicks} clicks. Optimize meta description.",
                        'high'
                    );
                    $recommendationsCount++;
                }

                // Poor position
                if ($page->position > 20 && $page->impressions > 100) {
                    $this->createRecommendation($userId, $page->page_url, 'poor_ranking',
                        "Page ranks at position {$page->position}. Consider improving on-page SEO and content quality.",
                        'medium'
                    );
                    $recommendationsCount++;
                }

                // Good position but low CTR
                if ($page->position < 10 && $page->ctr < 5.0) {
                    $this->createRecommendation($userId, $page->page_url, 'underperforming_ranking',
                        "Page ranks well (position {$page->position}) but has low CTR. Improve title tag to increase clicks.",
                        'high'
                    );
                    $recommendationsCount++;
                }
            }

            Log::info("Generated {$recommendationsCount} recommendations for user {$userId}");
            
            return ['success' => true, 'recommendations_created' => $recommendationsCount];
        } catch (Exception $e) {
            Log::error("Failed to generate recommendations for user {$userId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a recommendation (avoid duplicates)
     */
    protected function createRecommendation($userId, $pageUrl, $type, $message, $severity)
    {
        // Check if similar recommendation already exists and is unresolved
        $exists = SeoRecommendation::where('user_id', $userId)
            ->where('page_url', $pageUrl)
            ->where('recommendation_type', $type)
            ->where('is_resolved', false)
            ->exists();

        if (!$exists) {
            SeoRecommendation::create([
                'user_id' => $userId,
                'page_url' => $pageUrl,
                'recommendation_type' => $type,
                'message' => $message,
                'severity' => $severity
            ]);
        }
    }

    /**
     * Fetch date-based metrics from Google Search Console API
     */
    protected function fetchDateMetrics($userId, $startDate, $endDate)
    {
        $token = SeoToken::getForUser($userId);
        
        if (!$token || !$token->access_token) {
            Log::warning("No GSC token for user {$userId}");
            return [];
        }

        try {
            // Initialize Google Client
            $client = new Google_Client();
            $client->setClientId(config('services.google_search.client_id'));
            $client->setClientSecret(config('services.google_search.client_secret'));
            $client->setAccessToken($token->access_token);

            // Refresh token if expired
            if ($client->isAccessTokenExpired()) {
                if ($token->refresh_token) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
                    if (isset($newToken['access_token'])) {
                        $token->update([
                            'access_token' => $newToken['access_token'],
                            'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600)
                        ]);
                        $client->setAccessToken($newToken['access_token']);
                    }
                } else {
                    Log::warning("Token expired and no refresh token available for user {$userId}");
                    return [];
                }
            }

            // Call Google Search Console API
            $service = new Google_Service_SearchConsole($client);
            $request = new SearchAnalyticsQueryRequest();
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);
            $request->setDimensions(['date']);
            $request->setRowLimit(1000);

            $siteUrl = $token->site_url ?: config('services.google_search.site_url');
            if (!$siteUrl) {
                Log::warning("No site URL configured for user {$userId}");
                return [];
            }

            $response = $service->searchanalytics->query($siteUrl, $request);
            $rows = $response->getRows() ?? [];

            $metrics = [];
            foreach ($rows as $row) {
                $metrics[] = [
                    'date' => $row->getKeys()[0],
                    'clicks' => $row->getClicks() ?? 0,
                    'impressions' => $row->getImpressions() ?? 0,
                    'ctr' => round(($row->getCtr() ?? 0) * 100, 2),
                    'position' => round($row->getPosition() ?? 0, 2)
                ];
            }

            Log::info("Fetched " . count($metrics) . " date metrics for user {$userId}");
            return $metrics;
            
        } catch (Exception $e) {
            Log::error("Failed to fetch date metrics for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch page metrics from Google Search Console API
     */
    protected function fetchPageMetrics($userId, $days)
    {
        $token = SeoToken::getForUser($userId);
        
        if (!$token || !$token->access_token) {
            Log::warning("No GSC token for user {$userId}");
            return [];
        }

        try {
            // Initialize Google Client
            $client = new Google_Client();
            $client->setClientId(config('services.google_search.client_id'));
            $client->setClientSecret(config('services.google_search.client_secret'));
            $client->setAccessToken($token->access_token);

            // Refresh token if expired
            if ($client->isAccessTokenExpired()) {
                if ($token->refresh_token) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
                    if (isset($newToken['access_token'])) {
                        $token->update([
                            'access_token' => $newToken['access_token'],
                            'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600)
                        ]);
                        $client->setAccessToken($newToken['access_token']);
                    }
                }
            }

            // Call Google Search Console API
            $service = new Google_Service_SearchConsole($client);
            $request = new SearchAnalyticsQueryRequest();
            $request->setStartDate(now()->subDays($days)->toDateString());
            $request->setEndDate(now()->toDateString());
            $request->setDimensions(['page']);
            $request->setRowLimit(500);

            $siteUrl = $token->site_url ?: config('services.google_search.site_url');
            if (!$siteUrl) {
                Log::warning("No site URL configured for user {$userId}");
                return [];
            }

            $response = $service->searchanalytics->query($siteUrl, $request);
            $rows = $response->getRows() ?? [];

            $pages = [];
            foreach ($rows as $row) {
                $pages[] = [
                    'url' => $row->getKeys()[0],
                    'title' => '', // Will be extracted from URL
                    'clicks' => $row->getClicks() ?? 0,
                    'impressions' => $row->getImpressions() ?? 0,
                    'ctr' => round(($row->getCtr() ?? 0) * 100, 2),
                    'position' => round($row->getPosition() ?? 0, 2)
                ];
            }

            Log::info("Fetched " . count($pages) . " pages for user {$userId}");
            return $pages;
            
        } catch (Exception $e) {
            Log::error("Failed to fetch page metrics for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract page title from URL
     */
    protected function extractTitleFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        return ucwords(str_replace(['/', '-', '_'], ' ', trim($path, '/'))) ?: 'Homepage';
    }

    /**
     * Get dashboard summary
     */
    public function getDashboardSummary($userId, $days = 30)
    {
        $metrics = SeoMetric::getAggregated($userId, $days);
        $topPages = SeoPage::getTopPages($userId, 10);
        $recommendations = SeoRecommendation::getUnresolved($userId)->take(5);

        return [
            'metrics' => [
                'total_clicks' => $metrics->total_clicks ?? 0,
                'total_impressions' => $metrics->total_impressions ?? 0,
                'average_ctr' => round($metrics->avg_ctr ?? 0, 2),
                'average_position' => round($metrics->avg_position ?? 0, 2)
            ],
            'top_pages' => $topPages,
            'recommendations' => $recommendations,
            'period_days' => $days
        ];
    }
}

