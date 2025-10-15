<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialMediaOAuthController extends Controller
{
    /**
     * Initiate OAuth connection for a platform
     */
    public function connect(Request $request, string $platform): JsonResponse
    {
        $request->validate([
            'redirect_uri' => 'nullable|url',
        ]);

        try {
            $userId = Auth::id();
            $redirectUri = $request->input('redirect_uri', config('app.frontend_url') . '/social-media/callback');

            // Get OAuth URL based on platform
            $authUrl = $this->getOAuthUrl($platform, $userId, $redirectUri);

            return response()->json([
                'success' => true,
                'data' => [
                    'auth_url' => $authUrl,
                    'platform' => $platform
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to initiate OAuth connection', [
                'platform' => $platform,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate connection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle OAuth callback and store tokens
     */
    public function callback(Request $request, string $platform): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'nullable|string',
        ]);

        try {
            $userId = Auth::id();
            $code = $request->input('code');

            // Exchange code for access token
            $tokenData = $this->exchangeCodeForToken($platform, $code);

            // Store or update social account
            $account = SocialAccount::updateOrCreate(
                [
                    'user_id' => $userId,
                    'platform' => $platform,
                ],
                [
                    'access_token' => encrypt($tokenData['access_token']),
                    'refresh_token' => isset($tokenData['refresh_token']) ? encrypt($tokenData['refresh_token']) : null,
                    'expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
                    'platform_user_id' => $tokenData['user_id'] ?? null,
                    'platform_username' => $tokenData['username'] ?? null,
                    'metadata' => $tokenData['metadata'] ?? null,
                    'is_active' => true,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => ucfirst($platform) . ' account connected successfully',
                'data' => [
                    'platform' => $platform,
                    'username' => $account->platform_username,
                    'connected_at' => $account->updated_at->toIso8601String()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'platform' => $platform,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect a social media account
     */
    public function disconnect(string $platform): JsonResponse
    {
        try {
            $userId = Auth::id();

            $account = SocialAccount::where('user_id', $userId)
                                   ->where('platform', $platform)
                                   ->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found'
                ], 404);
            }

            $account->delete();

            return response()->json([
                'success' => true,
                'message' => ucfirst($platform) . ' account disconnected successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to disconnect account', [
                'platform' => $platform,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect account'
            ], 500);
        }
    }

    /**
     * Get all connected accounts for the authenticated user
     */
    public function getConnectedAccounts(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $accounts = SocialAccount::where('user_id', $userId)
                                    ->where('is_active', true)
                                    ->get()
                                    ->map(function ($account) {
                                        return [
                                            'id' => $account->id,
                                            'platform' => $account->platform,
                                            'username' => $account->platform_username,
                                            'platform_user_id' => $account->platform_user_id,
                                            'connected_at' => $account->created_at->toIso8601String(),
                                            'expires_at' => $account->expires_at?->toIso8601String(),
                                            'is_expired' => $account->isTokenExpired(),
                                        ];
                                    });

            return response()->json([
                'success' => true,
                'data' => $accounts
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch connected accounts', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch connected accounts'
            ], 500);
        }
    }

    /**
     * Refresh access token for a platform
     */
    public function refreshToken(string $platform): JsonResponse
    {
        try {
            $userId = Auth::id();

            $account = SocialAccount::where('user_id', $userId)
                                   ->where('platform', $platform)
                                   ->first();

            if (!$account || !$account->refresh_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found or refresh token not available'
                ], 404);
            }

            // Refresh the token
            $tokenData = $this->refreshAccessToken($platform, decrypt($account->refresh_token));

            $account->update([
                'access_token' => encrypt($tokenData['access_token']),
                'expires_at' => now()->addSeconds($tokenData['expires_in']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to refresh token', [
                'platform' => $platform,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token'
            ], 500);
        }
    }

    /**
     * Get OAuth URL for a platform
     */
    protected function getOAuthUrl(string $platform, int $userId, string $redirectUri): string
    {
        $state = base64_encode(json_encode(['user_id' => $userId, 'timestamp' => time()]));

        switch ($platform) {
            case 'facebook':
                return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
                    'client_id' => config('services.facebook.client_id'),
                    'redirect_uri' => $redirectUri,
                    'scope' => 'pages_show_list,pages_read_engagement,pages_manage_posts,pages_read_user_content,publish_to_groups',
                    'state' => $state,
                ]);

            case 'instagram':
                return 'https://api.instagram.com/oauth/authorize?' . http_build_query([
                    'client_id' => config('services.instagram.client_id'),
                    'redirect_uri' => $redirectUri,
                    'scope' => 'user_profile,user_media',
                    'response_type' => 'code',
                    'state' => $state,
                ]);

            case 'twitter':
                // Twitter OAuth 2.0
                return 'https://twitter.com/i/oauth2/authorize?' . http_build_query([
                    'response_type' => 'code',
                    'client_id' => config('services.twitter.client_id'),
                    'redirect_uri' => $redirectUri,
                    'scope' => 'tweet.read tweet.write users.read offline.access',
                    'state' => $state,
                    'code_challenge' => 'challenge',
                    'code_challenge_method' => 'plain',
                ]);

            case 'linkedin':
                return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
                    'response_type' => 'code',
                    'client_id' => config('services.linkedin.client_id'),
                    'redirect_uri' => $redirectUri,
                    'scope' => 'r_liteprofile r_emailaddress w_member_social',
                    'state' => $state,
                ]);

            default:
                throw new \Exception('Unsupported platform: ' . $platform);
        }
    }

    /**
     * Exchange authorization code for access token
     */
    protected function exchangeCodeForToken(string $platform, string $code): array
    {
        $redirectUri = config('app.frontend_url') . '/social-media/callback';

        switch ($platform) {
            case 'facebook':
                $response = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
                    'client_id' => config('services.facebook.client_id'),
                    'client_secret' => config('services.facebook.client_secret'),
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Facebook OAuth failed: ' . $response->body());
                }

                $data = $response->json();
                
                // Get user info
                $userResponse = Http::get('https://graph.facebook.com/me', [
                    'access_token' => $data['access_token'],
                    'fields' => 'id,name',
                ]);

                $userData = $userResponse->json();

                return [
                    'access_token' => $data['access_token'],
                    'expires_in' => $data['expires_in'] ?? 5184000,
                    'user_id' => $userData['id'] ?? null,
                    'username' => $userData['name'] ?? null,
                ];

            case 'instagram':
                $response = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
                    'client_id' => config('services.instagram.client_id'),
                    'client_secret' => config('services.instagram.client_secret'),
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Instagram OAuth failed: ' . $response->body());
                }

                $data = $response->json();

                return [
                    'access_token' => $data['access_token'],
                    'user_id' => $data['user_id'] ?? null,
                    'expires_in' => 3600, // Instagram short-lived tokens
                ];

            case 'twitter':
                $response = Http::asForm()
                    ->withBasicAuth(config('services.twitter.client_id'), config('services.twitter.client_secret'))
                    ->post('https://api.twitter.com/2/oauth2/token', [
                        'code' => $code,
                        'grant_type' => 'authorization_code',
                        'redirect_uri' => $redirectUri,
                        'code_verifier' => 'challenge',
                    ]);

                if (!$response->successful()) {
                    throw new \Exception('Twitter OAuth failed: ' . $response->body());
                }

                $data = $response->json();

                return [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'expires_in' => $data['expires_in'] ?? 7200,
                ];

            case 'linkedin':
                $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => config('services.linkedin.client_id'),
                    'client_secret' => config('services.linkedin.client_secret'),
                ]);

                if (!$response->successful()) {
                    throw new \Exception('LinkedIn OAuth failed: ' . $response->body());
                }

                $data = $response->json();

                return [
                    'access_token' => $data['access_token'],
                    'expires_in' => $data['expires_in'] ?? 5184000,
                ];

            default:
                throw new \Exception('Unsupported platform: ' . $platform);
        }
    }

    /**
     * Refresh access token
     */
    protected function refreshAccessToken(string $platform, string $refreshToken): array
    {
        switch ($platform) {
            case 'twitter':
                $response = Http::asForm()
                    ->withBasicAuth(config('services.twitter.client_id'), config('services.twitter.client_secret'))
                    ->post('https://api.twitter.com/2/oauth2/token', [
                        'refresh_token' => $refreshToken,
                        'grant_type' => 'refresh_token',
                    ]);

                if (!$response->successful()) {
                    throw new \Exception('Failed to refresh Twitter token');
                }

                return $response->json();

            default:
                throw new \Exception('Token refresh not supported for platform: ' . $platform);
        }
    }
}


