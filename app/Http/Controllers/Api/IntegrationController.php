<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IntegrationController extends Controller
{
    /**
     * Get widget snippet for embedding the Unified Support Widget.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getWidgetSnippet(Request $request): JsonResponse
    {
        try {
            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Detect tenant_id from authenticated user
            $tenantId = $user->tenant_id ?? $user->id;
            
            // Get FRONTEND_URL from config (already configured in config/app.php)
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            
            // Ensure frontend URL doesn't end with slash
            $frontendUrl = rtrim($frontendUrl, '/');
            
            // Build the embed URL
            $embedUrl = $frontendUrl . '/embed/rc-widget.js';
            
            // Generate the HTML snippet
            $snippet = $this->generateWidgetSnippet($tenantId, $embedUrl);
            
            return response()->json([
                'success' => true,
                'snippet' => $snippet,
                'tenant_id' => $tenantId,
                'embed_url' => $embedUrl,
                'env' => config('app.env', 'production')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate widget snippet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live chat widget snippet for embedding the Live Chat Widget.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getLiveChatSnippet(Request $request): JsonResponse
    {
        try {
            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Detect tenant_id from authenticated user
            $tenantId = $user->tenant_id ?? $user->id;
            
            // Get FRONTEND_URL from config (already configured in config/app.php)
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            
            // Ensure frontend URL doesn't end with slash
            $frontendUrl = rtrim($frontendUrl, '/');
            
            // Build the embed URL for live chat widget
            $embedUrl = $frontendUrl . '/embed/rc-livechat.js';
            
            // Generate the HTML snippet for live chat
            $snippet = $this->generateLiveChatSnippet($tenantId, $embedUrl);
            
            return response()->json([
                'success' => true,
                'snippet' => $snippet,
                'tenant_id' => $tenantId,
                'embed_url' => $embedUrl,
                'env' => config('app.env', 'production')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate live chat widget snippet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate the HTML snippet for the widget.
     * 
     * @param int $tenantId
     * @param string $embedUrl
     * @return string
     */
    private function generateWidgetSnippet(int $tenantId, string $embedUrl): string
    {
        $snippet = <<<HTML
<script>
window.RCWidgetConfig = {
    tenant: {$tenantId},
    modules: ['ticket', 'faq', 'ai']
};
</script>
<script src="{$embedUrl}" async></script>
HTML;

        return $snippet;
    }

    /**
     * Generate the HTML snippet for the live chat widget.
     * 
     * @param int $tenantId
     * @param string $embedUrl
     * @return string
     */
    private function generateLiveChatSnippet(int $tenantId, string $embedUrl): string
    {
        $snippet = <<<HTML
<script>
window.RCLiveChatConfig = {
    tenant: {$tenantId},
    modules: ['livechat']
};
</script>
<script src="{$embedUrl}" async></script>
HTML;

        return $snippet;
    }
}
