<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListeningKeyword;
use App\Models\ListeningMention;
use App\Services\SocialListeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SocialListeningController extends Controller
{
    protected SocialListeningService $listeningService;

    public function __construct(SocialListeningService $listeningService)
    {
        $this->listeningService = $listeningService;
    }

    /**
     * Get all listening keywords for user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $keywords = ListeningKeyword::where('user_id', Auth::id())
                                       ->when($request->filled('is_active'), function ($q) use ($request) {
                                           return $q->where('is_active', $request->is_active);
                                       })
                                       ->orderBy('created_at', 'desc')
                                       ->get();

            return response()->json([
                'success' => true,
                'data' => $keywords
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch listening keywords', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch keywords',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a new listening keyword
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => 'required|string|max:255',
            'platforms' => 'nullable|array',
            'platforms.*' => 'in:facebook,twitter,instagram,linkedin',
            'settings' => 'nullable|array'
        ]);

        try {
            $keyword = $this->listeningService->createKeyword(
                Auth::id(),
                $request->keyword,
                $request->platforms ?? ['twitter', 'facebook', 'instagram', 'linkedin'],
                $request->settings ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Listening keyword created successfully',
                'data' => $keyword
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create listening keyword', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create keyword'
            ], 500);
        }
    }

    /**
     * Update a listening keyword
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'keyword' => 'sometimes|string|max:255',
            'platforms' => 'sometimes|array',
            'platforms.*' => 'in:facebook,twitter,instagram,linkedin',
            'is_active' => 'sometimes|boolean',
            'settings' => 'sometimes|array'
        ]);

        try {
            $keyword = ListeningKeyword::where('user_id', Auth::id())->findOrFail($id);
            $keyword->update($request->only(['keyword', 'platforms', 'is_active', 'settings']));

            return response()->json([
                'success' => true,
                'message' => 'Keyword updated successfully',
                'data' => $keyword->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update listening keyword', [
                'keyword_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update keyword'
            ], 500);
        }
    }

    /**
     * Delete a listening keyword
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $keyword = ListeningKeyword::where('user_id', Auth::id())->findOrFail($id);
            $keyword->delete();

            return response()->json([
                'success' => true,
                'message' => 'Keyword deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete listening keyword', [
                'keyword_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete keyword'
            ], 500);
        }
    }

    /**
     * Search for mentions of a keyword
     */
    public function searchMentions(int $id): JsonResponse
    {
        try {
            $keyword = ListeningKeyword::where('user_id', Auth::id())->findOrFail($id);
            
            $mentions = $this->listeningService->searchMentions($keyword);

            return response()->json([
                'success' => true,
                'data' => [
                    'keyword' => $keyword->keyword,
                    'mentions' => $mentions,
                    'total_found' => count($mentions)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to search mentions', [
                'keyword_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search mentions'
            ], 500);
        }
    }

    /**
     * Get mentions for a keyword
     */
    public function getMentions(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'platform' => 'nullable|in:facebook,twitter,instagram,linkedin',
            'sentiment' => 'nullable|in:positive,neutral,negative',
            'is_read' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:200'
        ]);

        try {
            $mentions = $this->listeningService->getMentions(
                Auth::id(),
                $id,
                $request->only(['platform', 'sentiment', 'is_read', 'limit'])
            );

            return response()->json([
                'success' => true,
                'data' => $mentions
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get mentions', [
                'keyword_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get mentions'
            ], 500);
        }
    }

    /**
     * Mark mention as read
     */
    public function markAsRead(int $mentionId): JsonResponse
    {
        try {
            $mention = ListeningMention::where('user_id', Auth::id())->findOrFail($mentionId);
            $mention->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Mention marked as read'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark mention as read', [
                'mention_id' => $mentionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update mention'
            ], 500);
        }
    }

    /**
     * Get sentiment summary
     */
    public function sentimentSummary(Request $request, int $keywordId = null): JsonResponse
    {
        try {
            $summary = $this->listeningService->getSentimentSummary(Auth::id(), $keywordId);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sentiment summary', [
                'keyword_id' => $keywordId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sentiment summary'
            ], 500);
        }
    }
}


