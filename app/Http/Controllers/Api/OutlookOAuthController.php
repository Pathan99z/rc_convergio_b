<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OutlookIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function redirect(): JsonResponse
    {
        try {
            // Get authenticated user to include in state parameter
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$this->outlookService->isConfigured()) {
                return response()->json([
                    'error' => 'Outlook integration not configured',
                    'message' => 'Please configure Outlook integration settings'
                ], 400);
            }

            // Pass user to service to generate state parameter
            $authUrl = $this->outlookService->getAuthorizationUrl($user);
            
            return response()->json([
                'data' => [
                    'auth_url' => $authUrl,
                    'message' => 'Redirect to Outlook for meeting authorization'
                ],
                'message' => 'Outlook OAuth URL generated successfully for meetings'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate Outlook OAuth URL for meetings', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to generate Outlook OAuth URL for meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Outlook OAuth callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $code = $request->get('code');
            $state = $request->get('state');
            $error = $request->get('error');
            
            // Check for OAuth errors
            if ($error) {
                $errorDescription = $request->get('error_description', 'Unknown error');
                Log::warning('Outlook OAuth error for meetings', [
                    'error' => $error,
                    'description' => $errorDescription
                ]);
                
                return redirect('http://localhost:5173/marketing/meetings?outlook_oauth=error&error=' . urlencode($error) . '&error_description=' . urlencode($errorDescription));
            }
            
            if (!$code) {
                Log::warning('Authorization code not provided in Outlook OAuth callback for meetings');
                return redirect('http://localhost:5173/marketing/meetings?outlook_oauth=error&error=missing_code&error_description=' . urlencode('Authorization code not provided'));
            }

            // Extract user information from state parameter
            if (!$state) {
                Log::warning('State parameter missing in Outlook OAuth callback for meetings');
                return redirect('http://localhost:5173/marketing/meetings?outlook_oauth=error&error=missing_state&error_description=' . urlencode('State parameter missing'));
            }
            
            $stateData = json_decode(base64_decode($state), true);
            if (!$stateData || !isset($stateData['user_id']) || !isset($stateData['tenant_id'])) {
                // Fallback: try to use state as csrf_token (backward compatibility)
                if ($state === csrf_token()) {
                    // Old flow - try to get user from session or fail
                    $user = Auth::user();
                    if (!$user) {
                        Log::error('Invalid state parameter in Outlook OAuth callback for meetings', [
                            'state' => $state
                        ]);
                        return redirect('http://localhost:5173/marketing/meetings?outlook_oauth=error&error=invalid_state&error_description=' . urlencode('Invalid state parameter'));
                    }
                    $userId = $user->id;
                    $tenantId = $user->tenant_id ?? $user->id;
                } else {
                    Log::error('Invalid state parameter in Outlook OAuth callback for meetings', [
                        'state' => $state
                    ]);
                    return redirect('http://localhost:5173/marketing/meetings?outlook_oauth=error&error=invalid_state&error_description=' . urlencode('Invalid state parameter'));
                }
            } else {
                $userId = $stateData['user_id'];
                $tenantId = $stateData['tenant_id'];
            }

            // Optional: Verify state against session (for CSRF protection)
            if (session()->has('outlook_oauth_state')) {
                $sessionState = session('outlook_oauth_state');
                if ($sessionState !== $state) {
                    Log::warning('Outlook OAuth state mismatch for meetings', [
                        'session_state' => $sessionState,
                        'received_state' => $state
                    ]);
                }
                // Clear state from session
                session()->forget('outlook_oauth_state');
            }

            // Exchange code for token
            $result = $this->outlookService->exchangeCodeForToken($code, $userId, $tenantId);
            
            if ($result['success']) {
                Log::info('Outlook OAuth token received for meetings', [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId
                ]);

                // Redirect to frontend success page
                return redirect('http://localhost:5173/marketing/meetings?outlook_oauth=success&message=' . urlencode('Outlook account connected successfully'));
            } else {
                Log::error('Outlook OAuth token exchange failed', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'user_id' => $userId
                ]);

                return redirect('http://localhost:5173/marketing/meetings?outlook_oauth=error&error=token_exchange_failed&error_description=' . urlencode($result['message'] ?? 'Failed to exchange token'));
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle Outlook OAuth callback for meetings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'code' => $request->get('code'),
                'state' => $request->get('state')
            ]);

            return redirect('http://localhost:5173/marketing/meetings?outlook_oauth=error&error=connection_failed&error_description=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Check Outlook connection status.
     */
    public function status(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'connected' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $tenantId = $user->tenant_id ?? $user->id;
            $token = \App\Models\OutlookOAuthToken::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$token) {
                return response()->json([
                    'connected' => false,
                    'message' => 'Outlook not connected'
                ]);
            }

            return response()->json([
                'connected' => true,
                'email' => $token->email,
                'expires_at' => $token->expires_at?->toISOString(),
                'is_expired' => $token->isExpired(),
                'expires_in_minutes' => $token->expires_at ? now()->diffInMinutes($token->expires_at, false) : null,
                'has_refresh_token' => !empty($token->refresh_token),
                'auto_refresh_enabled' => !empty($token->refresh_token),
                'message' => 'Outlook connected'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check Outlook connection status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'connected' => false,
                'message' => 'Failed to check connection status'
            ], 500);
        }
    }

    /**
     * Disconnect Outlook account.
     * 
     * Deletes the OAuth token for the authenticated user and tenant.
     * After disconnecting, user can reconnect using the same OAuth flow.
     * 
     * @return JsonResponse
     */
    public function disconnect(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $tenantId = $user->tenant_id ?? $user->id;
            
            // Find and delete Outlook OAuth token
            $token = \App\Models\OutlookOAuthToken::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlook account not connected'
                ], 404);
            }

            $email = $token->email;
            $token->delete();
            
            Log::info('Outlook account disconnected', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'email' => $email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Outlook account disconnected successfully',
                'email' => $email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to disconnect Outlook account', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect Outlook account',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
