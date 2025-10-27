<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Http\Requests\Help\ArticleFeedbackRequest;
use App\Http\Resources\Help\ArticleFeedbackResource;
use App\Models\Help\Article;
use App\Services\Help\ArticleService;
use Illuminate\Http\JsonResponse;

class FeedbackController extends Controller
{
    public function __construct(
        private ArticleService $articleService
    ) {}

    /**
     * Store feedback for an article.
     */
    public function store(ArticleFeedbackRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $this->getTenantIdFromRequest($request);

        // Find the article with tenant scoping
        $article = Article::forTenant($tenantId)->findOrFail($id);

        $feedback = $this->articleService->recordFeedback($article, $data);

        return response()->json([
            'success' => true,
            'data' => new ArticleFeedbackResource($feedback),
            'message' => 'Feedback recorded successfully'
        ], 201);
    }

    /**
     * Get tenant ID from request.
     */
    private function getTenantIdFromRequest($request): int
    {
        // Try to get tenant from authenticated user first
        if ($request->user()) {
            return $request->user()->tenant_id ?? $request->user()->id;
        }

        // For public endpoints, get tenant from query parameter
        $tenantId = $request->query('tenant');
        
        if (!$tenantId || !is_numeric($tenantId)) {
            abort(400, 'Tenant ID is required for public access');
        }

        return (int) $tenantId;
    }
}
