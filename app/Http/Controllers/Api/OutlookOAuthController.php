<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OutlookIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OutlookOAuthController extends Controller
{
    protected OutlookIntegrationService $outlookService;

    public function __construct(OutlookIntegrationService $outlookService)
    {
        $this->outlookService = $outlookService;
    }

    /**
     * Redirect to Outlook OAuth authorization.
     */
    public function redirect()
    {
        if (!$this->outlookService->isConfigured()) {
            return response()->json([
                'error' => 'Outlook integration not configured',
                'message' => 'Please configure Outlook integration settings'
            ], 400);
        }

        $authUrl = $this->outlookService->getAuthorizationUrl();
        
        return response()->json([
            'auth_url' => $authUrl,
            'message' => 'Redirect to Outlook authorization'
        ]);
    }

    /**
     * Handle Outlook OAuth callback.
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');

        if (!$code) {
            return response()->json([
                'error' => 'Authorization code not provided',
                'message' => 'Outlook authorization failed'
            ], 400);
        }

        // Verify state parameter
        if ($state !== csrf_token()) {
            return response()->json([
                'error' => 'Invalid state parameter',
                'message' => 'Outlook authorization failed'
            ], 400);
        }

        try {
            $result = $this->outlookService->exchangeCodeForToken($code);

            if ($result['success']) {
                Log::info('Outlook OAuth token exchange successful', [
                    'user_id' => auth()->id()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Outlook authorization successful',
                    'access_token' => $result['access_token'],
                    'expires_in' => $result['expires_in']
                ]);
            } else {
                Log::error('Outlook OAuth token exchange failed', [
                    'error' => $result['error'],
                    'message' => $result['message']
                ]);

                return response()->json([
                    'error' => $result['error'],
                    'message' => $result['message']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Exception during Outlook OAuth callback', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'OAuth callback failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
