<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Models\Help\Article;
use App\Models\Help\Category;
use App\Models\Help\ArticleFeedback;
use App\Services\Help\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatsController extends Controller
{
    public function __construct(
        private ArticleService $articleService
    ) {}

    /**
     * Get help center overview statistics.
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewStats', Article::class);

            $tenantId = $request->user()->tenant_id ?? $request->user()->id;
            $stats = $this->articleService->getArticleStats($tenantId);

            // Get category performance data
            $categoryPerformance = $this->getCategoryPerformance($tenantId);

            // Get enhanced article helpfulness data
            $enhancedHelpfulness = $this->getEnhancedArticleHelpfulness($tenantId);

            return response()->json([
                'success' => true,
                'data' => [
                    // Core metrics
                    'total_articles_published' => $stats['total_articles'],
                    'total_views' => $stats['total_views'],
                    'total_feedback' => $stats['total_feedback'],
                    'average_helpful_percentage' => $stats['average_helpful_percentage'],
                    
                    // Top articles
                    'top_10_viewed_articles' => $stats['top_viewed']->map(function ($article) {
                        return [
                            'id' => $article->id,
                            'title' => $article->title,
                            'views' => $article->views,
                        ];
                    }),
                    
                    // Enhanced helpfulness data
                    'top_helpful_articles' => $enhancedHelpfulness['top_helpful'],
                    'helpfulness_summary' => $enhancedHelpfulness['summary'],
                    
                    // Category performance (NEW!)
                    'category_performance' => $categoryPerformance,
                    
                    // Integration metrics
                    'tickets_correlated_count' => $this->getTicketsCorrelatedCount($tenantId),
                ],
                'meta' => [
                    'generated_at' => now()->toISOString(),
                    'tenant_id' => $tenantId,
                    'period' => 'all_time', // Could be made dynamic based on request
                    'data_freshness' => 'real_time',
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch help center analytics', [
                'tenant_id' => $request->user()->tenant_id ?? $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get category performance data for analytics.
     */
    private function getCategoryPerformance(int $tenantId): array
    {
        try {
            $categories = Category::forTenant($tenantId)
                ->withCount(['articles' => function ($query) {
                    $query->published();
                }])
                ->with(['articles' => function ($query) {
                    $query->published()->select('id', 'category_id', 'views', 'helpful_count', 'not_helpful_count');
                }])
                ->get(['id', 'name', 'slug', 'description']);

            return $categories->map(function ($category) {
                $totalViews = $category->articles->sum('views');
                $totalFeedback = $category->articles->sum('helpful_count') + $category->articles->sum('not_helpful_count');
                $totalHelpful = $category->articles->sum('helpful_count');
                $helpfulPercentage = $totalFeedback > 0 ? round(($totalHelpful / $totalFeedback) * 100, 2) : 0;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'articles_count' => $category->articles_count,
                    'total_views' => $totalViews,
                    'total_feedback' => $totalFeedback,
                    'helpful_percentage' => $helpfulPercentage,
                    'performance_score' => $this->calculateCategoryPerformanceScore($category->articles_count, $totalViews, $helpfulPercentage),
                ];
            })->sortByDesc('performance_score')->values()->toArray();
        } catch (\Exception $e) {
            Log::error('Error getting category performance', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Get enhanced article helpfulness data.
     */
    private function getEnhancedArticleHelpfulness(int $tenantId): array
    {
        try {
            $articles = Article::forTenant($tenantId)
                ->published()
                ->where(DB::raw('helpful_count + not_helpful_count'), '>', 0)
                ->select('id', 'title', 'slug', 'helpful_count', 'not_helpful_count', 'views')
                ->get();

            $topHelpful = $articles->map(function ($article) {
                $total = $article->helpful_count + $article->not_helpful_count;
                $percentage = $total > 0 ? round(($article->helpful_count / $total) * 100, 2) : 0;
                
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'helpful_count' => $article->helpful_count,
                    'not_helpful_count' => $article->not_helpful_count,
                    'helpful_percentage' => $percentage,
                    'total_feedback' => $total,
                    'views' => $article->views,
                    'feedback_to_views_ratio' => $article->views > 0 ? round(($total / $article->views) * 100, 2) : 0,
                ];
            })->sortByDesc('helpful_percentage')->take(10)->values();

            // Calculate helpfulness summary
            $totalHelpful = $articles->sum('helpful_count');
            $totalNotHelpful = $articles->sum('not_helpful_count');
            $totalFeedback = $totalHelpful + $totalNotHelpful;
            $overallHelpfulPercentage = $totalFeedback > 0 ? round(($totalHelpful / $totalFeedback) * 100, 2) : 0;

            $summary = [
                'total_articles_with_feedback' => $articles->count(),
                'total_helpful_votes' => $totalHelpful,
                'total_not_helpful_votes' => $totalNotHelpful,
                'overall_helpful_percentage' => $overallHelpfulPercentage,
                'average_feedback_per_article' => $articles->count() > 0 ? round($totalFeedback / $articles->count(), 2) : 0,
                'feedback_distribution' => [
                    'excellent' => $articles->where('helpful_percentage', '>=', 90)->count(),
                    'good' => $articles->whereBetween('helpful_percentage', [70, 89])->count(),
                    'average' => $articles->whereBetween('helpful_percentage', [50, 69])->count(),
                    'poor' => $articles->where('helpful_percentage', '<', 50)->count(),
                ],
            ];

            return [
                'top_helpful' => $topHelpful,
                'summary' => $summary,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting enhanced helpfulness data', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'top_helpful' => [],
                'summary' => [
                    'total_articles_with_feedback' => 0,
                    'total_helpful_votes' => 0,
                    'total_not_helpful_votes' => 0,
                    'overall_helpful_percentage' => 0,
                    'average_feedback_per_article' => 0,
                    'feedback_distribution' => [
                        'excellent' => 0,
                        'good' => 0,
                        'average' => 0,
                        'poor' => 0,
                    ],
                ],
            ];
        }
    }

    /**
     * Calculate category performance score based on articles, views, and helpfulness.
     */
    private function calculateCategoryPerformanceScore(int $articlesCount, int $totalViews, float $helpfulPercentage): float
    {
        // Weighted scoring: 40% articles count, 30% views, 30% helpfulness
        $articlesScore = min($articlesCount * 10, 100); // Max 100 for 10+ articles
        $viewsScore = min($totalViews * 2, 100); // Max 100 for 50+ views
        $helpfulnessScore = $helpfulPercentage; // Already 0-100
        
        return round(($articlesScore * 0.4) + ($viewsScore * 0.3) + ($helpfulnessScore * 0.3), 2);
    }

    /**
     * Get count of tickets that had article suggestions used.
     * This is a placeholder implementation.
     */
    private function getTicketsCorrelatedCount(int $tenantId): int
    {
        // Placeholder implementation
        // In a real implementation, you would track when article suggestions
        // are used during ticket creation and count those instances
        
        try {
            // Example: Count tickets that have been created with article suggestions
            // This would require a ticket_suggestions table or similar tracking mechanism
            return 0; // Placeholder
        } catch (\Exception $e) {
            Log::error('Error getting tickets correlated count', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            
            return 0;
        }
    }
}
