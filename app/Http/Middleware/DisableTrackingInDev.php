<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableTrackingInDev
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if we're in development mode and should reduce polling frequency
        if (config('app.env') === 'local') {
            
            // List of tracking endpoints that cause heavy load when polled frequently
            $trackingEndpoints = [
                'tracking/intent',
                'tracking/analytics', 
                'tracking/visitor-intent-analytics',
                'tracking/actions',
                'tracking/intent-levels',
                'tracking/scoring/config',
                'tracking/exports',
                'tracking/reports',
                'campaigns/intent-stats'
            ];
            
            $path = $request->path();
            
            // Check if this is a tracking endpoint
            foreach ($trackingEndpoints as $endpoint) {
                if (str_contains($path, $endpoint)) {
                    // Check if this is an automatic polling request (frequent, no user interaction)
                    $isAutomaticPolling = $this->isAutomaticPolling($request);
                    
                    if ($isAutomaticPolling) {
                        // Return cached/lightweight response for automatic polling
                        return response()->json([
                            'message' => 'Auto-polling disabled in development mode',
                            'data' => $this->getMockData($endpoint),
                            'dev_mode' => true,
                            'note' => 'Automatic polling disabled to reduce load'
                        ], 200);
                    }
                }
            }
        }

        return $next($request);
    }

    /**
     * Check if this is an automatic polling request
     */
    private function isAutomaticPolling(Request $request): bool
    {
        // Check for manual request indicators
        if ($request->has('manual') || 
            $request->header('X-Manual-Request') === 'true' ||
            $request->has('force_refresh') ||
            $request->header('X-User-Interaction') === 'true') {
            return false;
        }
        
        // Check if this is a user-initiated request (has referer from the same domain)
        $referer = $request->header('referer');
        if ($referer && str_contains($referer, 'localhost:5173')) {
            // This is likely a user clicking or navigating, allow it
            return false;
        }
        
        // Check if this is a page load request (has specific headers)
        if ($request->header('sec-fetch-mode') === 'navigate' ||
            $request->header('sec-fetch-dest') === 'document') {
            return false;
        }
        
        // If none of the above, it's likely automatic polling
        return true;
    }

    /**
     * Get mock data for different endpoints
     */
    private function getMockData(string $endpoint): array
    {
        return match($endpoint) {
            'tracking/intent' => [
                'intent_signals' => [],
                'total_signals' => 0,
                'last_updated' => now()->toISOString()
            ],
            'tracking/analytics' => [
                'overview' => [
                    'total_events' => 0,
                    'unique_contacts' => 0,
                    'unique_companies' => 0,
                    'average_score' => 0
                ],
                'action_breakdown' => [],
                'top_pages' => []
            ],
            'tracking/visitor-intent-analytics' => [
                'visitors' => [],
                'intent_levels' => [],
                'trends' => []
            ],
            'tracking/actions' => [
                'actions' => [
                    ['id' => 'page_view', 'name' => 'Page View', 'score' => 5],
                    ['id' => 'email_open', 'name' => 'Email Open', 'score' => 10],
                    ['id' => 'email_click', 'name' => 'Email Click', 'score' => 15]
                ]
            ],
            'tracking/intent-levels' => [
                'levels' => [
                    ['id' => 'low', 'name' => 'Low Intent', 'min_score' => 0, 'max_score' => 30],
                    ['id' => 'medium', 'name' => 'Medium Intent', 'min_score' => 31, 'max_score' => 70],
                    ['id' => 'high', 'name' => 'High Intent', 'min_score' => 71, 'max_score' => 100]
                ]
            ],
            'tracking/scoring/config' => [
                'config' => [
                    'enabled' => true,
                    'default_score' => 5,
                    'max_score' => 100
                ]
            ],
            'tracking/exports' => [
                'exports' => [],
                'total' => 0
            ],
            'tracking/reports' => [
                'reports' => [],
                'total' => 0
            ],
            'campaigns/intent-stats' => [
                'stats' => [
                    'total_campaigns' => 0,
                    'total_recipients' => 0,
                    'intent_events' => 0
                ]
            ],
            default => []
        };
    }
}
