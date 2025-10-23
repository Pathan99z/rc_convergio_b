<?php

namespace App\Services;

use App\Models\UserSeoSite;
use Google\Client as Google_Client;
use Google\Service\SearchConsole as Google_Service_SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleSearchConsoleService
{
    protected $client;

    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * Initialize Google API Client
     */
    protected function initializeClient()
    {
        $this->client = new Google_Client();
        
        // Set auth config from environment or file
        if (config('services.google.client_id') && config('services.google.client_secret')) {
            $this->client->setClientId(config('services.google.client_id'));
            $this->client->setClientSecret(config('services.google.client_secret'));
            $this->client->setRedirectUri(config('services.google.redirect_uri'));
        } elseif (file_exists(storage_path('app/google/client_secret.json'))) {
            $this->client->setAuthConfig(storage_path('app/google/client_secret.json'));
        } else {
            Log::warning('Google Search Console credentials not configured');
        }

        $this->client->addScope(Google_Service_SearchConsole::WEBMASTERS_READONLY);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * Get authorization URL
     */
    public function getAuthorizationUrl(int $userId)
    {
        $this->client->setState(json_encode(['user_id' => $userId]));
        return $this->client->createAuthUrl();
    }

    /**
     * Handle OAuth callback and store tokens
     */
    public function handleCallback(string $code, int $userId, string $siteUrl)
    {
        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                throw new Exception($token['error_description'] ?? 'OAuth error');
            }

            $site = UserSeoSite::where('user_id', $userId)
                ->where('site_url', $siteUrl)
                ->first();

            if (!$site) {
                $site = UserSeoSite::create([
                    'user_id' => $userId,
                    'site_url' => $siteUrl,
                    'site_name' => $this->extractDomain($siteUrl),
                ]);
            }

            $site->update([
                'google_access_token' => $token['access_token'],
                'google_refresh_token' => $token['refresh_token'] ?? null,
                'google_token_expires_at' => now()->addSeconds($token['expires_in']),
                'is_connected' => true,
                'gsc_property' => $siteUrl,
                'last_synced' => now()
            ]);

            return $site;
        } catch (Exception $e) {
            Log::error('Google OAuth callback error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Set access token for a site
     */
    public function setAccessToken(UserSeoSite $site)
    {
        if (!$site->google_access_token) {
            throw new Exception('No access token available');
        }

        // Check if token is expired and refresh if needed
        if ($site->google_token_expires_at && $site->google_token_expires_at->isPast()) {
            if ($site->google_refresh_token) {
                $this->refreshAccessToken($site);
            } else {
                throw new Exception('Token expired and no refresh token available');
            }
        }

        $this->client->setAccessToken([
            'access_token' => $site->google_access_token,
            'refresh_token' => $site->google_refresh_token,
            'expires_in' => $site->google_token_expires_at ? $site->google_token_expires_at->diffInSeconds(now()) : 3600,
        ]);
    }

    /**
     * Refresh access token
     */
    protected function refreshAccessToken(UserSeoSite $site)
    {
        try {
            $this->client->setAccessToken([
                'access_token' => $site->google_access_token,
                'refresh_token' => $site->google_refresh_token,
            ]);

            $token = $this->client->fetchAccessTokenWithRefreshToken($site->google_refresh_token);
            
            if (isset($token['error'])) {
                throw new Exception($token['error_description'] ?? 'Token refresh error');
            }

            $site->update([
                'google_access_token' => $token['access_token'],
                'google_token_expires_at' => now()->addSeconds($token['expires_in']),
            ]);

            Log::info('Access token refreshed for site: ' . $site->site_url);
        } catch (Exception $e) {
            Log::error('Token refresh error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get Search Console metrics
     */
    public function getMetrics(UserSeoSite $site, int $days = 7)
    {
        $cacheKey = "seo_metrics_{$site->id}_{$days}";
        
        return Cache::remember($cacheKey, 86400, function () use ($site, $days) {
            try {
                $this->setAccessToken($site);
                $service = new Google_Service_SearchConsole($this->client);

                $request = new SearchAnalyticsQueryRequest();
                $request->setStartDate(now()->subDays($days)->toDateString());
                $request->setEndDate(now()->toDateString());
                $request->setDimensions(['query']);
                $request->setRowLimit(50);

                $response = $service->searchanalytics->query($site->site_url, $request);
                
                $metrics = [
                    'totalClicks' => 0,
                    'totalImpressions' => 0,
                    'averageCTR' => 0,
                    'averagePosition' => 0,
                    'keywords' => []
                ];

                $rows = $response->getRows() ?? [];
                
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $metrics['keywords'][] = [
                            'keyword' => $row->getKeys()[0],
                            'clicks' => $row->getClicks(),
                            'impressions' => $row->getImpressions(),
                            'ctr' => round($row->getCtr() * 100, 2),
                            'position' => round($row->getPosition(), 2)
                        ];
                    }

                    $metrics['totalClicks'] = array_sum(array_column($metrics['keywords'], 'clicks'));
                    $metrics['totalImpressions'] = array_sum(array_column($metrics['keywords'], 'impressions'));
                    
                    if ($metrics['totalImpressions'] > 0) {
                        $metrics['averageCTR'] = round(($metrics['totalClicks'] / $metrics['totalImpressions']) * 100, 2);
                    }
                    
                    if (count($metrics['keywords']) > 0) {
                        $metrics['averagePosition'] = round(array_sum(array_column($metrics['keywords'], 'position')) / count($metrics['keywords']), 2);
                    }
                }

                return $metrics;
            } catch (Exception $e) {
                Log::error('SEO Metrics Error: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Get page performance data
     */
    public function getPages(UserSeoSite $site, int $days = 7)
    {
        $cacheKey = "seo_pages_{$site->id}_{$days}";
        
        return Cache::remember($cacheKey, 86400, function () use ($site, $days) {
            try {
                $this->setAccessToken($site);
                $service = new Google_Service_SearchConsole($this->client);

                $request = new SearchAnalyticsQueryRequest();
                $request->setStartDate(now()->subDays($days)->toDateString());
                $request->setEndDate(now()->toDateString());
                $request->setDimensions(['page']);
                $request->setRowLimit(50);

                $response = $service->searchanalytics->query($site->site_url, $request);
                
                $pages = [];
                $rows = $response->getRows() ?? [];

                foreach ($rows as $row) {
                    $pages[] = [
                        'url' => $row->getKeys()[0],
                        'clicks' => $row->getClicks(),
                        'impressions' => $row->getImpressions(),
                        'ctr' => round($row->getCtr() * 100, 2),
                        'position' => round($row->getPosition(), 2)
                    ];
                }

                return $pages;
            } catch (Exception $e) {
                Log::error('SEO Pages Error: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Get available sites from Google Search Console
     */
    public function getAvailableSites(UserSeoSite $site)
    {
        try {
            $this->setAccessToken($site);
            $service = new Google_Service_SearchConsole($this->client);
            
            $sitesList = $service->sites->listSites();
            $sites = [];
            
            foreach ($sitesList->getSiteEntry() as $siteEntry) {
                $sites[] = [
                    'siteUrl' => $siteEntry->getSiteUrl(),
                    'permissionLevel' => $siteEntry->getPermissionLevel()
                ];
            }
            
            return $sites;
        } catch (Exception $e) {
            Log::error('Error fetching GSC sites: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Disconnect Google Search Console
     */
    public function disconnect(UserSeoSite $site)
    {
        $site->update([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'is_connected' => false,
            'gsc_property' => null,
        ]);
    }

    /**
     * Extract domain from URL
     */
    protected function extractDomain($url)
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? 'Unknown';
    }

    /**
     * Clear cache for a site
     */
    public function clearCache(UserSeoSite $site)
    {
        Cache::forget("seo_metrics_{$site->id}_7");
        Cache::forget("seo_metrics_{$site->id}_30");
        Cache::forget("seo_pages_{$site->id}_7");
        Cache::forget("seo_pages_{$site->id}_30");
    }
}
