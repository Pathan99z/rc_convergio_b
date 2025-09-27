<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntentEvent;
use App\Models\VisitorIntent;
use App\Models\Company;
use App\Models\Contact;
use App\Services\IdentityResolutionService;
use App\Services\ScoringEngineService;
use App\Services\AnalyticsRollupService;
use App\Services\UrlNormalizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TrackingController extends Controller
{
    /**
     * Log a visitor tracking event (Hardened version with idempotency, validation, and security).
     */
    public function logEvent(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $tenantId = null;
        $isAuthenticated = false;
        
        // Handle both authenticated and public tracking scenarios
        if (Auth::check()) {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $isAuthenticated = true;
        } else {
            // Public tracking with tracking_key validation
            $tenantId = $this->validateTrackingKey($request);
        }

        // Enhanced validation with sanitization
        $validatedData = $this->validateAndSanitizeRequest($request);

        // Generate or extract idempotency key
        $idempotencyKey = $this->generateIdempotencyKey($request, $validatedData);

        // Check for duplicate events within 24h
        $existingEvent = $this->checkIdempotency($idempotencyKey, $tenantId);
        if ($existingEvent) {
            $this->logTrackingEvent('duplicate', $tenantId, $validatedData['action'], null, microtime(true) - $startTime);
            return response()->json([
                'data' => $this->formatEventResponse($existingEvent),
                'message' => 'Event already processed (duplicate)',
                'duplicate' => true
            ], 200);
        }

        try {
            // Enhanced visitor tracking with deduplication
            $visitorTrackingService = new \App\Services\VisitorTrackingService();
            $visitorData = $visitorTrackingService->findOrCreateVisitor($validatedData, $tenantId);
            
            $visitor = $visitorData['visitor'];
            $session = $visitorData['session'];
            $isNewVisitor = $visitorData['is_new'];
            $trackingMethod = $visitorData['method'];
            
            // Calculate base score
            $scoringEngine = new ScoringEngineService();
            $baseScore = $scoringEngine->scoreFor($validatedData, $tenantId);
            
            // Calculate cumulative score for returning visitors
            $finalScore = $isNewVisitor ? $baseScore : 
                $visitorTrackingService->calculateCumulativeScore($visitor, $baseScore, $validatedData['action']);
            
            // Create intent event with enhanced visitor data
            // Ensure session exists, create one if it doesn't
            if (!$session) {
                $visitorTrackingService = new \App\Services\VisitorTrackingService();
                $session = $visitorTrackingService->createOrUpdateSession($visitor, $validatedData['session_id'], $tenantId);
            }
            
            $intentEvent = $this->createEnhancedEvent($validatedData, $tenantId, $idempotencyKey, $visitor, $session, $finalScore, $isNewVisitor, $trackingMethod);
            
            // Enrich with contact information if form data is available
            $this->enrichWithContactData($intentEvent, $validatedData, $tenantId);
            
            // Dispatch enrichment job (async processing)
            $this->dispatchEnrichmentJob($intentEvent, $validatedData);

            // Dispatch rollup update job (async processing)
            $this->dispatchRollupUpdateJob($intentEvent);

            $this->logTrackingEvent('accepted', $tenantId, $validatedData['action'], $intentEvent->id, microtime(true) - $startTime);

            return response()->json([
                'data' => $this->formatEnhancedEventResponse($intentEvent, $visitor, $isNewVisitor, $trackingMethod),
                'message' => $isNewVisitor ? 'New visitor tracked successfully' : 'Returning visitor tracked successfully'
            ], 201);

        } catch (\Exception $e) {
            $this->logTrackingEvent('error', $tenantId, $validatedData['action'] ?? 'unknown', null, microtime(true) - $startTime, $e->getMessage());
            return response()->json([
                'message' => 'Failed to log visitor event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get intent signals for contacts and companies.
     */
    public function getIntentSignals(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Note: Removed development mode restriction to allow real data display

        try {
            $query = IntentEvent::where('tenant_id', $tenantId)
                ->with(['company:id,name', 'contact:id,first_name,last_name,email,phone']);

            // Apply filters
            if ($request->has('min_score')) {
                $query->where('intent_score', '>=', $request->get('min_score'));
            }

            if ($request->has('action')) {
                $query->where('event_name', $request->get('action'));
            }

            if ($request->has('company_id')) {
                $query->where('company_id', $request->get('company_id'));
            }

            if ($request->has('contact_id')) {
                $query->where('contact_id', $request->get('contact_id'));
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->get('date_from'));
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->get('date_to'));
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
            $sortBy = $request->get('sort_by', 'intent_score');
            $sortOrder = $request->get('sort_order', 'desc');

            // Map frontend parameter to database column
            if ($sortBy === 'score') {
                $sortBy = 'intent_score';
            }

            $query->orderBy($sortBy, $sortOrder);

            $intentEvents = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform data for frontend with professional formatting
            $transformedData = collect($intentEvents->items())->map(function ($event) {
                // Calculate intent level from score
                $intentLevel = $this->calculateIntentLevel($event->intent_score);
                
                // Parse event data for page URL
                $eventData = json_decode($event->event_data, true);
                $pageUrl = $eventData['page_url'] ?? 'Unknown Page';
                
                // Format contact name professionally
                $contactName = 'Unknown';
                if ($event->contact) {
                    $contactName = trim($event->contact->first_name . ' ' . $event->contact->last_name);
                    if (empty($contactName)) {
                        $contactName = $event->contact->email;
                    }
                }
                
                // Format company name
                $companyName = $event->company ? $event->company->name : 'Unknown';
                
                // Format action name professionally
                $actionName = $this->formatActionName($event->event_name);
                
                return [
                    'id' => $event->id,
                    'when' => $event->created_at->format('M d, Y, h:i A'),
                    'contact' => [
                        'id' => $event->contact_id,
                        'name' => $contactName,
                        'email' => $event->contact ? $event->contact->email : null,
                        'phone' => $event->contact ? $event->contact->phone : null,
                        'status' => 'Unknown', // Default status since column doesn't exist
                    ],
                    'company' => [
                        'id' => $event->company_id,
                        'name' => $companyName,
                        'status' => 'Unknown', // Default status since column doesn't exist
                    ],
                    'campaign' => 'N/A', // Will be implemented later
                    'page' => $this->formatPageUrl($pageUrl),
                    'action' => $actionName,
                    'score' => $event->intent_score,
                    'intent_level' => $intentLevel,
                    'intent_level_label' => ucfirst(str_replace('_', ' ', $intentLevel)),
                    'source' => $event->source,
                    'session_id' => $event->session_id,
                    'created_at' => $event->created_at,
                    'updated_at' => $event->updated_at,
                ];
            });

            return response()->json([
                'data' => $transformedData,
                'meta' => [
                    'current_page' => $intentEvents->currentPage(),
                    'per_page' => $intentEvents->perPage(),
                    'total' => $intentEvents->total(),
                    'last_page' => $intentEvents->lastPage(),
                ],
                'message' => 'Intent signals retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve intent signals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate intent level from score
     */
    private function calculateIntentLevel(int $score): string
    {
        if ($score >= 80) {
            return 'very_high';
        } elseif ($score >= 60) {
            return 'high';
        } elseif ($score >= 40) {
            return 'medium';
        } elseif ($score >= 20) {
            return 'low';
        } else {
            return 'very_low';
        }
    }

    /**
     * Format action name professionally
     */
    private function formatActionName(string $eventName): string
    {
        $actionMap = [
            'page_view' => 'Page View',
            'form_submit' => 'Form Submission',
            'form_fill' => 'Form Fill',
            'download' => 'Download',
            'pricing_view' => 'Pricing View',
            'contact_view' => 'Contact View',
            'demo_request' => 'Demo Request',
            'trial_signup' => 'Trial Signup',
            'email_open' => 'Email Open',
            'email_click' => 'Email Click',
            'video_watch' => 'Video Watch',
            'whitepaper_download' => 'Whitepaper Download',
            'case_study_view' => 'Case Study View',
            'product_tour' => 'Product Tour',
            'chat_start' => 'Chat Started',
            'webinar_register' => 'Webinar Registration',
            'newsletter_signup' => 'Newsletter Signup',
            'social_share' => 'Social Share',
            'search' => 'Search',
            'cart_add' => 'Add to Cart',
            'checkout_start' => 'Checkout Started',
            'purchase' => 'Purchase',
        ];

        return $actionMap[$eventName] ?? ucfirst(str_replace('_', ' ', $eventName));
    }

    /**
     * Format page URL professionally
     */
    private function formatPageUrl(string $pageUrl): string
    {
        // Extract domain and path for better display
        $parsed = parse_url($pageUrl);
        if (!$parsed) {
            return 'Unknown Page';
        }

        $domain = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';

        // Format common pages
        if (strpos($path, '/pricing') !== false) {
            return 'Pricing Page';
        } elseif (strpos($path, '/contact') !== false) {
            return 'Contact Page';
        } elseif (strpos($path, '/demo') !== false) {
            return 'Demo Page';
        } elseif (strpos($path, '/product') !== false) {
            return 'Product Page';
        } elseif (strpos($path, '/about') !== false) {
            return 'About Page';
        } elseif (strpos($path, '/blog') !== false) {
            return 'Blog Page';
        } elseif ($path === '/' || $path === '') {
            return 'Home Page';
        } else {
            return 'Page: ' . $path;
        }
    }

    /**
     * Get intent analytics and statistics (using rollups with fallback).
     */
    public function getIntentAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Note: Removed development mode restriction to allow real data display

        try {
            // Try to get data from rollups first
            $rollupService = new AnalyticsRollupService();
            $rollupData = $rollupService->getAnalyticsData($tenantId, $request->all());

            // If rollup data is available and complete, use it
            if ($rollupData['data_source'] === 'rollups') {
                return response()->json([
                    'data' => [
                        'overview' => $rollupData['overview'],
                        'action_breakdown' => $rollupData['action_breakdown'],
                        'top_pages' => $rollupData['top_pages'],
                        'intent_distribution' => $rollupData['intent_distribution'],
                        'top_companies' => $rollupData['top_companies'],
                    ],
                    'message' => 'Intent analytics retrieved successfully (from rollups)'
                ]);
            }

            // Fallback to live queries if rollups are unavailable or incomplete
            return $this->getLiveIntentAnalytics($request, $tenantId);

        } catch (\Exception $e) {
            Log::error('Failed to get intent analytics from rollups, falling back to live queries', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            // Fallback to live queries
            return $this->getLiveIntentAnalytics($request, $tenantId);
        }
    }

    /**
     * Get intent analytics using live queries (fallback method).
     */
    private function getLiveIntentAnalytics(Request $request, int $tenantId): JsonResponse
    {
        try {
            // Create base query with filters
            $baseQuery = IntentEvent::where('tenant_id', $tenantId);

            // Apply filters
            if ($request->has('min_score')) {
                $baseQuery->where('intent_score', '>=', $request->get('min_score'));
            }

            if ($request->has('action')) {
                $baseQuery->where('event_name', $request->get('action'));
            }

            if ($request->has('company_id')) {
                $baseQuery->where('company_id', $request->get('company_id'));
            }

            if ($request->has('contact_id')) {
                $baseQuery->where('contact_id', $request->get('contact_id'));
            }

            if ($request->has('date_from')) {
                $baseQuery->where('created_at', '>=', $request->get('date_from'));
            }

            if ($request->has('date_to')) {
                $baseQuery->where('created_at', '<=', $request->get('date_to'));
            }

            // Get basic statistics using fresh query instances
            $totalEvents = $baseQuery->count();
            $avgScore = $baseQuery->avg('intent_score');
            $highIntentCount = (clone $baseQuery)->where('intent_score', '>=', 60)->count();
            $uniqueContacts = (clone $baseQuery)->whereNotNull('contact_id')->distinct('contact_id')->count();
            $uniqueCompanies = (clone $baseQuery)->whereNotNull('company_id')->distinct('company_id')->count();

            // Get action breakdown using fresh query
            $actionBreakdown = (clone $baseQuery)
                ->selectRaw('event_name as action, COUNT(*) as count, AVG(intent_score) as avg_score')
                ->groupBy('event_name')
                ->get()
                ->keyBy('action');

            // Get top pages by intent using fresh query - MySQL 8 strict mode safe with normalized URLs
            $topPages = (clone $baseQuery)
                ->selectRaw('
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(event_data, "$.page_url")),
                        "Unknown Page"
                    ) as page_url_norm,
                    COUNT(*) as visits, 
                    AVG(intent_score) as avg_score, 
                    MAX(intent_score) as max_score
                ')
                ->groupBy('page_url_norm')
                ->orderByRaw('AVG(intent_score) DESC')
                ->limit(10)
                ->get();

            // Get intent level distribution using fresh queries for each level
            $intentDistribution = [
                'very_high' => (clone $baseQuery)->where('intent_score', '>=', 80)->count(),
                'high' => (clone $baseQuery)->where('intent_score', '>=', 60)->where('intent_score', '<', 80)->count(),
                'medium' => (clone $baseQuery)->where('intent_score', '>=', 40)->where('intent_score', '<', 60)->count(),
                'low' => (clone $baseQuery)->where('intent_score', '>=', 20)->where('intent_score', '<', 40)->count(),
                'very_low' => (clone $baseQuery)->where('intent_score', '<', 20)->count(),
            ];

            // Get top companies by intent using fresh query
            $topCompanies = (clone $baseQuery)
                ->whereNotNull('company_id')
                ->with('company:id,name')
                ->selectRaw('company_id, COUNT(*) as events, AVG(intent_score) as avg_score, MAX(intent_score) as max_score')
                ->groupBy('company_id')
                ->orderByRaw('AVG(intent_score) DESC')
                ->limit(10)
                ->get();

            return response()->json([
                'data' => [
                    'overview' => [
                        'total_events' => $totalEvents,
                        'unique_contacts' => $uniqueContacts,
                        'unique_companies' => $uniqueCompanies,
                        'average_score' => round($avgScore, 2),
                        'high_intent_events' => $highIntentCount,
                        'high_intent_percentage' => $totalEvents > 0 ? round(($highIntentCount / $totalEvents) * 100, 2) : 0,
                        'conversion_rate' => $uniqueContacts > 0 ? round(($highIntentCount / $uniqueContacts) * 100, 2) : 0,
                        'engagement_score' => $totalEvents > 0 ? round(($avgScore * $totalEvents) / 100, 2) : 0,
                    ],
                    'action_breakdown' => $actionBreakdown->map(function ($item) use ($totalEvents) {
                        return [
                            'action' => $this->formatActionName($item->action),
                            'count' => $item->count,
                            'avg_score' => round($item->avg_score, 2),
                            'percentage' => $totalEvents > 0 ? round(($item->count / $totalEvents) * 100, 2) : 0,
                        ];
                    }),
                    'top_pages' => $topPages->map(function ($item) {
                        return [
                            'page' => $this->formatPageUrl($item->page_url_norm),
                            'visits' => $item->visits,
                            'avg_score' => round($item->avg_score, 2),
                            'max_score' => $item->max_score,
                        ];
                    }),
                    'intent_distribution' => [
                        'very_high' => [
                            'count' => $intentDistribution['very_high'],
                            'percentage' => $totalEvents > 0 ? round(($intentDistribution['very_high'] / $totalEvents) * 100, 2) : 0,
                            'label' => 'Very High Intent',
                            'color' => '#dc2626'
                        ],
                        'high' => [
                            'count' => $intentDistribution['high'],
                            'percentage' => $totalEvents > 0 ? round(($intentDistribution['high'] / $totalEvents) * 100, 2) : 0,
                            'label' => 'High Intent',
                            'color' => '#ea580c'
                        ],
                        'medium' => [
                            'count' => $intentDistribution['medium'],
                            'percentage' => $totalEvents > 0 ? round(($intentDistribution['medium'] / $totalEvents) * 100, 2) : 0,
                            'label' => 'Medium Intent',
                            'color' => '#d97706'
                        ],
                        'low' => [
                            'count' => $intentDistribution['low'],
                            'percentage' => $totalEvents > 0 ? round(($intentDistribution['low'] / $totalEvents) * 100, 2) : 0,
                            'label' => 'Low Intent',
                            'color' => '#65a30d'
                        ],
                        'very_low' => [
                            'count' => $intentDistribution['very_low'],
                            'percentage' => $totalEvents > 0 ? round(($intentDistribution['very_low'] / $totalEvents) * 100, 2) : 0,
                            'label' => 'Very Low Intent',
                            'color' => '#16a34a'
                        ],
                    ],
                    'top_companies' => $topCompanies->map(function ($item) {
                        return [
                            'company' => $item->company ? $item->company->name : 'Unknown',
                            'events' => $item->events,
                            'avg_score' => round($item->avg_score, 2),
                            'max_score' => $item->max_score,
                            'intent_level' => $this->calculateIntentLevel($item->avg_score),
                        ];
                    }),
                ],
                'message' => 'Intent analytics retrieved successfully (from live queries)'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve intent analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available tracking actions.
     */
    public function getAvailableActions(): JsonResponse
    {
        return response()->json([
            'data' => VisitorIntent::getAvailableActions(),
            'message' => 'Available tracking actions retrieved successfully'
        ]);
    }

    /**
     * Get intent level definitions.
     */
    public function getIntentLevels(): JsonResponse
    {
        return response()->json([
            'data' => [
                'very_high' => ['label' => 'Very High Intent', 'min_score' => 80, 'max_score' => 100],
                'high' => ['label' => 'High Intent', 'min_score' => 60, 'max_score' => 79],
                'medium' => ['label' => 'Medium Intent', 'min_score' => 40, 'max_score' => 59],
                'low' => ['label' => 'Low Intent', 'min_score' => 20, 'max_score' => 39],
                'very_low' => ['label' => 'Very Low Intent', 'min_score' => 0, 'max_score' => 19],
            ],
            'message' => 'Intent level definitions retrieved successfully'
        ]);
    }

    /**
     * Get visitor intent analytics for dashboard widgets.
     */
    public function getVisitorIntentAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Note: Removed development mode restriction to allow real data display

        try {
            $query = IntentEvent::where('tenant_id', $tenantId);

            // Get comprehensive visitor intent analytics
            $totalVisitors = $query->distinct('contact_id')->count();
            $highIntentVisitors = $query->where('intent_score', '>=', 60)->distinct('contact_id')->count();
            $avgIntentScore = $query->avg('intent_score') ?? 0;
            $conversionRate = $totalVisitors > 0 ? round(($highIntentVisitors / $totalVisitors) * 100, 2) : 0;

            // Get intent distribution
            $intentDistribution = [
                'very_high' => $query->where('intent_score', '>=', 80)->distinct('contact_id')->count(),
                'high' => $query->where('intent_score', '>=', 60)->where('intent_score', '<', 80)->distinct('contact_id')->count(),
                'medium' => $query->where('intent_score', '>=', 40)->where('intent_score', '<', 60)->distinct('contact_id')->count(),
                'low' => $query->where('intent_score', '>=', 20)->where('intent_score', '<', 40)->distinct('contact_id')->count(),
                'very_low' => $query->where('intent_score', '<', 20)->distinct('contact_id')->count(),
            ];

            // Get top intent actions with professional formatting
            $topIntentActions = $query->selectRaw('event_name as action, COUNT(*) as count, AVG(intent_score) as avg_score')
                ->groupBy('event_name')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'action' => $this->formatActionName($item->action),
                        'count' => $item->count,
                        'avg_score' => round($item->avg_score, 2),
                        'intent_level' => $this->calculateIntentLevel($item->avg_score),
                    ];
                });

            $visitorAnalytics = [
                'total_visitors' => $totalVisitors,
                'high_intent_visitors' => $highIntentVisitors,
                'conversion_rate' => $conversionRate,
                'avg_intent_score' => round($avgIntentScore, 2),
                'intent_distribution' => [
                    'very_high' => [
                        'count' => $intentDistribution['very_high'],
                        'percentage' => $totalVisitors > 0 ? round(($intentDistribution['very_high'] / $totalVisitors) * 100, 2) : 0,
                        'label' => 'Very High Intent',
                        'color' => '#dc2626'
                    ],
                    'high' => [
                        'count' => $intentDistribution['high'],
                        'percentage' => $totalVisitors > 0 ? round(($intentDistribution['high'] / $totalVisitors) * 100, 2) : 0,
                        'label' => 'High Intent',
                        'color' => '#ea580c'
                    ],
                    'medium' => [
                        'count' => $intentDistribution['medium'],
                        'percentage' => $totalVisitors > 0 ? round(($intentDistribution['medium'] / $totalVisitors) * 100, 2) : 0,
                        'label' => 'Medium Intent',
                        'color' => '#d97706'
                    ],
                    'low' => [
                        'count' => $intentDistribution['low'],
                        'percentage' => $totalVisitors > 0 ? round(($intentDistribution['low'] / $totalVisitors) * 100, 2) : 0,
                        'label' => 'Low Intent',
                        'color' => '#65a30d'
                    ],
                    'very_low' => [
                        'count' => $intentDistribution['very_low'],
                        'percentage' => $totalVisitors > 0 ? round(($intentDistribution['very_low'] / $totalVisitors) * 100, 2) : 0,
                        'label' => 'Very Low Intent',
                        'color' => '#16a34a'
                    ],
                ],
                'top_intent_actions' => $topIntentActions,
            ];

            return response()->json([
                'data' => $visitorAnalytics,
                'message' => 'Visitor intent analytics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve visitor intent analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get visitor identity resolution statistics.
     */
    public function getVisitorStats(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $identityService = new IdentityResolutionService();
            $visitorStats = $identityService->getVisitorStats($tenantId);

            return response()->json([
                'data' => $visitorStats,
                'message' => 'Visitor statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve visitor statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scoring configuration for the tenant.
     */
    public function getScoringConfig(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $scoringEngine = new ScoringEngineService();
            $config = $scoringEngine->getScoringConfig($tenantId);

            return response()->json([
                'data' => $config,
                'message' => 'Scoring configuration retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve scoring configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update scoring configuration for the tenant.
     */
    public function updateScoringConfig(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $request->validate([
            'config' => 'required|array',
            'config.*.default' => 'required|integer|min:0|max:100',
            'config.*.overrides' => 'nullable|array',
        ]);

        try {
            $scoringEngine = new ScoringEngineService();
            $scoringEngine->updateScoringConfig($tenantId, $request->config);

            return response()->json([
                'message' => 'Scoring configuration updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update scoring configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scoring statistics for the tenant.
     */
    public function getScoringStats(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $scoringEngine = new ScoringEngineService();
            $stats = $scoringEngine->getScoringStats($tenantId);

            return response()->json([
                'data' => $stats,
                'message' => 'Scoring statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve scoring statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test scoring configuration with sample data.
     */
    public function testScoring(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $request->validate([
            'test_data' => 'required|array',
            'test_data.*.action' => 'required|string',
            'test_data.*.page_url' => 'nullable|string',
            'test_data.*.duration_seconds' => 'nullable|integer',
            'test_data.*.metadata' => 'nullable|array',
        ]);

        try {
            $scoringEngine = new ScoringEngineService();
            $results = $scoringEngine->testScoring($tenantId, $request->test_data);

            return response()->json([
                'data' => $results,
                'message' => 'Scoring test completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to test scoring configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get URL statistics and normalization info.
     */
    public function getUrlStats(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $urlNormalizer = new UrlNormalizerService();
            
            // Get recent events with page URLs
            $events = IntentEvent::where('tenant_id', $tenantId)
                ->whereRaw('JSON_EXTRACT(event_data, "$.page_url") IS NOT NULL')
                ->orderBy('created_at', 'desc')
                ->limit(1000)
                ->get();

            $urls = [];
            foreach ($events as $event) {
                $eventData = json_decode($event->event_data, true);
                if (!empty($eventData['page_url'])) {
                    $urls[] = $eventData['page_url'];
                }
            }

            $stats = $urlNormalizer->getUrlStats($urls);

            // Get page categories distribution
            $categories = [];
            foreach ($events as $event) {
                $eventData = json_decode($event->event_data, true);
                $metadata = json_decode($event->metadata, true) ?? [];
                
                $category = $metadata['page_category'] ?? $urlNormalizer->getPageCategory($eventData['page_url'] ?? '');
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }

            return response()->json([
                'data' => [
                    'url_statistics' => $stats,
                    'page_categories' => $categories,
                    'total_events_analyzed' => $events->count(),
                    'normalization_status' => [
                        'events_with_normalized_urls' => $events->whereRaw('JSON_EXTRACT(event_data, "$.page_url_normalized") IS NOT NULL')->count(),
                        'events_without_normalized_urls' => $events->whereRaw('JSON_EXTRACT(event_data, "$.page_url_normalized") IS NULL')->count(),
                    ]
                ],
                'message' => 'URL statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve URL statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get intent events for a specific contact.
     */
    public function getContactIntent(int $contactId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            // Get intent events for contact
            $intentEvents = IntentEvent::where('tenant_id', $tenantId)
                ->where('contact_id', $contactId)
                ->with(['company:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate contact's overall intent score
            $overallScore = $intentEvents->avg('intent_score') ?? 0;
            $intentLevel = $this->getIntentLevel($overallScore);

            return response()->json([
                'data' => [
                    'contact_id' => $contactId,
                    'overall_score' => round($overallScore, 2),
                    'intent_level' => $intentLevel,
                    'intent_level_label' => $this->getIntentLevelLabel($intentLevel),
                    'total_events' => $intentEvents->count(),
                    'events' => $intentEvents->map(function ($event) {
                        return [
                            'id' => $event->id,
                            'action' => $event->event_name,
                            'score' => $event->intent_score,
                            'page_url' => json_decode($event->event_data, true)['page_url'] ?? null,
                            'created_at' => $event->created_at,
                            'company' => $event->company ? [
                                'id' => $event->company->id,
                                'name' => $event->company->name
                            ] : null,
                        ];
                    })
                ],
                'message' => 'Contact intent data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve contact intent data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get intent events for a specific company.
     */
    public function getCompanyIntent(int $companyId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            // Get intent events for company
            $intentEvents = IntentEvent::where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->with(['contact:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate company's overall intent score
            $overallScore = $intentEvents->avg('intent_score') ?? 0;
            $intentLevel = $this->getIntentLevel($overallScore);

            return response()->json([
                'data' => [
                    'company_id' => $companyId,
                    'overall_score' => round($overallScore, 2),
                    'intent_level' => $intentLevel,
                    'intent_level_label' => $this->getIntentLevelLabel($intentLevel),
                    'total_events' => $intentEvents->count(),
                    'unique_contacts' => $intentEvents->pluck('contact_id')->unique()->count(),
                    'events' => $intentEvents->map(function ($event) {
                        return [
                            'id' => $event->id,
                            'action' => $event->event_name,
                            'score' => $event->intent_score,
                            'page_url' => json_decode($event->event_data, true)['page_url'] ?? null,
                            'created_at' => $event->created_at,
                            'contact' => $event->contact ? [
                                'id' => $event->contact->id,
                                'name' => $event->contact->first_name . ' ' . $event->contact->last_name,
                                'email' => $event->contact->email
                            ] : null,
                        ];
                    })
                ],
                'message' => 'Company intent data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve company intent data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get intent events for a specific campaign.
     */
    public function getCampaignIntent(int $campaignId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            // Get intent events related to campaign
            $intentEvents = IntentEvent::where('tenant_id', $tenantId)
                ->whereJsonContains('metadata->campaign_id', $campaignId)
                ->with(['company:id,name', 'contact:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate campaign's overall intent score
            $overallScore = $intentEvents->avg('intent_score') ?? 0;
            $intentLevel = $this->getIntentLevel($overallScore);

            return response()->json([
                'data' => [
                    'campaign_id' => $campaignId,
                    'overall_score' => round($overallScore, 2),
                    'intent_level' => $intentLevel,
                    'intent_level_label' => $this->getIntentLevelLabel($intentLevel),
                    'total_events' => $intentEvents->count(),
                    'unique_contacts' => $intentEvents->pluck('contact_id')->unique()->count(),
                    'unique_companies' => $intentEvents->pluck('company_id')->unique()->count(),
                    'events' => $intentEvents->map(function ($event) {
                        return [
                            'id' => $event->id,
                            'action' => $event->event_name,
                            'score' => $event->intent_score,
                            'page_url' => json_decode($event->event_data, true)['page_url'] ?? null,
                            'created_at' => $event->created_at,
                            'company' => $event->company ? [
                                'id' => $event->company->id,
                                'name' => $event->company->name
                            ] : null,
                            'contact' => $event->contact ? [
                                'id' => $event->contact->id,
                                'name' => $event->contact->first_name . ' ' . $event->contact->last_name,
                                'email' => $event->contact->email
                            ] : null,
                        ];
                    })
                ],
                'message' => 'Campaign intent data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve campaign intent data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get intent events for a specific event.
     */
    public function getEventIntent(int $eventId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            // Get intent events related to event
            $intentEvents = IntentEvent::where('tenant_id', $tenantId)
                ->whereJsonContains('metadata->event_id', $eventId)
                ->with(['company:id,name', 'contact:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate event's overall intent score
            $overallScore = $intentEvents->avg('intent_score') ?? 0;
            $intentLevel = $this->getIntentLevel($overallScore);

            return response()->json([
                'data' => [
                    'event_id' => $eventId,
                    'overall_score' => round($overallScore, 2),
                    'intent_level' => $intentLevel,
                    'intent_level_label' => $this->getIntentLevelLabel($intentLevel),
                    'total_events' => $intentEvents->count(),
                    'unique_contacts' => $intentEvents->pluck('contact_id')->unique()->count(),
                    'unique_companies' => $intentEvents->pluck('company_id')->unique()->count(),
                    'events' => $intentEvents->map(function ($event) {
                        return [
                            'id' => $event->id,
                            'action' => $event->event_name,
                            'score' => $event->intent_score,
                            'page_url' => json_decode($event->event_data, true)['page_url'] ?? null,
                            'created_at' => $event->created_at,
                            'company' => $event->company ? [
                                'id' => $event->company->id,
                                'name' => $event->company->name
                            ] : null,
                            'contact' => $event->contact ? [
                                'id' => $event->contact->id,
                                'name' => $event->contact->first_name . ' ' . $event->contact->last_name,
                                'email' => $event->contact->email
                            ] : null,
                        ];
                    })
                ],
                'message' => 'Event intent data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve event intent data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate intent score based on action and metadata.
     */
    private function calculateIntentScore(string $action, array $metadata = []): int
    {
        $baseScore = 0;

        // Base scores for different actions
        $actionScores = [
            'visit' => 10,
            'page_view' => 15,
            'form_fill' => 30,
            'download' => 25,
            'email_open' => 20,
            'email_click' => 35,
            'video_watch' => 20,
            'demo_request' => 50,
            'pricing_view' => 40,
            'contact_form' => 45,
            'chat_start' => 30,
            'whitepaper_download' => 35,
            'case_study_view' => 25,
            'product_tour' => 40,
            'trial_signup' => 60,
            'purchase_intent' => 70,
        ];

        $baseScore = $actionScores[$action] ?? 10;

        // Bonus points for metadata
        if (isset($metadata['high_value_page']) && $metadata['high_value_page']) {
            $baseScore += 15;
        }

        if (isset($metadata['return_visitor']) && $metadata['return_visitor']) {
            $baseScore += 10;
        }

        if (isset($metadata['page_depth']) && $metadata['page_depth'] > 3) {
            $baseScore += 5;
        }

        if (isset($metadata['duration_seconds']) && $metadata['duration_seconds'] > 120) {
            $baseScore += 10;
        }

        // Cap at 100
        return min($baseScore, 100);
    }

    /**
     * Get intent level based on score (using new scoring engine for consistency).
     */
    private function getIntentLevel(int $score): string
    {
        $scoringEngine = new ScoringEngineService();
        return $scoringEngine->getIntentLevel($score);
    }

    /**
     * Get intent level label (using new scoring engine for consistency).
     */
    private function getIntentLevelLabel(string $level): string
    {
        $scoringEngine = new ScoringEngineService();
        return $scoringEngine->getIntentLevelLabel($level);
    }

    /**
     * Validate tracking key for public tracking.
     */
    private function validateTrackingKey(Request $request): int
    {
        // Accept both header formats for compatibility
        $trackingKey = $request->header('X-Tracking-Key') ?? $request->header('tracking_key');
        
        if (!$trackingKey) {
            throw new \Exception('Missing tracking key header for public tracking');
        }

        // In a real implementation, you'd validate against tenant config
        // For now, we'll use a simple mapping or database lookup
        $tenantId = Cache::get("tracking_key_{$trackingKey}");
        
        if (!$tenantId) {
            // Fallback: extract tenant_id from tracking_key if it contains it
            if (preg_match('/^tk_(\d+)_/', $trackingKey, $matches)) {
                $tenantId = (int) $matches[1];
            } elseif (preg_match('/tenant_(\d+)/', $trackingKey, $matches)) {
                $tenantId = (int) $matches[1];
            } else {
                throw new \Exception('Invalid tracking_key format. Expected: tk_{tenant_id}_{random} or tenant_{tenant_id}');
            }
        }

        return $tenantId;
    }

    /**
     * Validate and sanitize request data.
     */
    private function validateAndSanitizeRequest(Request $request): array
    {
        $request->validate([
            'page_url' => 'required|string|max:500|url',
            'duration_seconds' => 'nullable|integer|min:0|max:86400',
            'action' => 'required|string|max:100|regex:/^[a-zA-Z0-9_-]+$/',
            'company_id' => 'nullable|integer|exists:companies,id',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'metadata' => 'nullable|array',
            'session_id' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9_-]+$/',
            'rc_vid' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9_-]+$/',
            'referrer' => 'nullable|string|max:500|url',
            'data' => 'nullable|array',
        ]);

        // Sanitize page_url
        $pageUrl = filter_var($request->page_url, FILTER_SANITIZE_URL);
        if (!filter_var($pageUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid page_url format');
        }

        // Sanitize referrer if provided
        $referrer = null;
        if ($request->has('referrer')) {
            $referrer = filter_var($request->referrer, FILTER_SANITIZE_URL);
            if (!filter_var($referrer, FILTER_VALIDATE_URL)) {
                $referrer = null; // Invalid referrer, ignore
            }
        }

        // Sanitize metadata
        $metadata = $request->metadata ?? [];
        if (is_array($metadata)) {
            $metadata = array_filter($metadata, function($key) {
                return is_string($key) && preg_match('/^[a-zA-Z0-9_-]+$/', $key);
            }, ARRAY_FILTER_USE_KEY);
        } else {
            $metadata = [];
        }

        return [
            'page_url' => $pageUrl,
            'duration_seconds' => $request->duration_seconds,
            'action' => $request->action,
            'company_id' => $request->company_id,
            'contact_id' => $request->contact_id,
            'metadata' => $metadata,
            'session_id' => $request->session_id,
            'rc_vid' => $request->rc_vid,
            'referrer' => $referrer,
            'data' => $request->data ?? [],
        ];
    }

    /**
     * Generate idempotency key.
     */
    private function generateIdempotencyKey(Request $request, array $validatedData): string
    {
        // Use provided idempotency key or generate one
        $providedKey = $request->header('idempotency_key');
        if ($providedKey) {
            return $providedKey;
        }

        // Generate key from: rc_vid + session_id + action + page_url + second timestamp
        $components = [
            $validatedData['rc_vid'] ?? 'unknown',
            $validatedData['session_id'] ?? 'unknown',
            $validatedData['action'],
            parse_url($validatedData['page_url'], PHP_URL_PATH) ?? $validatedData['page_url'],
            date('Y-m-d-H-i-s'), // Second-level timestamp
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Check for duplicate events using idempotency key (using existing table structure).
     */
    private function checkIdempotency(string $idempotencyKey, int $tenantId): ?IntentEvent
    {
        $cacheKey = "idempotency_{$tenantId}_{$idempotencyKey}";
        
        // Check cache first (24h TTL)
        $cachedEventId = Cache::get($cacheKey);
        if ($cachedEventId) {
            return IntentEvent::where('tenant_id', $tenantId)
                ->where('id', $cachedEventId)
                ->first();
        }

        // Check database for recent duplicate (within 24h) using JSON search
        $existingEvent = IntentEvent::where('tenant_id', $tenantId)
            ->whereJsonContains('event_data->idempotency_key', $idempotencyKey)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($existingEvent) {
            // Cache the result for faster future lookups
            Cache::put($cacheKey, $existingEvent->id, 86400); // 24h
        }

        return $existingEvent;
    }

    /**
     * Create minimal event record (using existing table structure).
     */
    private function createMinimalEvent(array $validatedData, int $tenantId, string $idempotencyKey): IntentEvent
    {
        // Use new scoring engine to calculate score
        $scoringEngine = new ScoringEngineService();
        $provisionalScore = $scoringEngine->scoreFor($validatedData, $tenantId);

        // Normalize page URL for consistent analytics
        $urlNormalizer = new UrlNormalizerService();
        $normalizedPageUrl = $urlNormalizer->normalize($validatedData['page_url']);

        return IntentEvent::create([
            'event_type' => 'visitor_action',
            'event_name' => $validatedData['action'],
            'event_data' => json_encode([
                'page_url' => $validatedData['page_url'], // Original URL
                'page_url_normalized' => $normalizedPageUrl, // Normalized URL
                'duration_seconds' => $validatedData['duration_seconds'],
                'action' => $validatedData['action'],
                'metadata' => $validatedData['metadata'],
                'session_id' => $validatedData['session_id'],
                'rc_vid' => $validatedData['rc_vid'],
                'referrer' => $validatedData['referrer'],
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending_enrichment',
            ]),
            'intent_score' => $provisionalScore,
            'source' => 'web_tracking',
            'session_id' => $validatedData['session_id'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => json_encode(array_merge($validatedData['metadata'], [
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending_enrichment',
                'page_url_normalized' => $normalizedPageUrl,
                'page_category' => $urlNormalizer->getPageCategory($normalizedPageUrl),
                'is_high_value_page' => $urlNormalizer->isHighValuePage($normalizedPageUrl),
            ])),
            'company_id' => $validatedData['company_id'],
            'contact_id' => $validatedData['contact_id'],
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Dispatch enrichment job for async processing.
     */
    private function dispatchEnrichmentJob(IntentEvent $intentEvent, array $validatedData): void
    {
        // In a real implementation, you'd dispatch a job like:
        // ProcessEventEnrichmentJob::dispatch($intentEvent, $validatedData);
        
        // For now, we'll do synchronous enrichment to maintain compatibility
        $this->enrichEvent($intentEvent, $validatedData);
    }

    /**
     * Dispatch rollup update job for async processing.
     */
    private function dispatchRollupUpdateJob(IntentEvent $intentEvent): void
    {
        // In a real implementation, you'd dispatch a job like:
        // UpdateAnalyticsRollups::dispatch($intentEvent->id);
        
        // For now, we'll do synchronous rollup update to maintain compatibility
        $rollupService = new AnalyticsRollupService();
        $rollupService->updateRollupsForEvent($intentEvent);
    }

    /**
     * Enrich event with additional data.
     */
    private function enrichEvent(IntentEvent $intentEvent, array $validatedData): void
    {
        try {
            // Parse user agent
            $userAgent = request()->userAgent();
            $uaData = $this->parseUserAgent($userAgent);

            // Normalize page URL
            $normalizedUrl = $this->normalizePageUrl($validatedData['page_url']);

            // Extract domain
            $domain = parse_url($normalizedUrl, PHP_URL_HOST);

            // Optional: Geo-IP lookup (you'd implement this based on your needs)
            $geoData = $this->getGeoData(request()->ip());

            // Resolve identity (contact/company matching)
            $identityData = $this->resolveIdentity($validatedData, $intentEvent);

            // Compute final score with enriched data
            $finalScore = $this->calculateEnrichedIntentScore($validatedData, $uaData, $geoData, $identityData);

            // Update event with enriched data (using existing table structure)
            $currentEventData = json_decode($intentEvent->event_data, true);
            $currentMetadata = json_decode($intentEvent->metadata, true);
            
            $intentEvent->update([
                'intent_score' => $finalScore,
                'event_data' => json_encode(array_merge($currentEventData, [
                    'normalized_url' => $normalizedUrl,
                    'domain' => $domain,
                    'ua_data' => $uaData,
                    'geo_data' => $geoData,
                    'identity_data' => $identityData,
                    'status' => 'enriched',
                ])),
                'metadata' => json_encode(array_merge($currentMetadata, [
                    'status' => 'enriched',
                    'enriched_at' => now()->toISOString(),
                ])),
            ]);

        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Event enrichment failed', [
                'event_id' => $intentEvent->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Parse user agent string.
     */
    private function parseUserAgent(string $userAgent): array
    {
        // Simple user agent parsing (you might want to use a library like jenssegers/agent)
        return [
            'browser' => $this->extractBrowser($userAgent),
            'os' => $this->extractOS($userAgent),
            'device' => $this->extractDevice($userAgent),
        ];
    }

    /**
     * Normalize page URL.
     */
    private function normalizePageUrl(string $url): string
    {
        $parsed = parse_url($url);
        $normalized = $parsed['scheme'] . '://' . $parsed['host'];
        
        if (isset($parsed['path'])) {
            $normalized .= rtrim($parsed['path'], '/');
        }
        
        if (isset($parsed['query'])) {
            // Only include certain query parameters
            parse_str($parsed['query'], $query);
            $allowedParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
            $filteredQuery = array_intersect_key($query, array_flip($allowedParams));
            if (!empty($filteredQuery)) {
                $normalized .= '?' . http_build_query($filteredQuery);
            }
        }
        
        return $normalized;
    }

    /**
     * Get geo data from IP.
     */
    private function getGeoData(string $ip): array
    {
        // Implement geo-IP lookup if needed
        return [
            'country' => null,
            'region' => null,
            'city' => null,
        ];
    }

    /**
     * Resolve identity (contact/company matching) using IdentityResolutionService.
     */
    private function resolveIdentity(array $validatedData, IntentEvent $intentEvent): array
    {
        // If contact_id and company_id are already provided, use them
        if ($validatedData['contact_id'] && $validatedData['company_id']) {
            return [
                'contact_id' => $validatedData['contact_id'],
                'company_id' => $validatedData['company_id'],
                'resolved' => true,
                'method' => 'provided'
            ];
        }

        // Use IdentityResolutionService to resolve identity
        $identityService = new IdentityResolutionService();
        $resolution = $identityService->resolveContactAndCompany($validatedData, $intentEvent->tenant_id);

        // Update the intent event with resolved contact/company if found
        if ($resolution['resolved'] && ($resolution['contact_id'] || $resolution['company_id'])) {
            $intentEvent->update([
                'contact_id' => $resolution['contact_id'] ?? $validatedData['contact_id'],
                'company_id' => $resolution['company_id'] ?? $validatedData['company_id'],
            ]);
        }

        return $resolution;
    }

    /**
     * Calculate enriched intent score using new scoring engine.
     */
    private function calculateEnrichedIntentScore(array $validatedData, array $uaData, array $geoData, array $identityData): int
    {
        // Use new scoring engine for base score calculation
        $scoringEngine = new ScoringEngineService();
        $baseScore = $scoringEngine->scoreFor($validatedData, $validatedData['tenant_id'] ?? 1);
        
        // Add enrichment bonuses
        if ($identityData['resolved']) {
            $baseScore += 10; // Bonus for resolved identity
        }
        
        // Add user agent data to event data for scoring
        $enrichedEventData = array_merge($validatedData, [
            'user_agent' => request()->userAgent(),
            'ua_data' => $uaData,
            'geo_data' => $geoData,
        ]);
        
        // Recalculate with enriched data
        $enrichedScore = $scoringEngine->scoreFor($enrichedEventData, $validatedData['tenant_id'] ?? 1);
        
        return min($enrichedScore, 100);
    }

    /**
     * Format event response.
     */
    private function formatEventResponse(IntentEvent $intentEvent): array
    {
        $eventData = json_decode($intentEvent->event_data, true);
        
        return [
            'id' => $intentEvent->id,
            'contact_id' => $intentEvent->contact_id,
            'company_id' => $intentEvent->company_id,
            'page_url' => $eventData['page_url'] ?? null,
            'duration_seconds' => $eventData['duration_seconds'] ?? null,
            'action' => $intentEvent->event_name,
            'score' => $intentEvent->intent_score,
            'intent_level' => $this->getIntentLevel($intentEvent->intent_score),
            'intent_level_label' => $this->getIntentLevelLabel($this->getIntentLevel($intentEvent->intent_score)),
            'created_at' => $intentEvent->created_at,
        ];
    }

    /**
     * Log structured event data.
     */
    private function logTrackingEvent(string $status, int $tenantId, string $action, ?int $eventId, float $latency, ?string $error = null): void
    {
        Log::info('Tracking event processed', [
            'status' => $status,
            'tenant_id' => $tenantId,
            'action' => $action,
            'event_id' => $eventId,
            'latency_ms' => round($latency * 1000, 2),
            'error' => $error,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Extract browser from user agent.
     */
    private function extractBrowser(string $userAgent): string
    {
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        return 'Unknown';
    }

    /**
     * Extract OS from user agent.
     */
    private function extractOS(string $userAgent): string
    {
        if (strpos($userAgent, 'Windows') !== false) return 'Windows';
        if (strpos($userAgent, 'Mac') !== false) return 'macOS';
        if (strpos($userAgent, 'Linux') !== false) return 'Linux';
        if (strpos($userAgent, 'Android') !== false) return 'Android';
        if (strpos($userAgent, 'iOS') !== false) return 'iOS';
        return 'Unknown';
    }

    /**
     * Extract device from user agent.
     */
    private function extractDevice(string $userAgent): string
    {
        if (strpos($userAgent, 'Mobile') !== false) return 'Mobile';
        if (strpos($userAgent, 'Tablet') !== false) return 'Tablet';
        return 'Desktop';
    }

    /**
     * Generate tracking script for client's website
     */
    public function getTrackingScript(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        
        // Generate unique tracking key for this tenant
        $trackingKey = $this->generateTrackingKey($tenantId);
        
        // Get the CRM URL (where the script will send data)
        $crmUrl = config('app.url');
        
        // Generate the tracking script
        $script = $this->generateTrackingScript($crmUrl, $trackingKey);
        
        return response()->json([
            'data' => [
                'script' => $script,
                'tracking_key' => $trackingKey,
                'crm_url' => $crmUrl,
                'installation_instructions' => $this->getInstallationInstructions(),
                'test_url' => $this->getTestUrl($crmUrl, $trackingKey)
            ],
            'message' => 'Tracking script generated successfully'
        ]);
    }

    /**
     * Generate unique tracking key for tenant
     */
    private function generateTrackingKey(int $tenantId): string
    {
        return 'tk_' . $tenantId . '_' . Str::random(32);
    }

    /**
     * Generate the actual tracking script
     */
    private function generateTrackingScript(string $crmUrl, string $trackingKey): string
    {
        return "
<!-- RC Convergio CRM Tracking Script -->
<script>
(function() {
    var rc = window.rc = window.rc || {};
    rc.tracking = {
        key: '{$trackingKey}',
        url: '{$crmUrl}/api/tracking/events',
        events: []
    };
    
    // Define the track function FIRST
    rc.tracking.track = function(action, data) {
        var event = {
            action: action,
            data: data,
            timestamp: new Date().toISOString(),
            page_url: window.location.href,
            user_agent: navigator.userAgent,
            ip_address: 'auto-detected'
        };
        
        rc.tracking.events.push(event);
        
        // Send to CRM automatically
        fetch(rc.tracking.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Tracking-Key': rc.tracking.key
            },
            body: JSON.stringify(event)
        }).catch(function(error) {
            console.log('RC Tracking: Failed to send event', error);
        });
    };
    
    // Track page view automatically
    rc.tracking.track('page_view', {
        page_url: window.location.href,
        page_title: document.title,
        referrer: document.referrer,
        timestamp: new Date().toISOString()
    });
    
    // Track form submissions automatically with data extraction
    document.addEventListener('submit', function(e) {
        var formData = {};
        var formElements = e.target.elements;
        
        // Extract form data
        for (var i = 0; i < formElements.length; i++) {
            var element = formElements[i];
            if (element.name && element.value && element.type !== 'submit' && element.type !== 'button') {
                formData[element.name] = element.value;
            }
        }
        
        rc.tracking.track('form_submit', {
            form_id: e.target.id || 'unknown',
            form_action: e.target.action || 'unknown',
            page_url: window.location.href,
            form_data: formData,
            form_fields_count: Object.keys(formData).length
        });
    });
    
    // Track downloads automatically
    document.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' && e.target.href) {
            var href = e.target.href;
            if (href.match(/\\.(pdf|doc|docx|xls|xlsx|zip|rar)$/i)) {
                rc.tracking.track('download', {
                    file_url: href,
                    file_name: e.target.textContent || 'unknown',
                    page_url: window.location.href
                });
            }
        }
    });
    
    // Track pricing page visits
    if (window.location.pathname.includes('pricing')) {
        rc.tracking.track('pricing_view', {
            page_url: window.location.href,
            timestamp: new Date().toISOString()
        });
    }
    
    // Track contact page visits
    if (window.location.pathname.includes('contact')) {
        rc.tracking.track('contact_view', {
            page_url: window.location.href,
            timestamp: new Date().toISOString()
        });
    }
})();
</script>
<!-- End RC Convergio CRM Tracking Script -->";
    }

    /**
     * Get installation instructions
     */
    private function getInstallationInstructions(): array
    {
        return [
            'step_1' => 'Copy the script above',
            'step_2' => 'Paste it before the closing </body> tag on every page of your website',
            'step_3' => 'Save and publish your website',
            'step_4' => 'Data will start appearing in your CRM dashboard within 4 hours',
            'note' => 'The script automatically tracks page views, form submissions, downloads, and pricing page visits'
        ];
    }

    /**
     * Enrich intent event with contact data from form submissions.
     */
    private function enrichWithContactData(IntentEvent $intentEvent, array $validatedData, int $tenantId): void
    {
        try {
            // Check if this is a form submission with contact data
            if ($validatedData['action'] === 'form_submit' && isset($validatedData['data']['form_data'])) {
                $contactEnrichmentService = new \App\Services\ContactEnrichmentService();
                
                if ($contactEnrichmentService->hasContactInfo($validatedData['data']['form_data'])) {
                    $enrichmentResult = $contactEnrichmentService->enrichFromFormData(
                        $validatedData['data']['form_data'],
                        $intentEvent,
                        $tenantId
                    );
                    
                    if ($enrichmentResult['enriched']) {
                        Log::info('Intent event enriched with contact data', [
                            'intent_event_id' => $intentEvent->id,
                            'contact_id' => $enrichmentResult['contact']->id,
                            'company_id' => $enrichmentResult['company']->id ?? null
                        ]);
                    }
                }
            }
            // Also check for direct form data in the data array
            elseif ($validatedData['action'] === 'form_submit' && !empty($validatedData['data'])) {
                $contactEnrichmentService = new \App\Services\ContactEnrichmentService();
                
                if ($contactEnrichmentService->hasContactInfo($validatedData['data'])) {
                    $enrichmentResult = $contactEnrichmentService->enrichFromFormData(
                        $validatedData['data'],
                        $intentEvent,
                        $tenantId
                    );
                    
                    if ($enrichmentResult['enriched']) {
                        Log::info('Intent event enriched with contact data (direct)', [
                            'intent_event_id' => $intentEvent->id,
                            'contact_id' => $enrichmentResult['contact']->id,
                            'company_id' => $enrichmentResult['company']->id ?? null
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Contact enrichment failed', [
                'intent_event_id' => $intentEvent->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get test URL for verification
     */
    private function getTestUrl(string $crmUrl, string $trackingKey): string
    {
        return $crmUrl . '/api/tracking/test?key=' . $trackingKey;
    }

    /**
     * Create enhanced event with visitor tracking data.
     */
    private function createEnhancedEvent(array $validatedData, int $tenantId, string $idempotencyKey, 
        \App\Models\Visitor $visitor, ?\App\Models\VisitorSession $session, int $finalScore, bool $isNewVisitor, string $trackingMethod): IntentEvent
    {
        // Use new scoring engine to calculate score
        $scoringEngine = new ScoringEngineService();
        $provisionalScore = $scoringEngine->scoreFor($validatedData, $tenantId);

        // Normalize page URL for consistent analytics
        $urlNormalizer = new UrlNormalizerService();
        $normalizedPageUrl = $urlNormalizer->normalize($validatedData['page_url']);

        return IntentEvent::create([
            'event_type' => 'visitor_action',
            'event_name' => $validatedData['action'],
            'event_data' => json_encode([
                'page_url' => $validatedData['page_url'],
                'page_url_normalized' => $normalizedPageUrl,
                'duration_seconds' => $validatedData['duration_seconds'],
                'action' => $validatedData['action'],
                'metadata' => $validatedData['metadata'],
                'session_id' => $validatedData['session_id'],
                'rc_vid' => $validatedData['rc_vid'],
                'referrer' => $validatedData['referrer'],
                'idempotency_key' => $idempotencyKey,
                'visitor_id' => $visitor->visitor_id,
                'is_new_visitor' => $isNewVisitor,
                'tracking_method' => $trackingMethod,
                'status' => 'pending_enrichment',
            ]),
            'intent_score' => $finalScore,
            'source' => 'web_tracking',
            'session_id' => $validatedData['session_id'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => json_encode(array_merge($validatedData['metadata'], [
                'idempotency_key' => $idempotencyKey,
                'visitor_id' => $visitor->visitor_id,
                'is_new_visitor' => $isNewVisitor,
                'tracking_method' => $trackingMethod,
                'status' => 'pending_enrichment',
                'page_url_normalized' => $normalizedPageUrl,
                'page_category' => $urlNormalizer->getPageCategory($normalizedPageUrl),
                'enriched_at' => null,
            ])),
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Format enhanced event response with visitor info.
     */
    private function formatEnhancedEventResponse(IntentEvent $intentEvent, \App\Models\Visitor $visitor, bool $isNewVisitor, string $trackingMethod): array
    {
        return [
            'id' => $intentEvent->id,
            'action' => $intentEvent->event_name,
            'score' => $intentEvent->intent_score,
            'intent_level' => $this->calculateIntentLevel($intentEvent->intent_score),
            'intent_level_label' => ucfirst(str_replace('_', ' ', $this->calculateIntentLevel($intentEvent->intent_score))),
            'page_url' => json_decode($intentEvent->event_data, true)['page_url'] ?? 'Unknown Page',
            'visitor' => [
                'visitor_id' => $visitor->visitor_id,
                'is_new' => $isNewVisitor,
                'tracking_method' => $trackingMethod,
                'first_seen' => $visitor->first_seen_at,
                'last_seen' => $visitor->last_seen_at,
            ],
            'created_at' => $intentEvent->created_at,
        ];
    }
}