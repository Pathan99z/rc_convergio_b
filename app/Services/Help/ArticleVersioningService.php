<?php

namespace App\Services\Help;

use App\Models\Help\Article;
use App\Models\Help\ArticleVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArticleVersioningService
{
    /**
     * Create a new version of an article.
     */
    public function createVersion(Article $article, ?string $changeReason = null): ArticleVersion
    {
        return DB::transaction(function () use ($article, $changeReason) {
            // Get the next version number
            $nextVersion = $this->getNextVersionNumber($article->id);
            
            // Create the version
            $version = ArticleVersion::create([
                'tenant_id' => $article->tenant_id,
                'article_id' => $article->id,
                'version_number' => $nextVersion,
                'title' => $article->title,
                'summary' => $article->summary,
                'content' => $article->content,
                'status' => $article->status,
                'published_at' => $article->published_at,
                'created_by' => auth()->id(),
                'change_reason' => $changeReason,
            ]);

            Log::info('Article version created', [
                'article_id' => $article->id,
                'version_number' => $nextVersion,
                'tenant_id' => $article->tenant_id,
                'change_reason' => $changeReason,
            ]);

            return $version;
        });
    }

    /**
     * Restore an article to a specific version.
     */
    public function restoreToVersion(Article $article, int $versionNumber): bool
    {
        return DB::transaction(function () use ($article, $versionNumber) {
            $version = ArticleVersion::where('article_id', $article->id)
                ->where('version_number', $versionNumber)
                ->where('tenant_id', $article->tenant_id)
                ->firstOrFail();

            // Create a new version before restoring (for audit trail)
            $this->createVersion($article, "Restored to version {$versionNumber}");

            // Restore the article to the version's state
            $article->update([
                'title' => $version->title,
                'summary' => $version->summary,
                'content' => $version->content,
                'status' => $version->status,
                'published_at' => $version->published_at,
                'updated_by' => auth()->id(),
            ]);

            Log::info('Article restored to version', [
                'article_id' => $article->id,
                'version_number' => $versionNumber,
                'tenant_id' => $article->tenant_id,
            ]);

            return true;
        });
    }

    /**
     * Get the next version number for an article.
     */
    private function getNextVersionNumber(int $articleId): int
    {
        $latestVersion = ArticleVersion::where('article_id', $articleId)
            ->orderBy('version_number', 'desc')
            ->first();

        return $latestVersion ? $latestVersion->version_number + 1 : 1;
    }

    /**
     * Get version history for an article.
     */
    public function getVersionHistory(int $articleId, int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return ArticleVersion::where('article_id', $articleId)
            ->where('tenant_id', $tenantId)
            ->with('creator')
            ->orderBy('version_number', 'desc')
            ->get();
    }

    /**
     * Compare two versions of an article.
     */
    public function compareVersions(int $articleId, int $version1, int $version2, int $tenantId): array
    {
        $version1Data = ArticleVersion::where('article_id', $articleId)
            ->where('version_number', $version1)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $version2Data = ArticleVersion::where('article_id', $articleId)
            ->where('version_number', $version2)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return [
            'version1' => [
                'version_number' => $version1Data->version_number,
                'title' => $version1Data->title,
                'summary' => $version1Data->summary,
                'content' => $version1Data->content,
                'status' => $version1Data->status,
                'created_at' => $version1Data->created_at,
                'created_by' => $version1Data->creator?->name,
            ],
            'version2' => [
                'version_number' => $version2Data->version_number,
                'title' => $version2Data->title,
                'summary' => $version2Data->summary,
                'content' => $version2Data->content,
                'status' => $version2Data->status,
                'created_at' => $version2Data->created_at,
                'created_by' => $version2Data->creator?->name,
            ],
            'changes' => $this->calculateChanges($version1Data, $version2Data),
        ];
    }

    /**
     * Calculate changes between two versions.
     */
    private function calculateChanges(ArticleVersion $version1, ArticleVersion $version2): array
    {
        $changes = [];

        if ($version1->title !== $version2->title) {
            $changes['title'] = [
                'from' => $version1->title,
                'to' => $version2->title,
            ];
        }

        if ($version1->summary !== $version2->summary) {
            $changes['summary'] = [
                'from' => $version1->summary,
                'to' => $version2->summary,
            ];
        }

        if ($version1->content !== $version2->content) {
            $changes['content'] = [
                'from' => $version1->content,
                'to' => $version2->content,
            ];
        }

        if ($version1->status !== $version2->status) {
            $changes['status'] = [
                'from' => $version1->status,
                'to' => $version2->status,
            ];
        }

        return $changes;
    }

    /**
     * Delete old versions (keep only the last N versions).
     */
    public function cleanupOldVersions(int $articleId, int $keepVersions = 10): int
    {
        $versionsToDelete = ArticleVersion::where('article_id', $articleId)
            ->orderBy('version_number', 'desc')
            ->skip($keepVersions)
            ->pluck('id');

        $deletedCount = ArticleVersion::whereIn('id', $versionsToDelete)->delete();

        Log::info('Cleaned up old article versions', [
            'article_id' => $articleId,
            'deleted_count' => $deletedCount,
            'kept_versions' => $keepVersions,
        ]);

        return $deletedCount;
    }
}
