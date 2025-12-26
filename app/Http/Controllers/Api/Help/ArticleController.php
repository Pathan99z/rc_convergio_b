<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Http\Requests\Help\StoreArticleRequest;
use App\Http\Requests\Help\UpdateArticleRequest;
use App\Http\Resources\Help\ArticleResource;
use App\Models\Help\Article;
use App\Services\Help\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ArticleController extends Controller
{
    public function __construct(
        private ArticleService $articleService
    ) {}

    /**
     * Display a listing of articles (public).
     */
    public function publicIndex(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->getTenantIdFromRequest($request);
        
        $query = Article::forTenant($tenantId)->published();

        // Apply advanced filters
        $this->applyAdvancedFilters($query, $request);

        // Pagination
        $perPage = min($request->get('per_page', 15), 50);
        
        // Create cache key for public articles (no user ID needed for public)
        $cacheKey = "help_articles_public_{$tenantId}_" . md5(serialize($request->all()));
        
        // Cache public articles for 5 minutes (300 seconds)
        $articles = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->with(['category'])
                ->orderBy('published_at', 'desc')
                ->paginate($perPage);
        });

        return ArticleResource::collection($articles);
    }

    /**
     * Display the specified article (public).
     */
    public function publicShow(Request $request, string $slug): JsonResponse
    {
        $tenantId = $this->getTenantIdFromRequest($request);
        
        $article = Article::forTenant($tenantId)
            ->published()
            ->where('slug', $slug)
            ->with(['category', 'attachments'])
            ->firstOrFail();

        // Increment view count
        $this->articleService->incrementView($article, $request);

        return response()->json([
            'success' => true,
            'data' => new ArticleResource($article)
        ]);
    }

    /**
     * Display a listing of articles (admin).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Article::class);

        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $query = Article::forTenant($tenantId);

        // Apply advanced filters
        $this->applyAdvancedFilters($query, $request);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $userId = $request->user()->id;
        
        // Create cache key with tenant and user isolation
        $cacheKey = "help_articles_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        // Cache articles list for 5 minutes (300 seconds)
        $articles = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->with(['category', 'creator', 'updater'])
                ->orderBy('updated_at', 'desc')
                ->paginate($perPage);
        });

        return ArticleResource::collection($articles);
    }

    /**
     * Store a newly created article.
     */
    public function store(StoreArticleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $createdBy = $request->user()->id;

        $article = $this->articleService->createArticle($data, $tenantId, $createdBy);

        // Clear cache after creating article
        $this->clearHelpArticlesCache($tenantId, $createdBy);

        return response()->json([
            'success' => true,
            'data' => new ArticleResource($article->load(['category', 'creator'])),
            'message' => 'Article created successfully'
        ], 201);
    }

    /**
     * Display the specified article (admin).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;

        // Manually resolve the article with tenant scoping
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Check authorization manually to avoid policy parameter issues
        if (!$request->user()->can('view', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        $userId = $request->user()->id;
        
        // Create cache key with tenant, user, and article ID isolation
        $cacheKey = "help_article_show_{$tenantId}_{$userId}_{$id}";
        
        // Cache article detail for 15 minutes (900 seconds)
        $article = Cache::remember($cacheKey, 900, function () use ($tenantId, $id) {
            return Article::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        });

        // Check authorization manually to avoid policy parameter issues
        if (!$request->user()->can('view', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        return response()->json([
            'success' => true,
            'data' => new ArticleResource($article->load(['category', 'creator', 'updater']))
        ]);
    }

    /**
     * Update the specified article.
     */
    public function update(UpdateArticleRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;

        // Manually resolve the article with tenant scoping
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Authorization is now handled in the UpdateArticleRequest

        $updated = $this->articleService->updateArticle($article, $data, $request->user()->id);

        // Clear cache after updating article
        $this->clearHelpArticlesCache($tenantId, $request->user()->id);
        Cache::forget("help_article_show_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'success' => true,
            'data' => new ArticleResource($article->fresh()->load(['category', 'creator', 'updater'])),
            'message' => 'Article updated successfully'
        ]);
    }

    /**
     * Remove the specified article.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;

        // Manually resolve the article with tenant scoping
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Check authorization manually to avoid policy parameter issues
        if (!$request->user()->can('delete', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        $userId = $request->user()->id;
        $articleId = $article->id;

        $article->delete();

        // Clear cache after deleting article
        $this->clearHelpArticlesCache($tenantId, $userId);
        Cache::forget("help_article_show_{$tenantId}_{$userId}_{$articleId}");

        return response()->json([
            'success' => true,
            'message' => 'Article deleted successfully'
        ]);
    }

    /**
     * Suggest articles for ticket creation.
     */
    public function suggestForTicket(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'tenant_id' => 'required|integer|exists:users,id',
        ]);

        $suggestionService = app(\App\Services\Help\SuggestionService::class);
        
        $suggestions = $suggestionService->suggestForTicket(
            $request->get('subject'),
            $request->get('description'),
            $request->get('tenant_id')
        );

        return response()->json([
            'success' => true,
            'data' => $suggestions
        ]);
    }

    /**
     * Advanced search with comprehensive filters.
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        // Get tenant ID from authenticated user
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $query = Article::forTenant($tenantId)->published();

        // Apply advanced filters
        $this->applyAdvancedFilters($query, $request);

        // Get results
        $perPage = min($request->get('per_page', 20), 100);
        $articles = $query->with(['category'])
            ->paginate($perPage);

        // Get search metadata
        $searchMetadata = [
            'total_results' => $articles->total(),
            'current_page' => $articles->currentPage(),
            'per_page' => $articles->perPage(),
            'last_page' => $articles->lastPage(),
            'has_more' => $articles->hasMorePages(),
            'filters_applied' => $this->getAppliedFilters($request),
        ];

        return response()->json([
            'success' => true,
            'data' => ArticleResource::collection($articles),
            'meta' => $searchMetadata,
            'message' => "Found {$articles->total()} articles matching your criteria.",
        ]);
    }

    /**
     * Get list of applied filters for metadata.
     */
    private function getAppliedFilters(Request $request): array
    {
        $filters = [];
        
        if ($request->has('query')) $filters['search_term'] = $request->get('query');
        if ($request->has('category')) $filters['category'] = $request->get('category');
        if ($request->has('date_from')) $filters['date_from'] = $request->get('date_from');
        if ($request->has('date_to')) $filters['date_to'] = $request->get('date_to');
        if ($request->has('author_id')) $filters['author'] = $request->get('author_id');
        if ($request->has('status')) $filters['status'] = $request->get('status');
        if ($request->has('min_views')) $filters['min_views'] = $request->get('min_views');
        if ($request->has('max_views')) $filters['max_views'] = $request->get('max_views');
        if ($request->has('min_helpful_percentage')) $filters['min_helpful_percentage'] = $request->get('min_helpful_percentage');
        if ($request->has('sort_by')) $filters['sort_by'] = $request->get('sort_by');
        if ($request->has('sort_order')) $filters['sort_order'] = $request->get('sort_order');
        
        return $filters;
    }


    /**
     * Apply advanced filters to article query.
     */
    private function applyAdvancedFilters($query, Request $request): void
    {
        // Text search
        if ($request->has('query') && !empty($request->get('query'))) {
            $query->search($request->get('query'));
        }

        // Category filter
        if ($request->has('category') && !empty($request->get('category'))) {
            if (is_numeric($request->get('category'))) {
                $query->where('category_id', $request->get('category'));
            } else {
                $query->whereHas('category', function ($q) use ($request) {
                    $q->where('slug', $request->get('category'));
                });
            }
        }

        // Date range filters
        if ($request->has('date_from')) {
            $query->whereDate('published_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('published_at', '<=', $request->get('date_to'));
        }

        // Author filter
        if ($request->has('author_id') && !empty($request->get('author_id'))) {
            $query->where('created_by', $request->get('author_id'));
        }

        // Status filter (for admin)
        if ($request->has('status') && !empty($request->get('status'))) {
            $query->where('status', $request->get('status'));
        }

        // Views range filters
        if ($request->has('min_views')) {
            $query->where('views', '>=', $request->get('min_views'));
        }

        if ($request->has('max_views')) {
            $query->where('views', '<=', $request->get('max_views'));
        }

        // Helpfulness filter
        if ($request->has('min_helpful_percentage')) {
            $query->whereRaw('(helpful_count / (helpful_count + not_helpful_count)) * 100 >= ?', [
                $request->get('min_helpful_percentage')
            ]);
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'published_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSortFields = ['published_at', 'created_at', 'updated_at', 'views', 'title', 'helpful_count'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Special sorting for helpfulness
        if ($sortBy === 'helpful_percentage') {
            $query->orderByRaw('(helpful_count / (helpful_count + not_helpful_count)) DESC');
        }
    }

    /**
     * Get tenant ID from request (for public endpoints).
     */
    private function getTenantIdFromRequest(Request $request): int
    {
        // Try to get tenant from authenticated user first
        if ($request->user()) {
            return $request->user()->tenant_id ?? $request->user()->id;
        }

        // Try to get tenant from query parameter
        $tenantId = $request->query('tenant');
        
        if ($tenantId && is_numeric($tenantId) && $tenantId > 0) {
            return (int) $tenantId;
        }

        // Tenant ID is required for public access
        abort(400, 'Tenant ID is required for public access');
    }

    /**
     * Clear help articles cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearHelpArticlesCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for articles list
            $commonParams = [
                '',
                md5(serialize(['per_page' => 15])),
                md5(serialize(['per_page' => 50])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("help_articles_list_{$tenantId}_{$userId}_{$params}");
                Cache::forget("help_articles_public_{$tenantId}_{$params}");
            }

            Log::info('Help articles cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams) * 2
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear help articles cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
