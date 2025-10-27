<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Http\Requests\Help\StoreArticleRequest;
use App\Http\Requests\Help\UpdateArticleRequest;
use App\Http\Resources\Help\ArticleResource;
use App\Models\Help\Article;
use App\Models\Help\ArticleAttachment;
use App\Services\Help\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $articles = $query->with(['category'])
            ->orderBy('published_at', 'desc')
            ->paginate($perPage);

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
        $articles = $query->with(['category', 'creator', 'updater'])
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);

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

        $article->delete();

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
     * Get version history for an article.
     */
    public function getVersionHistory(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('view', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        try {
            $versioningService = app(\App\Services\Help\ArticleVersioningService::class);
            $versions = $versioningService->getVersionHistory($article->id, $tenantId);

            return response()->json([
                'success' => true,
                'article_id' => $article->id,
                'versions' => $versions->map(function ($version) {
                    return [
                        'version_number' => $version->version_number,
                        'title' => $version->title,
                        'status' => $version->status,
                        'change_reason' => $version->change_reason,
                        'created_at' => $version->created_at->toISOString(),
                        'created_by' => $version->creator?->name,
                    ];
                }),
                'total_versions' => $versions->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get article version history', [
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get version history.',
            ], 500);
        }
    }

    /**
     * Restore article to a specific version.
     */
    public function restoreToVersion(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('update', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        $request->validate([
            'version_number' => 'required|integer|min:1',
        ]);

        try {
            $versioningService = app(\App\Services\Help\ArticleVersioningService::class);
            $versioningService->restoreToVersion($article, $request->get('version_number'));

            return response()->json([
                'success' => true,
                'message' => "Article restored to version {$request->get('version_number')}.",
                'article_id' => $article->id,
                'version_number' => $request->get('version_number'),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to restore article to version', [
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'version_number' => $request->get('version_number'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore article to version.',
            ], 500);
        }
    }

    /**
     * Compare two versions of an article.
     */
    public function compareVersions(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('view', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        $request->validate([
            'version1' => 'required|integer|min:1',
            'version2' => 'required|integer|min:1',
        ]);

        try {
            $versioningService = app(\App\Services\Help\ArticleVersioningService::class);
            $comparison = $versioningService->compareVersions(
                $article->id,
                $request->get('version1'),
                $request->get('version2'),
                $tenantId
            );

            return response()->json([
                'success' => true,
                'article_id' => $article->id,
                'comparison' => $comparison,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to compare article versions', [
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'version1' => $request->get('version1'),
                'version2' => $request->get('version2'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to compare versions.',
            ], 500);
        }
    }

    /**
     * Send notification for an article.
     */
    public function sendNotification(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('update', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        $request->validate([
            'action' => 'required|in:published,updated,archived',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        try {
            $notificationService = app(\App\Services\Help\ArticleNotificationService::class);
            
            if ($request->has('user_ids') && !empty($request->get('user_ids'))) {
                // Send to specific users
                $notificationService->notifySpecificUsers(
                    $article, 
                    $request->get('user_ids'), 
                    $request->get('action')
                );
                $message = 'Notification sent to ' . count($request->get('user_ids')) . ' specific users.';
            } else {
                // Send to all eligible users
                switch ($request->get('action')) {
                    case 'published':
                        $notificationService->notifyArticlePublished($article);
                        break;
                    case 'updated':
                        $notificationService->notifyArticleUpdated($article);
                        break;
                    case 'archived':
                        $notificationService->notifyArticleArchived($article);
                        break;
                }
                $message = 'Notification sent to all eligible users.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'action' => $request->get('action'),
                'article_id' => $article->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send article notification', [
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'action' => $request->get('action'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification.',
            ], 500);
        }
    }

    /**
     * Upload attachment for an article.
     */
    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('update', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,txt,jpg,jpeg,png,gif,zip,rar',
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;
            
            // Store file in tenant-specific directory
            $path = $file->storeAs("help_attachments/{$tenantId}", $filename, 'public');
            
            // Create attachment record
            $attachment = ArticleAttachment::create([
                'tenant_id' => $tenantId,
                'article_id' => $article->id,
                'disk' => 'public',
                'path' => $path,
                'filename' => $originalName,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            Log::info('Article attachment uploaded', [
                'attachment_id' => $attachment->id,
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'filename' => $originalName,
                'size' => $file->getSize(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment uploaded successfully.',
                'attachment' => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'size' => $attachment->size,
                    'mime_type' => $attachment->mime_type,
                    'url' => Storage::url($attachment->path),
                    'created_at' => $attachment->created_at->toISOString(),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to upload article attachment', [
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment.',
            ], 500);
        }
    }

    /**
     * Get attachments for an article.
     */
    public function getAttachments(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('view', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        $attachments = ArticleAttachment::where('article_id', $article->id)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'size' => $attachment->size,
                    'mime_type' => $attachment->mime_type,
                    'url' => Storage::url($attachment->path),
                    'created_at' => $attachment->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'attachments' => $attachments,
            'count' => $attachments->count(),
        ]);
    }

    /**
     * Delete an attachment.
     */
    public function deleteAttachment(Request $request, int $id, int $attachmentId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $article = Article::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('update', $article)) {
            abort(403, 'This action is unauthorized.');
        }

        $attachment = ArticleAttachment::where('id', $attachmentId)
            ->where('article_id', $article->id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            // Delete file from storage
            if (Storage::disk($attachment->disk)->exists($attachment->path)) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }

            // Delete attachment record
            $attachment->delete();

            Log::info('Article attachment deleted', [
                'attachment_id' => $attachmentId,
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'filename' => $attachment->filename,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete article attachment', [
                'attachment_id' => $attachmentId,
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment.',
            ], 500);
        }
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

}
