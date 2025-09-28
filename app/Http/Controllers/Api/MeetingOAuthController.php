<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleMeetService;
use Illuminate\Http\JsonResponse;
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
            $authUrl = $this->googleMeetService->getAuthorizationUrl();
            
            return response()->json([
                'data' => [
                    'auth_url' => $authUrl,
                    'message' => 'Redirect to Google for meeting authorization'
                ],
                'message' => 'Google OAuth URL generated successfully for meetings'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate Google OAuth URL for meetings', [
                'error' => $e->getMessage()
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
            
            // Store token for the user
            $user = Auth::user();
            if (!$user) {
                // If no authenticated user, try to get user from session or use a default
                // For now, we'll use user ID 5 (Amrutha) as seen in your meeting creation
                $userId = 5; // Default user ID for testing
                Log::warning('No authenticated user during OAuth callback, using default user ID: ' . $userId);
            } else {
                $userId = $user->id;
            }
            
            \App\Models\GoogleOAuthToken::updateOrCreate(
                ['user_id' => $userId],
                [
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'expires_at' => isset($tokenData['expires_in']) 
                        ? now()->addSeconds($tokenData['expires_in']) 
                        : null,
                ]
            );

            Log::info('Google OAuth token received for meetings', [
                'user_id' => $userId,
                'access_token' => substr($tokenData['access_token'], 0, 20) . '...'
            ]);

            return response()->json([
                'data' => [
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'expires_in' => $tokenData['expires_in'] ?? null,
                ],
                'message' => 'Google OAuth completed successfully for meetings'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle Google OAuth callback for meetings', [
                'error' => $e->getMessage(),
                'code' => $request->get('code')
            ]);

            return response()->json([
                'message' => 'Failed to complete Google OAuth for meetings',
                'error' => $e->getMessage()
            ], 500);
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
}
