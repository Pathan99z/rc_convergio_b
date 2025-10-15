<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSocialMediaPostRequest;
use App\Http\Requests\UpdateSocialMediaPostRequest;
use App\Models\SocialMediaPost;
use App\Services\SocialMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SocialMediaController extends Controller
{
    protected SocialMediaService $socialMediaService;

    public function __construct(SocialMediaService $socialMediaService)
    {
        $this->socialMediaService = $socialMediaService;
    }

    /**
     * Display a listing of social media posts.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SocialMediaPost::where('user_id', Auth::id())
                                  ->with('user:id,name,email');

            // Apply filters
            if ($request->filled('platform')) {
                $query->where('platform', $request->platform);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
                });
            }

            // Date range filter
            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            // Sort options
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            if (in_array($sortBy, ['created_at', 'updated_at', 'scheduled_at', 'published_at', 'title'])) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $perPage = min($request->get('per_page', 15), 100); // Max 100 per page
            $posts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $posts->items(),
                'meta' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                    'from' => $posts->firstItem(),
                    'to' => $posts->lastItem()
                ],
                'links' => [
                    'first' => $posts->url(1),
                    'last' => $posts->url($posts->lastPage()),
                    'prev' => $posts->previousPageUrl(),
                    'next' => $posts->nextPageUrl()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch social media posts', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch posts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created social media post.
     */
    public function store(StoreSocialMediaPostRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $validatedData['user_id'] = Auth::id();
            
            // Set status based on scheduling and publish_now flag
            if ($request->filled('scheduled_at')) {
                $validatedData['status'] = 'scheduled';
            } elseif ($request->input('publish_now')) {
                // If publish_now flag is set, mark as scheduled for immediate publishing
                $validatedData['status'] = 'scheduled';
                $validatedData['scheduled_at'] = now();
            } else {
                $validatedData['status'] = 'draft';
            }

            $post = SocialMediaPost::create($validatedData);

            // If it's for immediate publishing or within 5 minutes, publish now
            if (($request->input('publish_now') || 
                ($post->scheduled_at && $post->scheduled_at->diffInMinutes(now()) <= 5))) {
                $publishResult = $this->socialMediaService->publishPost($post);
                
                if ($publishResult['success']) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Post published successfully',
                        'data' => $post->fresh(),
                        'platform_url' => $publishResult['platform_url'] ?? null
                    ], 201);
                } else {
                    Log::warning('Immediate publish failed, keeping as scheduled', [
                        'post_id' => $post->id,
                        'error' => $publishResult['message']
                    ]);
                    
                    // Return the publish error to frontend
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to publish: ' . $publishResult['message'],
                        'data' => $post->fresh(),
                        'error' => $publishResult['error'] ?? 'Publishing failed'
                    ], 422);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Social media post created successfully',
                'data' => $post->fresh()
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return validation errors to frontend
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'debug_data' => config('app.debug') ? $request->all() : null
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create social media post', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create post',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'debug_data' => config('app.debug') ? [
                    'request_data' => $request->all(),
                    'user_id' => Auth::id(),
                    'timestamp' => now()->toIso8601String()
                ] : null
            ], 500);
        }
    }

    /**
     * Display the specified social media post.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $post = SocialMediaPost::where('user_id', Auth::id())
                                  ->with('user:id,name,email')
                                  ->findOrFail($id);

            // Fetch latest engagement metrics if post is published
            if ($post->isPublished() && $post->external_post_id) {
                $metrics = $this->socialMediaService->getEngagementMetrics($post);
                if (!isset($metrics['error'])) {
                    $post->refresh(); // Reload to get updated metrics
                }
            }

            return response()->json([
                'success' => true,
                'data' => $post
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch social media post', [
                'post_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Post not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Post not found'
            ], 404);
        }
    }

    /**
     * Update the specified social media post.
     */
    public function update(UpdateSocialMediaPostRequest $request, int $id): JsonResponse
    {
        try {
            $post = SocialMediaPost::where('user_id', Auth::id())->findOrFail($id);

            // Don't allow updating published posts
            if ($post->status === 'published') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update published posts'
                ], 422);
            }

            $validatedData = $request->validated();
            
            // Update status based on scheduled_at if it's being changed
            if ($request->filled('scheduled_at')) {
                $validatedData['status'] = 'scheduled';
            } elseif ($request->has('scheduled_at') && is_null($request->scheduled_at)) {
                $validatedData['status'] = 'draft';
            }

            $post->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Social media post updated successfully',
                'data' => $post->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update social media post', [
                'post_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update post',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified social media post.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $post = SocialMediaPost::where('user_id', Auth::id())->findOrFail($id);

            // Don't allow deleting published posts (optional - you might want to allow this)
            if ($post->status === 'published') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete published posts. Consider archiving instead.'
                ], 422);
            }

            $post->delete();

            return response()->json([
                'success' => true,
                'message' => 'Social media post deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete social media post', [
                'post_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete post',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Publish a social media post immediately.
     */
    public function publish(int $id): JsonResponse
    {
        try {
            $post = SocialMediaPost::where('user_id', Auth::id())->findOrFail($id);

            if ($post->status === 'published') {
                return response()->json([
                    'success' => false,
                    'message' => 'Post is already published'
                ], 422);
            }

            $result = $this->socialMediaService->publishPost($post);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $post->fresh(),
                'platform_url' => $result['platform_url'] ?? null
            ], $result['success'] ? 200 : 422);

        } catch (\Exception $e) {
            Log::error('Failed to publish social media post', [
                'post_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to publish post',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available social media platforms with connection status.
     */
    public function platforms(): JsonResponse
    {
        try {
            $platforms = $this->socialMediaService->getAvailablePlatforms(Auth::id());

            return response()->json([
                'success' => true,
                'data' => $platforms
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch social media platforms', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch platforms',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get engagement metrics for a specific post.
     */
    public function metrics(int $id): JsonResponse
    {
        try {
            $post = SocialMediaPost::where('user_id', Auth::id())->findOrFail($id);

            if (!$post->isPublished() || !$post->external_post_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metrics are only available for published posts'
                ], 422);
            }

            $metrics = $this->socialMediaService->getEngagementMetrics($post);

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $post->id,
                    'platform' => $post->platform,
                    'metrics' => $metrics
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch post metrics', [
                'post_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch metrics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get analytics summary for all posts.
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $query = SocialMediaPost::where('user_id', Auth::id());

            // Apply date filter if provided
            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $posts = $query->get();

            $analytics = [
                'total_posts' => $posts->count(),
                'published_posts' => $posts->where('status', 'published')->count(),
                'scheduled_posts' => $posts->where('status', 'scheduled')->count(),
                'draft_posts' => $posts->where('status', 'draft')->count(),
                'failed_posts' => $posts->where('status', 'failed')->count(),
                'platforms' => $posts->groupBy('platform')->map->count(),
                'engagement_summary' => $this->calculateEngagementSummary($posts->where('status', 'published'))
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch analytics', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate engagement summary from published posts.
     */
    protected function calculateEngagementSummary($publishedPosts): array
    {
        $totalLikes = 0;
        $totalComments = 0;
        $totalShares = 0;
        $totalViews = 0;

        foreach ($publishedPosts as $post) {
            $metrics = $post->engagement_metrics ?? [];
            $totalLikes += $metrics['likes'] ?? 0;
            $totalComments += $metrics['comments'] ?? 0;
            $totalShares += ($metrics['shares'] ?? 0) + ($metrics['retweets'] ?? 0);
            $totalViews += ($metrics['views'] ?? 0) + ($metrics['impressions'] ?? 0);
        }

        return [
            'total_likes' => $totalLikes,
            'total_comments' => $totalComments,
            'total_shares' => $totalShares,
            'total_views' => $totalViews,
            'average_engagement' => $publishedPosts->count() > 0 ? 
                round(($totalLikes + $totalComments + $totalShares) / $publishedPosts->count(), 2) : 0
        ];
    }

    /**
     * Get social media dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Get recent posts
            $recentPosts = SocialMediaPost::where('user_id', $userId)
                                        ->orderBy('created_at', 'desc')
                                        ->limit(5)
                                        ->get();

            // Get platform distribution
            $platformStats = SocialMediaPost::where('user_id', $userId)
                                          ->selectRaw('platform, count(*) as count')
                                          ->groupBy('platform')
                                          ->get()
                                          ->pluck('count', 'platform');

            // Get status distribution
            $statusStats = SocialMediaPost::where('user_id', $userId)
                                        ->selectRaw('status, count(*) as count')
                                        ->groupBy('status')
                                        ->get()
                                        ->pluck('count', 'status');

            // Get engagement summary
            $engagementSummary = $this->calculateEngagementSummary(
                SocialMediaPost::where('user_id', $userId)->where('status', 'published')->get()
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'recent_posts' => $recentPosts,
                    'platform_stats' => $platformStats,
                    'status_stats' => $statusStats,
                    'engagement_summary' => $engagementSummary,
                    'total_posts' => SocialMediaPost::where('user_id', $userId)->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch social media dashboard', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Debug endpoint to see what data frontend is sending
     */
    public function debug(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Debug info',
            'data' => [
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'request_headers' => $request->headers->all(),
                'request_body' => $request->all(),
                'user_id' => Auth::id(),
                'user_email' => Auth::user()->email ?? 'N/A',
                'timestamp' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * Get social media listening data.
     */
    public function listening(Request $request): JsonResponse
    {
        try {
            // For now, return mock data as listening would require external API integrations
            $listeningData = [
                'mentions' => [
                    [
                        'id' => 1,
                        'platform' => 'twitter',
                        'content' => 'Great product! Really loving the new features.',
                        'author' => '@user123',
                        'sentiment' => 'positive',
                        'engagement' => ['likes' => 15, 'retweets' => 3],
                        'created_at' => now()->subHours(2)->toISOString()
                    ],
                    [
                        'id' => 2,
                        'platform' => 'facebook',
                        'content' => 'Had some issues with the service, but support was helpful.',
                        'author' => 'John Doe',
                        'sentiment' => 'neutral',
                        'engagement' => ['likes' => 8, 'comments' => 2],
                        'created_at' => now()->subHours(5)->toISOString()
                    ]
                ],
                'sentiment_analysis' => [
                    'positive' => 65,
                    'neutral' => 25,
                    'negative' => 10
                ],
                'trending_hashtags' => [
                    '#socialmedia',
                    '#marketing',
                    '#engagement',
                    '#brand',
                    '#content'
                ],
                'keywords' => [
                    'product' => 45,
                    'service' => 32,
                    'support' => 28,
                    'features' => 22,
                    'quality' => 18
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $listeningData,
                'meta' => [
                    'demo_mode' => true,
                    'message' => 'Social listening data (demo mode - integrate with real APIs for production)'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch social media listening data', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch listening data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}