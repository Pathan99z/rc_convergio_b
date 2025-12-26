<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Models\Help\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ArticleNotificationController extends Controller
{
    // Define constant for unauthorized message
    private const UNAUTHORIZED_MESSAGE = 'This action is unauthorized.';

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
            abort(403, self::UNAUTHORIZED_MESSAGE);
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
                    default:
                        // This should not happen due to validation, but included for completeness
                        throw new \InvalidArgumentException('Invalid action: ' . $request->get('action'));
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
}

