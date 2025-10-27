<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoKeyword;
use App\Models\SeoMetric;
use App\Models\SeoPage;
use App\Models\SeoRecommendation;
use App\Models\SeoReport;
use App\Models\SeoReportKeyword;
use App\Models\SeoToken;
use App\Models\UserSeoSite;
use App\Services\GoogleSearchConsoleService;
use App\Services\SeoAnalyticsService;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class SeoController extends Controller
{
    protected $googleSearchConsoleService;
    protected $seoAnalyticsService;

    public function __construct(
        GoogleSearchConsoleService $googleSearchConsoleService,
        SeoAnalyticsService $seoAnalyticsService
    ) {
        $this->googleSearchConsoleService = $googleSearchConsoleService;
        $this->seoAnalyticsService = $seoAnalyticsService;
    }

    // ==================== NEW ENHANCED API METHODS ====================

    /**
     * Initiate Google Search Console connection (Frontend endpoint)
     * POST /api/seo/connect - Called when user clicks "Connect Google Search Console" button
     */
    public function initiateConnection(Request $request)
    {
        try {
            $user = auth()->user();
            $authUrl = $this->googleSearchConsoleService->getAuthorizationUrl($user->id);
            
            return response()->json([
                'success' => true,
                'status' => 'redirect_required',
                'auth_url' => $authUrl,
                'message' => 'Please authorize access to Google Search Console'
            ]);
        } catch (Exception $e) {
            Log::error('Connection initiation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to initiate connection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check Google Search Console connection status
     * GET /api/seo/connect - Check if user has connected account
     */
    public function checkConnection(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Check SeoToken
            $token = SeoToken::getForUser($user->id);
            
            if (!$token || !$token->access_token) {
                return response()->json([
                    'success' => true,
                    'connected' => false,
                    'message' => 'Google Search Console not connected'
                ]);
            }

            // Check if token is expired
            $isExpired = $token->isExpired();

            return response()->json([
                'success' => true,
                'connected' => !$isExpired,
                'site_url' => $token->site_url,
                'expires_at' => $token->expires_at?->toIso8601String(),
                'is_expired' => $isExpired,
                'message' => $isExpired ? 'Token expired - reconnection required' : 'Connected to Google Search Console'
            ]);
        } catch (Exception $e) {
            Log::error('Check connection error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'connected' => false,
                'message' => 'Failed to check connection status'
            ], 500);
        }
    }

    /**
     * Redirect to Google OAuth (alias for frontend compatibility)
     */
    public function redirectToGoogle()
    {
        return $this->oauthRedirect(request());
    }

    /**
     * Handle Google OAuth callback (enhanced with SeoToken storage)
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $code = $request->input('code');
            $state = json_decode($request->input('state'), true);
            
            if (!$code) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authorization code not provided'
                ], 400);
            }

            $userId = $state['user_id'] ?? auth()->id();
            
            // Get Google client
            $client = $this->getGoogleClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                throw new Exception($token['error_description'] ?? 'OAuth error');
            }

            // Store in SeoToken model
            $siteUrl = config('services.google_search.site_url') ?? env('GOOGLE_SEARCH_SITE_URL');
            
            SeoToken::storeForUser(
                $userId,
                $token['access_token'],
                $token['refresh_token'] ?? null,
                $token['expires_in'] ?? 3600,
                $siteUrl
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully connected to Google Search Console',
                'expires_in' => $token['expires_in'] ?? 3600
            ]);
        } catch (Exception $e) {
            Log::error('OAuth callback error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Authorization failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get dashboard data with aggregated metrics
     */
    public function getDashboardData(Request $request)
    {
        try {
            $user = auth()->user();
            $days = $request->input('days', 30);

            // First, try to get from local database
            $dashboardData = $this->seoAnalyticsService->getDashboardSummary($user->id, $days);

            // If no local data, try to fetch from Google and sync
            if ($dashboardData['metrics']['total_clicks'] == 0) {
                try {
                    $this->syncData($user->id);
                    $dashboardData = $this->seoAnalyticsService->getDashboardSummary($user->id, $days);
                } catch (Exception $e) {
                    Log::warning("Could not sync data: " . $e->getMessage());
                }
            }

            // Get trend data
            $trendData = SeoMetric::where('user_id', $user->id)
                ->whereBetween('date', [now()->subDays($days), now()])
                ->orderBy('date', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'summary' => $dashboardData['metrics'],
                    'top_pages' => $dashboardData['top_pages'],
                    'recommendations' => $dashboardData['recommendations'],
                    'trends' => $trendData,
                    'period' => [
                        'days' => $days,
                        'start_date' => now()->subDays($days)->toDateString(),
                        'end_date' => now()->toDateString()
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Dashboard data error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all pages with pagination
     */
    public function getAllPages(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 50);
            $sortBy = $request->input('sort_by', 'clicks');
            $sortOrder = $request->input('sort_order', 'desc');

            $pages = SeoPage::where('user_id', $user->id)
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $pages
            ]);
        } catch (Exception $e) {
            Log::error('Get pages error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch pages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pages (alias - returns array format for frontend compatibility)
     */
    public function getPages(Request $request)
    {
        try {
            $user = auth()->user();
            $siteUrl = $request->input('site_url');
            $limit = $request->input('limit', 50);

            $query = SeoPage::where('user_id', $user->id);

            if ($siteUrl) {
                $query->where('page_url', 'LIKE', $siteUrl . '%');
            }

            $pages = $query->orderBy('clicks', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($page) {
                    return [
                        'id' => $page->id,
                        'page_url' => $page->page_url,
                        'title' => $page->title,
                        'clicks' => $page->clicks,
                        'impressions' => $page->impressions,
                        'ctr' => (float) $page->ctr,
                        'position' => (float) $page->position,
                        'last_fetched_at' => $page->last_fetched_at?->toIso8601String()
                    ];
                })
                ->toArray();

            // Return plain array
            return response()->json($pages);
        } catch (Exception $e) {
            Log::error('SEO Pages Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch page data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get individual page detail
     */
    public function getPageDetail($id)
    {
        try {
            $user = auth()->user();
            
            $page = SeoPage::where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            // Get recommendations for this page
            $recommendations = SeoRecommendation::where('user_id', $user->id)
                ->where('page_url', $page->page_url)
                ->where('is_resolved', false)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'page' => $page,
                    'recommendations' => $recommendations
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Get page detail error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Page not found'
            ], 404);
        }
    }

    /**
     * Get all SEO recommendations
     */
    public function getAllRecommendations(Request $request)
    {
        try {
            $user = auth()->user();
            $severity = $request->input('severity');
            $type = $request->input('type');

            $query = SeoRecommendation::where('user_id', $user->id)
                ->where('is_resolved', false);

            if ($severity) {
                $query->where('severity', $severity);
            }

            if ($type) {
                $query->where('recommendation_type', $type);
            }

            $recommendations = $query->orderBy('severity', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($rec) {
                    return [
                        'page_url' => $rec->page_url,
                        'message' => $rec->message,
                        'severity' => $rec->severity,
                        'recommendation_type' => $rec->recommendation_type,
                        'created_at' => $rec->created_at->toIso8601String()
                    ];
                })
                ->toArray(); // Ensure it's an array

            // Return plain array as expected by frontend
            return response()->json($recommendations);
        } catch (Exception $e) {
            Log::error('Get recommendations error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recommendations (alias for backward compatibility)
     * Matches the legacy format expected by some frontends
     */
    public function getRecommendations(Request $request)
    {
        try {
            $user = auth()->user();
            $siteUrl = $request->input('site_url');

            // Get from database first
            $query = SeoRecommendation::where('user_id', $user->id)
                ->where('is_resolved', false);

            if ($siteUrl) {
                $query->where('page_url', 'LIKE', $siteUrl . '%');
            }

            $dbRecommendations = $query->orderBy('severity', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($rec) {
                    return [
                        'page_url' => $rec->page_url,
                        'message' => $rec->message,
                        'severity' => $rec->severity,
                        'recommendation_type' => $rec->recommendation_type
                    ];
                })
                ->toArray();

            // If we have database recommendations, return them
            if (!empty($dbRecommendations)) {
                return response()->json($dbRecommendations);
            }

            // Otherwise, generate basic recommendations from pages
            $pages = SeoPage::where('user_id', $user->id)->get();
            $recommendations = [];

            foreach ($pages as $page) {
                if ($page->ctr < 0.5 && $page->impressions > 100) {
                    $recommendations[] = [
                        'page_url' => $page->page_url,
                        'message' => 'Improve meta title or description to increase CTR',
                        'severity' => 'medium',
                        'recommendation_type' => 'low_ctr'
                    ];
                }

                if ($page->position > 20 && $page->impressions > 50) {
                    $recommendations[] = [
                        'page_url' => $page->page_url,
                        'message' => 'Page ranking is low. Improve content quality and SEO optimization',
                        'severity' => 'high',
                        'recommendation_type' => 'poor_ranking'
                    ];
                }
            }

            // Return plain array as expected by frontend
            return response()->json($recommendations);
        } catch (Exception $e) {
            Log::error('SEO Recommendations Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update SEO settings
     */
    public function updateSettings(Request $request)
    {
        try {
            $user = auth()->user();
            
            $validator = Validator::make($request->all(), [
                'site_url' => 'nullable|url',
                'sync_frequency' => 'nullable|in:daily,weekly,manual'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update site URL in token if provided
            if ($request->has('site_url')) {
                $token = SeoToken::getForUser($user->id);
                if ($token) {
                    $token->update(['site_url' => $request->site_url]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Settings updated successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Update settings error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual sync trigger
     */
    public function syncNow(Request $request)
    {
        try {
            $user = auth()->user();
            $days = $request->input('days', 30);

            $result = $this->syncData($user->id, $days);

            return response()->json([
                'status' => 'success',
                'message' => 'Data synced successfully',
                'data' => $result
            ]);
        } catch (Exception $e) {
            Log::error('Sync error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to sync data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Internal method to sync data
     */
    protected function syncData($userId, $days = 30)
    {
        // Sync metrics
        $metricsResult = $this->seoAnalyticsService->syncMetricsForUser($userId, $days);
        
        // Sync pages
        $pagesResult = $this->seoAnalyticsService->syncPagesForUser($userId, 7);
        
        // Generate recommendations
        $recommendationsResult = $this->seoAnalyticsService->generateRecommendations($userId);

        return [
            'metrics_synced' => $metricsResult,
            'pages_synced' => $pagesResult,
            'recommendations_generated' => $recommendationsResult
        ];
    }

    /**
     * Get Google API client
     */
    protected function getGoogleClient()
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google_search.client_id') ?? env('GOOGLE_SEARCH_CLIENT_ID'));
        $client->setClientSecret(config('services.google_search.client_secret') ?? env('GOOGLE_SEARCH_CLIENT_SECRET'));
        $client->setRedirectUri(config('services.google_search.redirect_uri') ?? env('GOOGLE_SEARCH_REDIRECT_URI'));
        $client->addScope('https://www.googleapis.com/auth/webmasters.readonly');
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        return $client;
    }

    // ==================== GOOGLE SEARCH CONSOLE INTEGRATION ====================

    /**
     * Redirect to Google OAuth
     */
    public function oauthRedirect(Request $request)
    {
        try {
            $user = auth()->user();
            $authUrl = $this->googleSearchConsoleService->getAuthorizationUrl($user->id);
            
            return response()->json([
                'status' => 'success',
                'auth_url' => $authUrl
            ]);
        } catch (Exception $e) {
            Log::error('OAuth redirect error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate OAuth URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function oauthCallback(Request $request)
    {
        try {
            $code = $request->input('code');
            $state = json_decode($request->input('state'), true);
            
            if (!$code) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authorization code not provided'
                ], 400);
            }

            $userId = $state['user_id'] ?? auth()->id();
            $siteUrl = $request->input('site_url', $request->input('siteUrl'));

            if (!$siteUrl) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Site URL is required'
                ], 400);
            }

            $site = $this->googleSearchConsoleService->handleCallback($code, $userId, $siteUrl);

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully connected to Google Search Console',
                'site' => $site
            ]);
        } catch (Exception $e) {
            Log::error('OAuth callback error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Authorization failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get SEO metrics from Google Search Console
     */
    public function getMetrics(Request $request)
    {
        try {
            $user = auth()->user();
            $siteUrl = $request->input('site_url');
            $days = $request->input('days', 7);

            // Get the connected site
            $site = UserSeoSite::where('user_id', $user->id)
                ->where('is_connected', true);

            if ($siteUrl) {
                $site->where('site_url', $siteUrl);
            }

            $site = $site->first();

            if (!$site) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No connected Google Search Console site found'
                ], 404);
            }

            $metrics = $this->googleSearchConsoleService->getMetrics($site, $days);

            return response()->json([
                'status' => 'success',
                'data' => $metrics
            ]);
        } catch (Exception $e) {
            Log::error('SEO Metrics Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch SEO metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get page performance data from Google Search Console (Legacy - Real-time API)
     * @deprecated Use getPages() instead for database-cached data
     */
    public function getPagesFromGoogle(Request $request)
    {
        try {
            $user = auth()->user();
            $siteUrl = $request->input('site_url');
            $days = $request->input('days', 7);

            // Get the connected site
            $site = UserSeoSite::where('user_id', $user->id)
                ->where('is_connected', true);

            if ($siteUrl) {
                $site->where('site_url', $siteUrl);
            }

            $site = $site->first();

            if (!$site) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No connected Google Search Console site found'
                ], 404);
            }

            $pages = $this->googleSearchConsoleService->getPages($site, $days);

            return response()->json([
                'status' => 'success',
                'data' => $pages
            ]);
        } catch (Exception $e) {
            Log::error('SEO Pages Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch page data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SEO recommendations from crawl data (Legacy)
     * @deprecated Use getRecommendations() instead for database-backed recommendations
     */
    public function getRecommendationsFromCrawlData(Request $request)
    {
        try {
            $user = auth()->user();
            $siteUrl = $request->input('site_url');

            // Get the connected site
            $site = UserSeoSite::where('user_id', $user->id)
                ->where('is_connected', true);

            if ($siteUrl) {
                $site->where('site_url', $siteUrl);
            }

            $site = $site->first();

            $recommendations = [];

            // Generate recommendations based on crawl data
            if ($site && $site->crawl_data) {
                $crawlData = $site->crawl_data;
                
                foreach ($crawlData['pages'] ?? [] as $page) {
                    // Missing meta description
                    if (empty($page['metaDescription'])) {
                        $recommendations[] = [
                            'title' => 'Add meta description to ' . $page['title'],
                            'priority' => 'High',
                            'page' => $page['url'],
                            'description' => 'Meta descriptions improve click-through rates from search results'
                        ];
                    }

                    // Missing H1
                    if (empty($page['h1'])) {
                        $recommendations[] = [
                            'title' => 'Add H1 tag to ' . $page['title'],
                            'priority' => 'High',
                            'page' => $page['url'],
                            'description' => 'H1 tags help search engines understand your page content'
                        ];
                    }

                    // Images without alt text
                    if (isset($page['imagesWithoutAlt']) && $page['imagesWithoutAlt'] > 0) {
                        $recommendations[] = [
                            'title' => "Add alt text to {$page['imagesWithoutAlt']} images on " . $page['title'],
                            'priority' => 'Medium',
                            'page' => $page['url'],
                            'description' => 'Alt text improves accessibility and image SEO'
                        ];
                    }

                    // Slow loading
                    if (isset($page['loadTime']) && $page['loadTime'] > 3) {
                        $recommendations[] = [
                            'title' => 'Improve page speed for ' . $page['title'],
                            'priority' => 'High',
                            'page' => $page['url'],
                            'description' => "Page loads in {$page['loadTime']}s. Target: <3s"
                        ];
                    }
                }
            }

            // Add general recommendations
            if (empty($recommendations)) {
                $recommendations = [
                    [
                        'title' => 'Optimize title tags with target keywords',
                        'priority' => 'High',
                        'description' => 'Include primary keywords in page titles for better rankings'
                    ],
                    [
                        'title' => 'Reduce image file sizes',
                        'priority' => 'Medium',
                        'description' => 'Compress images to improve page load speed'
                    ],
                    [
                        'title' => 'Add internal links between related pages',
                        'priority' => 'Medium',
                        'description' => 'Internal linking helps search engines understand site structure'
                    ],
                    [
                        'title' => 'Create XML sitemap',
                        'priority' => 'High',
                        'description' => 'Sitemaps help search engines discover and index your pages'
                    ],
                    [
                        'title' => 'Implement schema markup',
                        'priority' => 'Medium',
                        'description' => 'Structured data helps search engines understand your content'
                    ]
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => array_slice($recommendations, 0, 10)
            ]);
        } catch (Exception $e) {
            Log::error('SEO Recommendations Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SEO settings and connection status
     */
    public function getSettings(Request $request)
    {
        try {
            $user = auth()->user();
            $siteUrl = $request->input('site_url');

            // Get the connected site
            $site = UserSeoSite::where('user_id', $user->id)
                ->where('is_connected', true);

            if ($siteUrl) {
                $site->where('site_url', $siteUrl);
            }

            $site = $site->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'connected' => $site ? true : false,
                    'site_url' => $site?->site_url,
                    'site_name' => $site?->site_name,
                    'last_synced' => $site?->last_synced?->toIso8601String(),
                    'token_expires_at' => $site?->google_token_expires_at?->toIso8601String(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('SEO Settings Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch settings: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== LEGACY SITE MANAGEMENT METHODS ====================

    /**
     * Save website for SEO tracking
     */
    public function saveSite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_url' => 'required|url',
            'site_name' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        
        // Check if site already exists
        $existingSite = UserSeoSite::where('user_id', $user->id)
            ->where('site_url', $request->site_url)
            ->first();

        if ($existingSite) {
            $existingSite->update([
                'site_name' => $request->site_name,
                'updated_at' => now()
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Site updated successfully',
                'site' => $existingSite
            ]);
        }

        $site = UserSeoSite::create([
            'user_id' => $user->id,
            'site_url' => $request->site_url,
            'site_name' => $request->site_name ?? $this->extractDomain($request->site_url)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Site added successfully',
            'site' => $site
        ]);
    }

    /**
     * Connect to Google Search Console
     */
    public function connectGoogleSearchConsole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $site = UserSeoSite::where('user_id', $user->id)
            ->where('site_url', $request->site_url)
            ->first();

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Site not found'
            ], 404);
        }

        // Simulate GSC connection
        $site->update([
            'is_connected' => true,
            'gsc_property' => $this->extractDomain($request->site_url),
            'last_synced' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully connected to Google Search Console',
            'site' => $site
        ]);
    }

    /**
     * Start full site scan (Frontend endpoint)
     * POST /api/seo/scan - Initiates site crawling and analysis
     */
    public function startSiteScan(Request $request)
    {
        // Alias to crawlWebsite for frontend compatibility
        return $this->crawlWebsite($request);
    }

    /**
     * Get sync status
     * GET /api/seo/sync-status - Check when data was last synced
     */
    public function getSyncStatus(Request $request)
    {
        try {
            $user = auth()->user();
            $site = UserSeoSite::where('user_id', $user->id)
                ->where('is_connected', true)
                ->first();

            if (!$site) {
                return response()->json([
                    'success' => true,
                    'synced' => false,
                    'message' => 'No connected site found'
                ]);
            }

            return response()->json([
                'success' => true,
                'synced' => true,
                'last_synced' => $site->last_synced?->toIso8601String(),
                'site_url' => $site->site_url,
                'data_range' => 'Last 90 days',
                'message' => $site->last_synced ? 'Last synced ' . $site->last_synced->diffForHumans() : 'Never synced'
            ]);
        } catch (Exception $e) {
            Log::error('Sync status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync status'
            ], 500);
        }
    }

    /**
     * Disconnect from Google Search Console
     */
    public function disconnectGoogleSearchConsole(Request $request)
    {
        $user = auth()->user();
        
        // Delete SeoToken
        $token = SeoToken::where('user_id', $user->id)->first();
        if ($token) {
            $token->delete();
        }
        
        // Update UserSeoSite
        $site = UserSeoSite::where('user_id', $user->id)
            ->where('is_connected', true)
            ->first();

        if ($site) {
            $site->update([
                'is_connected' => false,
                'gsc_property' => null,
                'last_synced' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Disconnected from Google Search Console'
        ]);
    }

    /**
     * Get user's connected sites
     */
    public function getSites()
    {
        $user = auth()->user();
        $sites = UserSeoSite::where('user_id', $user->id)->get();

        return response()->json([
            'success' => true,
            'sites' => $sites
        ]);
    }

    /**
     * Crawl website and extract data
     */
    public function crawlWebsite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $site = UserSeoSite::where('user_id', $user->id)
            ->where('site_url', $request->site_url)
            ->first();

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Site not found'
            ], 404);
        }

        // Simulate crawling process
        $crawlData = $this->performCrawl($request->site_url);
        
        // Save crawl data
        $site->update([
            'crawl_data' => $crawlData,
            'last_synced' => now()
        ]);

        // Extract and save keywords
        $this->extractAndSaveKeywords($site, $crawlData);

        return response()->json([
            'success' => true,
            'message' => 'Website crawled successfully',
            'crawl_data' => $crawlData
        ]);
    }

    /**
     * Get crawl data for a site
     */
    public function getCrawlData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $site = UserSeoSite::where('user_id', $user->id)
            ->where('site_url', $request->site_url)
            ->first();

        if (!$site || !$site->crawl_data) {
            return response()->json([
                'success' => false,
                'message' => 'No crawl data found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'crawl_data' => $site->crawl_data,
            'crawled_at' => $site->last_synced
        ]);
    }

    /**
     * Get SEO analysis and recommendations
     */
    public function getSeoAnalysis(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $site = UserSeoSite::where('user_id', $user->id)
            ->where('site_url', $request->site_url)
            ->first();

        if (!$site || !$site->crawl_data) {
            return response()->json([
                'success' => false,
                'message' => 'No crawl data available'
            ], 404);
        }

        $analysis = $this->analyzeSeoData($site->crawl_data);
        
        return response()->json([
            'success' => true,
            'analysis' => $analysis
        ]);
    }

    /**
     * Get keyword frequency analysis
     */
    public function getKeywordFrequency(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $site = UserSeoSite::where('user_id', $user->id)
            ->where('site_url', $request->site_url)
            ->first();

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Site not found'
            ], 404);
        }

        $keywords = SeoKeyword::where('user_id', $user->id)
            ->whereHas('reportKeywords', function($query) use ($site) {
                $query->whereHas('seoReport', function($q) use ($site) {
                    $q->where('url', $site->site_url);
                });
            })
            ->with(['reportKeywords' => function($query) use ($site) {
                $query->whereHas('seoReport', function($q) use ($site) {
                    $q->where('url', $site->site_url);
                });
            }])
            ->get()
            ->map(function($keyword) {
                $totalFrequency = $keyword->reportKeywords->sum('frequency');
                return [
                    'word' => $keyword->keyword,
                    'frequency' => $totalFrequency,
                    'sources' => $keyword->reportKeywords->pluck('source')->unique()->toArray()
                ];
            })
            ->sortByDesc('frequency')
            ->values();

        return response()->json([
            'success' => true,
            'keywords' => $keywords
        ]);
    }

    /**
     * Mark SEO recommendation as resolved
     */
    /**
     * Resolve a recommendation
     * POST /api/seo/recommendations/:id/resolve
     */
    public function resolveRecommendation($id)
    {
        try {
            $user = auth()->user();
            
            $recommendation = SeoRecommendation::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$recommendation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recommendation not found'
                ], 404);
            }

            $recommendation->update([
                'is_resolved' => true,
                'resolved_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recommendation marked as resolved',
                'recommendation' => [
                    'id' => $recommendation->id,
                    'page_url' => $recommendation->page_url,
                    'is_resolved' => true,
                    'resolved_at' => $recommendation->resolved_at->toIso8601String()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Resolve recommendation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve recommendation'
            ], 500);
        }
    }

    /**
     * @deprecated Use resolveRecommendation instead
     */
    public function markRecommendationResolved(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recommendation_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->resolveRecommendation($request->recommendation_id);
    }

    /**
     * Private helper methods
     */
    private function extractDomain($url)
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? 'Unknown';
    }

    private function performCrawl($url)
    {
        // This is a simplified version - in production you'd use a proper crawler
        // For now, return the hardcoded Trendify data or implement actual crawling
        
        if (str_contains($url, 'seo-test-clothing')) {
            return [
                "crawledAt" => now()->toISOString(),
                "pages" => [
                    [
                        "url" => "https://seo-test-clothing.com/",
                        "title" => "SEO Test Clothing - Premium Fashion Store",
                        "metaDescription" => "Discover the latest fashion trends with SEO Test Clothing. Premium quality clothing for men and women. Free shipping on orders over $50.",
                        "h1" => ["Welcome to SEO Test Clothing"],
                        "h2" => ["Featured Products", "New Arrivals", "Best Sellers"],
                        "h3" => ["Men's Collection", "Women's Collection", "Accessories"],
                        "statusCode" => 200,
                        "loadTime" => 1.2,
                        "wordCount" => 450,
                        "internalLinks" => 12,
                        "externalLinks" => 3,
                        "images" => 8,
                        "imagesWithoutAlt" => 2
                    ],
                    [
                        "url" => "https://seo-test-clothing.com/mens",
                        "title" => "Men's Clothing - SEO Test Clothing",
                        "metaDescription" => "Shop the latest men's fashion at SEO Test Clothing. Premium quality shirts, pants, jackets and more.",
                        "h1" => ["Men's Collection"],
                        "h2" => ["Shirts", "Pants", "Jackets", "Accessories"],
                        "h3" => ["Casual Wear", "Formal Wear", "Sports Wear"],
                        "statusCode" => 200,
                        "loadTime" => 0.8,
                        "wordCount" => 320,
                        "internalLinks" => 8,
                        "externalLinks" => 1,
                        "images" => 15,
                        "imagesWithoutAlt" => 1
                    ],
                    [
                        "url" => "https://seo-test-clothing.com/womens",
                        "title" => "Women's Clothing - SEO Test Clothing",
                        "metaDescription" => "Discover elegant women's fashion at SEO Test Clothing. Dresses, tops, bottoms and accessories for every occasion.",
                        "h1" => ["Women's Collection"],
                        "h2" => ["Dresses", "Tops", "Bottoms", "Accessories"],
                        "h3" => ["Casual Dresses", "Evening Wear", "Work Wear"],
                        "statusCode" => 200,
                        "loadTime" => 0.9,
                        "wordCount" => 380,
                        "internalLinks" => 10,
                        "externalLinks" => 2,
                        "images" => 20,
                        "imagesWithoutAlt" => 3
                    ]
                ]
            ];
        }

        // Implement actual crawling logic here
        return [
            "crawledAt" => now()->toISOString(),
            "pages" => []
        ];
    }

    private function extractAndSaveKeywords($site, $crawlData)
    {
        $user = $site->user;
        $keywords = [];

        foreach ($crawlData['pages'] ?? [] as $page) {
            // Extract from title
            $titleWords = $this->extractWords($page['title'] ?? '');
            foreach ($titleWords as $word) {
                $keywords[] = [
                    'keyword' => $word,
                    'source' => 'title',
                    'frequency' => 1
                ];
            }

            // Extract from meta description
            if (!empty($page['metaDescription'])) {
                $metaWords = $this->extractWords($page['metaDescription']);
                foreach ($metaWords as $word) {
                    $keywords[] = [
                        'keyword' => $word,
                        'source' => 'meta',
                        'frequency' => 1
                    ];
                }
            }

            // Extract from headings
            foreach (($page['h1'] ?? []) as $heading) {
                $headingWords = $this->extractWords($heading);
                foreach ($headingWords as $word) {
                    $keywords[] = [
                        'keyword' => $word,
                        'source' => 'heading',
                        'frequency' => 1
                    ];
                }
            }
        }

        // Group by keyword and source, sum frequencies
        $groupedKeywords = collect($keywords)
            ->groupBy(function($item) {
                return $item['keyword'] . '|' . $item['source'];
            })
            ->map(function($group) {
                return [
                    'keyword' => $group->first()['keyword'],
                    'source' => $group->first()['source'],
                    'frequency' => $group->sum('frequency')
                ];
            })
            ->values();

        // Create or find SEO report for this site
        $seoReport = SeoReport::firstOrCreate([
            'user_id' => $user->id,
            'url' => $site->site_url
        ], [
            'name' => $site->site_name ?? $this->extractDomain($site->site_url),
            'report_type' => 'site_audit',
            'status' => 'completed',
            'crawl_data' => $crawlData,
            'crawled_at' => now()
        ]);

        // Save keywords to database
        foreach ($groupedKeywords as $keywordData) {
            $keyword = SeoKeyword::firstOrCreate([
                'user_id' => $user->id,
                'keyword' => $keywordData['keyword']
            ]);

            // Create or update report keyword relationship
            SeoReportKeyword::updateOrCreate([
                'seo_report_id' => $seoReport->id,
                'seo_keyword_id' => $keyword->id,
                'source' => $keywordData['source']
            ], [
                'frequency' => $keywordData['frequency']
            ]);
        }
    }

    private function extractWords($text)
    {
        $words = preg_split('/\s+/', strtolower($text));
        return array_filter($words, function($word) {
            return strlen($word) > 3 && !in_array($word, [
                'this', 'that', 'with', 'from', 'they', 'were', 'been', 'have', 
                'their', 'said', 'each', 'which', 'them', 'more', 'very', 'what', 
                'know', 'just', 'first', 'into', 'over', 'think', 'also', 'your', 
                'work', 'life', 'only', 'can', 'still', 'should', 'after', 'being', 
                'now', 'made', 'before', 'here', 'through', 'when', 'where', 'much', 
                'some', 'these', 'many', 'then', 'well', 'were', 'details', 'product'
            ]);
        });
    }

    private function analyzeSeoData($crawlData)
    {
        $issues = [];
        $score = 100;

        foreach ($crawlData['pages'] ?? [] as $page) {
            // Check for missing meta description
            if (empty($page['metaDescription'])) {
                $issues[] = [
                    'type' => 'missing_meta_description',
                    'severity' => 'Medium',
                    'page' => $page['url'],
                    'title' => $page['title'],
                    'description' => 'Add a compelling meta description (150-160 characters)'
                ];
                $score -= 10;
            }

            // Check for missing H1 tags
            if (empty($page['h1'])) {
                $issues[] = [
                    'type' => 'missing_h1',
                    'severity' => 'High',
                    'page' => $page['url'],
                    'title' => $page['title'],
                    'description' => 'Add a descriptive H1 tag for better SEO structure'
                ];
                $score -= 15;
            }

            // Check for multiple H1 tags
            if (count($page['h1']) > 1) {
                $issues[] = [
                    'type' => 'multiple_h1',
                    'severity' => 'Medium',
                    'page' => $page['url'],
                    'title' => $page['title'],
                    'description' => 'Use only one H1 tag per page'
                ];
                $score -= 5;
            }

            // Check for images without alt text
            if (isset($page['imagesWithoutAlt']) && $page['imagesWithoutAlt'] > 0) {
                $issues[] = [
                    'type' => 'missing_alt_text',
                    'severity' => 'Medium',
                    'page' => $page['url'],
                    'title' => $page['title'],
                    'description' => "Add alt text to {$page['imagesWithoutAlt']} images for better accessibility and SEO"
                ];
                $score -= 5;
            }

            // Check for slow loading pages
            if (isset($page['loadTime']) && $page['loadTime'] > 3) {
                $issues[] = [
                    'type' => 'slow_loading',
                    'severity' => 'High',
                    'page' => $page['url'],
                    'title' => $page['title'],
                    'description' => "Page loads in {$page['loadTime']}s. Optimize for faster loading (target: <3s)"
                ];
                $score -= 10;
            }
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'total_pages' => count($crawlData['pages'] ?? []),
            'successful_pages' => count(array_filter($crawlData['pages'] ?? [], function($page) {
                return ($page['statusCode'] ?? 0) === 200;
            }))
        ];
    }
}
