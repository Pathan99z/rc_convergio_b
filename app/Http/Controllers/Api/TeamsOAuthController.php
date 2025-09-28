<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeamsIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeamsOAuthController extends Controller
{
    protected TeamsIntegrationService $teamsService;

    public function __construct(TeamsIntegrationService $teamsService)
    {
        $this->teamsService = $teamsService;
    }

    /**
     * Redirect to Teams OAuth authorization.
     */
    public function redirect()
    {
        if (!$this->teamsService->isConfigured()) {
            return response()->json([
                'error' => 'Teams integration not configured',
                'message' => 'Please configure Teams integration settings'
            ], 400);
        }

        $authUrl = $this->teamsService->getAuthorizationUrl();
        
        return response()->json([
            'auth_url' => $authUrl,
            'message' => 'Redirect to Teams authorization'
        ]);
    }

    /**
     * Handle Teams OAuth callback.
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');

        if (!$code) {
            return response()->json([
                'error' => 'Authorization code not provided',
                'message' => 'Teams authorization failed'
            ], 400);
        }

        // Verify state parameter
        if ($state !== csrf_token()) {
            return response()->json([
                'error' => 'Invalid state parameter',
                'message' => 'Teams authorization failed'
            ], 400);
        }

        try {
            $result = $this->teamsService->exchangeCodeForToken($code);

            if ($result['success']) {
                Log::info('Teams OAuth token exchange successful', [
                    'user_id' => auth()->id()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Teams authorization successful',
                    'access_token' => $result['access_token'],
                    'expires_in' => $result['expires_in']
                ]);
            } else {
                Log::error('Teams OAuth token exchange failed', [
                    'error' => $result['error'],
                    'message' => $result['message']
                ]);

                return response()->json([
                    'error' => $result['error'],
                    'message' => $result['message']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Exception during Teams OAuth callback', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'OAuth callback failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
