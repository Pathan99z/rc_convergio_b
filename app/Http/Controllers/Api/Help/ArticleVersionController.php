<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Models\Help\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ArticleVersionController extends Controller
{
    // Define constant for unauthorized message
    private const UNAUTHORIZED_MESSAGE = 'This action is unauthorized.';

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
            abort(403, self::UNAUTHORIZED_MESSAGE);
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
            abort(403, self::UNAUTHORIZED_MESSAGE);
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
            abort(403, self::UNAUTHORIZED_MESSAGE);
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
}

