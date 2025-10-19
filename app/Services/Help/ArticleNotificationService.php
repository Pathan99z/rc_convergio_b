<?php

namespace App\Services\Help;

use App\Mail\Help\ArticlePublishedNotification;
use App\Models\Help\Article;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ArticleNotificationService
{
    /**
     * Send notifications when an article is published.
     */
    public function notifyArticlePublished(Article $article): void
    {
        try {
            $recipients = $this->getNotificationRecipients($article->tenant_id);
            
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->queue(
                    new ArticlePublishedNotification($article, $recipient, 'published')
                );
            }

            Log::info('Article published notifications sent', [
                'article_id' => $article->id,
                'tenant_id' => $article->tenant_id,
                'recipients_count' => $recipients->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send article published notifications', [
                'article_id' => $article->id,
                'tenant_id' => $article->tenant_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notifications when an article is updated.
     */
    public function notifyArticleUpdated(Article $article): void
    {
        try {
            $recipients = $this->getNotificationRecipients($article->tenant_id);
            
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->queue(
                    new ArticlePublishedNotification($article, $recipient, 'updated')
                );
            }

            Log::info('Article updated notifications sent', [
                'article_id' => $article->id,
                'tenant_id' => $article->tenant_id,
                'recipients_count' => $recipients->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send article updated notifications', [
                'article_id' => $article->id,
                'tenant_id' => $article->tenant_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notifications when an article is archived.
     */
    public function notifyArticleArchived(Article $article): void
    {
        try {
            $recipients = $this->getNotificationRecipients($article->tenant_id);
            
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->queue(
                    new ArticlePublishedNotification($article, $recipient, 'archived')
                );
            }

            Log::info('Article archived notifications sent', [
                'article_id' => $article->id,
                'tenant_id' => $article->tenant_id,
                'recipients_count' => $recipients->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send article archived notifications', [
                'article_id' => $article->id,
                'tenant_id' => $article->tenant_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get users who should receive article notifications.
     */
    private function getNotificationRecipients(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'service_manager', 'service_agent']);
            })
            ->where('email_verified_at', '!=', null)
            ->get();
    }

    /**
     * Send notification to specific users.
     */
    public function notifySpecificUsers(Article $article, array $userIds, string $action = 'published'): void
    {
        try {
            $recipients = User::whereIn('id', $userIds)
                ->where('tenant_id', $article->tenant_id)
                ->where('email_verified_at', '!=', null)
                ->get();
            
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->queue(
                    new ArticlePublishedNotification($article, $recipient, $action)
                );
            }

            Log::info('Article notifications sent to specific users', [
                'article_id' => $article->id,
                'tenant_id' => $article->tenant_id,
                'action' => $action,
                'recipients_count' => $recipients->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send article notifications to specific users', [
                'article_id' => $article->id,
                'tenant_id' => $article->tenant_id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
