<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleMeetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MeetingOAuthController extends Controller
{
    protected GoogleMeetService $googleMeetService;

    public function __construct(GoogleMeetService $googleMeetService)
    {
        $this->googleMeetService = $googleMeetService;
    }

    /**
     * Redirect to Google OAuth for meeting integration.
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
            
            // Pass user to service to generate state parameter
            $authUrl = $this->googleMeetService->getAuthorizationUrl($user);
            
            return response()->json([
                'data' => [
                    'auth_url' => $authUrl,
                    'message' => 'Redirect to Google for meeting authorization'
                ],
                'message' => 'Google OAuth URL generated successfully for meetings'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate Google OAuth URL for meetings', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to generate Google OAuth URL for meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback for meeting integration.
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
                Log::warning('Google OAuth error for meetings', [
                    'error' => $error,
                    'description' => $errorDescription
                ]);
                
                // Redirect to frontend error page
                return redirect('http://localhost:5173/marketing/meetings?google_oauth=error&error=' . urlencode($error) . '&error_description=' . urlencode($errorDescription));
            }
            
            if (!$code) {
                Log::warning('Authorization code not provided in Google OAuth callback for meetings');
                return redirect('http://localhost:5173/marketing/meetings?google_oauth=error&error=missing_code&error_description=' . urlencode('Authorization code not provided'));
            }

            // Extract user information from state parameter
            if (!$state) {
                Log::warning('State parameter missing in Google OAuth callback for meetings');
                return redirect('http://localhost:5173/marketing/meetings?google_oauth=error&error=missing_state&error_description=' . urlencode('State parameter missing'));
            }
            
            $stateData = json_decode(base64_decode($state), true);
            if (!$stateData || !isset($stateData['user_id']) || !isset($stateData['tenant_id'])) {
                Log::error('Invalid state parameter in Google OAuth callback for meetings', [
                    'state' => $state,
                    'decoded' => $stateData
                ]);
                return redirect('http://localhost:5173/marketing/meetings?google_oauth=error&error=invalid_state&error_description=' . urlencode('Invalid state parameter'));
            }
            
            $userId = $stateData['user_id'];
            $tenantId = $stateData['tenant_id'];
            
            // Optional: Verify state against session (for CSRF protection)
            if (session()->has('google_meet_oauth_state')) {
                $sessionState = session('google_meet_oauth_state');
                if ($sessionState !== $state) {
                    Log::warning('Google OAuth state mismatch for meetings', [
                        'session_state' => $sessionState,
                        'received_state' => $state
                    ]);
                }
                // Clear state from session
                session()->forget('google_meet_oauth_state');
            }

            // Exchange code for token
            $tokenData = $this->googleMeetService->exchangeCodeForToken($code);
            
            // Store token for the user (using user_id and tenant_id from state)
            \App\Models\GoogleOAuthToken::updateOrCreate(
                [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                ],
                [
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'expires_at' => isset($tokenData['expires_in']) 
                        ? now()->addSeconds($tokenData['expires_in']) 
                        : null,
                    'email' => $tokenData['email'] ?? null,
                ]
            );

            Log::info('Google OAuth token received for meetings', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'access_token' => substr($tokenData['access_token'], 0, 20) . '...'
            ]);

            // Redirect to frontend success page
            return redirect('http://localhost:5173/marketing/meetings?google_oauth=success&message=' . urlencode('Google account connected successfully'));

        } catch (\Exception $e) {
            Log::error('Failed to handle Google OAuth callback for meetings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'code' => $request->get('code'),
                'state' => $request->get('state')
            ]);

            // Redirect to frontend error page
            return redirect('http://localhost:5173/marketing/meetings?google_oauth=error&error=connection_failed&error_description=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Test Google Meet integration for meetings.
     */
    public function testMeet(Request $request): JsonResponse
    {
        try {
            $testData = [
                'title' => 'Test Google Meet Meeting',
                'description' => 'This is a test meeting created via API',
                'scheduled_at' => now()->addHour()->toISOString(),
                'duration_minutes' => 30
            ];

            $meetingData = $this->googleMeetService->createMeeting($testData);

            return response()->json([
                'data' => $meetingData,
                'message' => 'Google Meet test completed successfully for meetings'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to test Google Meet integration for meetings', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to test Google Meet integration for meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force create real Google Meet (for testing without OAuth).
     */
    public function forceRealMeet(Request $request): JsonResponse
    {
        try {
            $testData = [
                'title' => 'Force Real Google Meet Test',
                'description' => 'This is a forced real meeting test',
                'scheduled_at' => now()->addHour()->toISOString(),
                'duration_minutes' => 30
            ];

            // Force real Google Meet creation
            $meetingData = $this->googleMeetService->createCalendarEventWithMeet($testData, 'real_token');

            return response()->json([
                'data' => $meetingData,
                'message' => 'Real Google Meet created successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create real Google Meet', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to create real Google Meet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check Google Calendar connection status for the authenticated user.
     * 
     * Returns connection status, email, expiration info, and auto-refresh capability.
     * User is considered "connected" if they have a refresh_token (even if access_token expired),
     * because the system can automatically refresh expired tokens.
     * 
     * @return JsonResponse
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

            $token = \App\Models\GoogleOAuthToken::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$token) {
                return response()->json([
                    'connected' => false,
                    'message' => 'Google Calendar not connected'
                ]);
            }

            $isExpired = $token->isExpired();
            $hasRefreshToken = !empty($token->refresh_token);
            
            // User is "connected" if they have a refresh_token (even if access_token expired)
            // because we can auto-refresh it when needed
            $connected = $hasRefreshToken;
            
            $expiresInMinutes = $token->expires_at 
                ? max(0, now()->diffInMinutes($token->expires_at, false))
                : null;

            return response()->json([
                'connected' => $connected,
                'email' => $token->email,
                'expires_at' => $token->expires_at?->toIso8601String(),
                'is_expired' => $isExpired,
                'expires_in_minutes' => $expiresInMinutes,
                'has_refresh_token' => $hasRefreshToken,
                'auto_refresh_enabled' => $hasRefreshToken, // Can auto-refresh
                'message' => $connected 
                    ? ($isExpired 
                        ? 'Connected - Token will auto-refresh on next use'
                        : 'Google Calendar connected')
                    : 'Token expired - Reconnection required'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check Google Calendar connection status', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'connected' => false,
                'message' => 'Failed to check connection status'
            ], 500);
        }
    }

    /**
     * Disconnect Google Calendar account.
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
            
            // Find and delete Google OAuth token
            $token = \App\Models\GoogleOAuthToken::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Calendar not connected'
                ], 404);
            }

            $email = $token->email;
            $token->delete();
            
            Log::info('Google Calendar account disconnected', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'email' => $email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Google Calendar disconnected successfully',
                'email' => $email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to disconnect Google Calendar account', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect Google Calendar account',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
