<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\SupportAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SupportAgentController extends Controller
{
    public function __construct(
        private SupportAgentService $supportAgentService
    ) {}

    /**
     * Process a user message and return AI suggestions.
     */
    public function message(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|min:1|max:1000',
                'user_email' => 'nullable|email|max:255',
                'tenant_id' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get tenant_id from request or authenticated user
            $tenantId = $this->getTenantId($request);
            $userEmail = $request->input('user_email');
            $message = $request->input('message');

            // Process the message through the AI service
            $response = $this->supportAgentService->processMessage(
                $message,
                $tenantId,
                $userEmail
            );

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('SupportAgentController: Error processing message', [
                'message' => $request->input('message'),
                'user_id' => $request->user()?->id,
                'tenant_id' => $request->input('tenant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred while processing your request. Please try again.',
                'suggestions' => [],
                'confidence' => 0.0
            ], 500);
        }
    }

    /**
     * Process an article request and return AI response based on article content.
     */
    public function processArticleRequest(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'article_slug' => 'required|string|max:255',
                'user_email' => 'nullable|email|max:255',
                'tenant_id' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get tenant_id from request or authenticated user
            $tenantId = $this->getTenantId($request);
            $userEmail = $request->input('user_email');
            $articleSlug = $request->input('article_slug');

            // Fetch the article
            $article = \App\Models\Help\Article::forTenant($tenantId)
                ->published()
                ->where('slug', $articleSlug)
                ->first();

            if (!$article) {
                // Instead of returning 404, provide a professional AI response
                $response = $this->supportAgentService->handleMissingArticle($articleSlug, $tenantId, $userEmail);
                return response()->json($response, 200);
            }

            // Process the article through the AI service
            $response = $this->supportAgentService->processArticleContent(
                $article,
                $tenantId,
                $userEmail
            );

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('SupportAgentController: Error processing article request', [
                'article_slug' => $request->input('article_slug'),
                'tenant_id' => $request->input('tenant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred while processing your request. Please try again.',
                'suggestions' => [],
                'confidence' => 0.0
            ], 500);
        }
    }

    /**
     * Get tenant_id from request parameters or authenticated user.
     */
    private function getTenantId(Request $request): int
    {
        // First, try to get tenant_id from request body (for public access)
        $tenantId = $request->input('tenant_id');

        if ($tenantId && is_numeric($tenantId) && $tenantId > 0) {
            return (int) $tenantId;
        }

        // Try to get tenant_id from query parameters (for widget integration)
        $tenantId = $request->query('tenant');

        if ($tenantId && is_numeric($tenantId) && $tenantId > 0) {
            return (int) $tenantId;
        }

        // Try to extract from referer URL (for widget integration)
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

        // If no tenant_id provided and no authenticated user, use default tenant for public access
        return 1; // Default tenant for public access
    }
}
