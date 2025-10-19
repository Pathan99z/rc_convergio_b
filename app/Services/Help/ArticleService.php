<?php

namespace App\Services\Help;

use App\Models\Help\Article;
use App\Models\Help\ArticleFeedback;
use App\Models\Help\ArticleView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArticleService
{
    public function __construct(
        private ArticleNotificationService $notificationService,
        private ArticleVersioningService $versioningService
    ) {}
    /**
     * Create a new article.
     */
    public function createArticle(array $data, int $tenantId, int $createdBy): Article
    {
        return DB::transaction(function () use ($data, $tenantId, $createdBy) {
            $article = Article::create([
                'tenant_id' => $tenantId,
                'category_id' => $data['category_id'] ?? null,
                'title' => $data['title'],
                'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['title']),
                'summary' => $data['summary'] ?? null,
                'content' => $data['content'],
                'status' => $data['status'] ?? 'draft',
                'published_at' => $data['published_at'] ?? null,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]);

            Log::info('Help article created', [
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'title' => $article->title,
                'status' => $article->status,
                'created_by' => $createdBy,
            ]);

            // Create initial version
            $this->versioningService->createVersion($article, 'Initial version');

            // Send notification if article is published
            if ($article->status === 'published') {
                $this->notificationService->notifyArticlePublished($article);
            }

            return $article;
        });
    }

    /**
     * Update an existing article.
     */
    public function updateArticle(Article $article, array $data): bool
    {
        return DB::transaction(function () use ($article, $data) {
            $updated = $article->update([
                'category_id' => $data['category_id'] ?? $article->category_id,
                'title' => $data['title'] ?? $article->title,
                'slug' => $data['slug'] ?? $article->slug,
                'summary' => $data['summary'] ?? $article->summary,
                'content' => $data['content'] ?? $article->content,
                'status' => $data['status'] ?? $article->status,
                'published_at' => $data['published_at'] ?? $article->published_at,
                'updated_by' => auth()->id(),
            ]);

            if ($updated) {
                Log::info('Help article updated', [
                    'article_id' => $article->id,
                    'tenant_id' => $article->tenant_id,
                    'title' => $article->title,
                    'status' => $article->status,
                    'updated_by' => auth()->id(),
                ]);

                // Send notifications based on status change
                $oldStatus = $article->getOriginal('status');
                $newStatus = $article->status;

                if ($oldStatus !== $newStatus) {
                    if ($newStatus === 'published') {
                        $this->notificationService->notifyArticlePublished($article);
                    } elseif ($newStatus === 'archived') {
                        $this->notificationService->notifyArticleArchived($article);
                    } elseif ($newStatus === 'draft' && $oldStatus === 'published') {
                        // Article was unpublished, send update notification
                        $this->notificationService->notifyArticleUpdated($article);
                    }
                } else {
                    // Status didn't change, but content was updated
                    if ($newStatus === 'published') {
                        $this->notificationService->notifyArticleUpdated($article);
                    }
                }

                // Create version for any update
                $this->versioningService->createVersion($article, 'Article updated');
            }

            return $updated;
        });
    }

    /**
     * Increment article view count and log the view.
     */
    public function incrementView(Article $article, Request $request): void
    {
        DB::transaction(function () use ($article, $request) {
            // Log the view
            ArticleView::create([
                'tenant_id' => $article->tenant_id,
                'article_id' => $article->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'viewed_at' => now(),
            ]);

            // Increment the view counter
            $article->incrementViews();
        });
    }

    /**
     * Record feedback for an article.
     */
    public function recordFeedback(Article $article, array $data): ArticleFeedback
    {
        return DB::transaction(function () use ($article, $data) {
            $feedback = ArticleFeedback::create([
                'tenant_id' => $article->tenant_id,
                'article_id' => $article->id,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'feedback' => $data['feedback'],
                'user_id' => auth()->id(),
            ]);

            // Update article feedback counts
            if ($data['feedback'] === 'helpful') {
                $article->increment('helpful_count');
            } else {
                $article->increment('not_helpful_count');
            }

            Log::info('Article feedback recorded', [
                'article_id' => $article->id,
                'feedback' => $data['feedback'],
                'contact_email' => $data['contact_email'] ?? null,
                'user_id' => auth()->id(),
            ]);

            return $feedback;
        });
    }

    /**
     * Search for articles related to the given text.
     */
    public function searchRelatedArticles(string $text, int $limit = 3, int $tenantId): array
    {
        try {
            // Split text into keywords
            $keywords = array_filter(explode(' ', strtolower($text)));
            
            if (empty($keywords)) {
                return [];
            }

            // Build search query
            $query = Article::forTenant($tenantId)
                ->published()
                ->select(['id', 'title', 'slug', 'summary', 'views', 'helpful_count', 'not_helpful_count']);

            // Add search conditions for each keyword
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->orWhere('title', 'like', "%{$keyword}%")
                      ->orWhere('content', 'like', "%{$keyword}%")
                      ->orWhere('summary', 'like', "%{$keyword}%");
                }
            });

            // Order by relevance (title matches first, then content, then views)
            $articles = $query->orderByRaw("
                CASE 
                    WHEN title LIKE ? THEN 1
                    WHEN summary LIKE ? THEN 2
                    WHEN content LIKE ? THEN 3
                    ELSE 4
                END,
                views DESC,
                helpful_count DESC
            ", [
                "%{$keywords[0]}%",
                "%{$keywords[0]}%", 
                "%{$keywords[0]}%"
            ])
            ->limit($limit)
            ->get();

            return $articles->map(function ($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'summary' => $article->summary,
                    'url' => url("/api/help/articles/{$article->slug}?tenant={$tenantId}"),
                    'views' => $article->views,
                    'helpful_percentage' => $article->helpful_percentage,
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Error searching related articles', [
                'text' => $text,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get article statistics for analytics.
     */
    public function getArticleStats(int $tenantId): array
    {
        $articles = Article::forTenant($tenantId)->published();

        return [
            'total_articles' => $articles->count(),
            'total_views' => $articles->sum('views'),
            'total_feedback' => $articles->sum('helpful_count') + $articles->sum('not_helpful_count'),
            'average_helpful_percentage' => $this->calculateAverageHelpfulPercentage($articles),
            'top_viewed' => $articles->orderBy('views', 'desc')->limit(10)->get(['id', 'title', 'views']),
            'most_helpful' => $articles->orderByRaw('(helpful_count / (helpful_count + not_helpful_count)) DESC')
                ->whereRaw('helpful_count + not_helpful_count > 0')
                ->limit(10)
                ->get(['id', 'title', 'helpful_count', 'not_helpful_count']),
        ];
    }

    /**
     * Calculate average helpful percentage.
     */
    private function calculateAverageHelpfulPercentage($articles): float
    {
        $totalHelpful = $articles->sum('helpful_count');
        $totalFeedback = $articles->sum('helpful_count') + $articles->sum('not_helpful_count');

        if ($totalFeedback === 0) {
            return 0.0;
        }

        return round(($totalHelpful / $totalFeedback) * 100, 2);
    }
}
