<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleMeetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleOAuthController extends Controller
{
    protected GoogleMeetService $googleMeetService;

    public function __construct(GoogleMeetService $googleMeetService)
    {
        $this->googleMeetService = $googleMeetService;
    }

    /**
     * Redirect to Google OAuth.
     */
    public function redirect(): JsonResponse
    {
        try {
            $authUrl = $this->googleMeetService->getAuthorizationUrl();
            
            return response()->json([
                'data' => [
                    'auth_url' => $authUrl,
                    'message' => 'Redirect to Google for authorization'
                ],
                'message' => 'Google OAuth URL generated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate Google OAuth URL', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to generate Google OAuth URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback.
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            $code = $request->get('code');
            
            if (!$code) {
                return response()->json([
                    'message' => 'Authorization code not provided'
                ], 400);
            }

            // Exchange code for token
            $tokenData = $this->googleMeetService->exchangeCodeForToken($code);
            
            // Store token for the user (in a real app, store this securely)
            $user = Auth::user();
            // You would store this in a user_tokens table or similar
            Log::info('Google OAuth token received', [
                'user_id' => $user->id,
                'access_token' => substr($tokenData['access_token'], 0, 20) . '...'
            ]);

            return response()->json([
                'data' => [
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'expires_in' => $tokenData['expires_in'] ?? null,
                ],
                'message' => 'Google OAuth completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle Google OAuth callback', [
                'error' => $e->getMessage(),
                'code' => $request->get('code')
            ]);

            return response()->json([
                'message' => 'Failed to complete Google OAuth',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Google Meet integration.
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
                'message' => 'Google Meet test completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to test Google Meet integration', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to test Google Meet integration',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}