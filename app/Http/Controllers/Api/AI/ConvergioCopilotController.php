<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\ConvergioCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ConvergioCopilotController extends Controller
{
    public function __construct(
        private ConvergioCopilotService $copilotService
    ) {}

    /**
     * Process a user query and return AI guidance.
     */
    public function ask(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:1|max:1000',
                'current_page' => 'nullable|string|max:255',
                'user_id' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get tenant_id from authenticated user
            $tenantId = $this->getTenantId($request);
            $userId = $request->input('user_id') ?? $request->user()?->id;
            $query = $request->input('query');
            $currentPage = $request->input('current_page');

            // Process the query through the copilot service
            $response = $this->copilotService->processQuery(
                $query,
                $tenantId,
                $currentPage,
                $userId
            );

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('ConvergioCopilotController: Error processing query', [
                'query' => $request->input('query'),
                'user_id' => $request->user()?->id,
                'tenant_id' => $request->input('tenant_id'),
                'current_page' => $request->input('current_page'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'I apologize, but I encountered an error processing your request. Please try again.',
                'steps' => [],
                'navigation' => '',
                'feature_name' => null,
                'description' => null,
                'help_articles' => [],
                'related_features' => [],
                'confidence' => 0.0,
                'action' => 'help',
                'feature' => null
            ], 500);
        }
    }

    /**
     * Get all available features that the copilot can help with.
     */
    public function features(): JsonResponse
    {
        try {
            $features = $this->copilotService->getAvailableFeatures();

            return response()->json([
                'features' => $features,
                'total' => count($features)
            ], 200);

        } catch (\Exception $e) {
            Log::error('ConvergioCopilotController: Error getting features', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Unable to retrieve available features',
                'features' => [],
                'total' => 0
            ], 500);
        }
    }

    /**
     * Get user's conversation history.
     */
    public function history(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'limit' => 'nullable|integer|min:1|max:50',
                'user_id' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tenantId = $this->getTenantId($request);
            $userId = $request->input('user_id') ?? $request->user()?->id;
            $limit = $request->input('limit', 10);

            $history = $this->copilotService->getConversationHistory($tenantId, $userId, $limit);

            return response()->json([
                'conversations' => $history,
                'total' => $history->count()
            ], 200);

        } catch (\Exception $e) {
            Log::error('ConvergioCopilotController: Error getting history', [
                'user_id' => $request->user()?->id,
                'tenant_id' => $request->input('tenant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Unable to retrieve conversation history',
                'conversations' => [],
                'total' => 0
            ], 500);
        }
    }

    /**
     * Get platform statistics and insights.
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId($request);
            $userId = $request->user()?->id;

            // Get conversation statistics
            $totalConversations = \App\Models\AI\CopilotConversation::where('tenant_id', $tenantId)->count();
            $userConversations = \App\Models\AI\CopilotConversation::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->count();

            // Get most used features
            $popularFeatures = \App\Models\AI\CopilotConversation::where('tenant_id', $tenantId)
                ->whereNotNull('feature')
                ->selectRaw('feature, COUNT(*) as count')
                ->groupBy('feature')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get();

            // Get recent activity
            $recentActivity = \App\Models\AI\CopilotConversation::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['feature', 'action', 'confidence', 'created_at']);

            return response()->json([
                'total_conversations' => $totalConversations,
                'user_conversations' => $userConversations,
                'popular_features' => $popularFeatures,
                'recent_activity' => $recentActivity
            ], 200);

        } catch (\Exception $e) {
            Log::error('ConvergioCopilotController: Error getting stats', [
                'user_id' => $request->user()?->id,
                'tenant_id' => $request->input('tenant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Unable to retrieve statistics',
                'total_conversations' => 0,
                'user_conversations' => 0,
                'popular_features' => [],
                'recent_activity' => []
            ], 500);
        }
    }

    /**
     * Get tenant_id from request parameters or authenticated user.
     */
    private function getTenantId(Request $request): int
    {
        // First, try to get tenant_id from request body
        $tenantId = $request->input('tenant_id');

        if ($tenantId && is_numeric($tenantId) && $tenantId > 0) {
            return (int) $tenantId;
        }

        // Try to get tenant_id from query parameters
        $tenantId = $request->query('tenant');

        if ($tenantId && is_numeric($tenantId) && $tenantId > 0) {
            return (int) $tenantId;
        }

        // Try to extract from referer URL
        $referer = $request->header('referer');
        if ($referer && preg_match('/tenant=(\d+)/', $referer, $matches)) {
            $tenantId = (int) $matches[1];
            if ($tenantId > 0) {
                return $tenantId;
            }
        }

        // If no tenant_id in request, try to get from authenticated user
        $user = $request->user();

        if ($user) {
            // Get tenant_id from user, with fallback logic
            $tenantId = $user->tenant_id ?? $user->id;

            if ($tenantId === 0) {
                // Use organization_name to determine tenant_id (legacy logic)
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4; // chitti's organization
                } else {
                    $tenantId = 1; // default tenant
                }
            }

            return $tenantId;
        }

        // If no tenant_id provided and no authenticated user, use default tenant
        return 1; // Default tenant for public access
    }
}

