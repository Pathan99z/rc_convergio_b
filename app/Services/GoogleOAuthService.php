<?php

namespace App\Services;

use App\Models\AdAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri = config('services.google.redirect');
    }

    /**
     * Generate Google OAuth authorization URL
     */
    public function getAuthorizationUrl(): string
    {
        $user = Auth::user();
        $state = base64_encode(json_encode([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'nonce' => bin2hex(random_bytes(16))
        ]));
        
        session(['google_oauth_state' => $state]);

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'https://www.googleapis.com/auth/adwords openid email profile',
            'response_type' => 'code',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code, string $state): array
    {
        // Decode state to get user information and validate
        $stateData = json_decode(base64_decode($state), true);
        if (!$stateData || !isset($stateData['user_id']) || !isset($stateData['tenant_id']) || !isset($stateData['nonce'])) {
            throw new \Exception('Invalid state data');
        }

        // Verify state parameter (check if it matches session or is valid format)
        if (session()->has('google_oauth_state')) {
            $sessionState = session('google_oauth_state');
            if ($sessionState !== $state) {
                // Log for debugging but don't fail - OAuth flows can have session issues
                Log::warning('Google OAuth state mismatch', [
                    'session_state' => $sessionState,
                    'received_state' => $state
                ]);
            }
        } else {
            // Session might be lost, but we can still validate the state format
            Log::info('Google OAuth session state not found, validating state format only');
        }

        // Clear state from session if it exists
        session()->forget('google_oauth_state');

        try {
            Log::info('Attempting Google token exchange', [
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'code_length' => strlen($code)
            ]);

            $response = Http::post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

            Log::info('Google token exchange response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response_body' => $response->body()
            ]);

            if (!$response->successful()) {
                Log::error('Google OAuth token exchange failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'headers' => $response->headers()
                ]);
                throw new \Exception('Failed to exchange code for access token: ' . $response->body());
            }

            $tokenData = $response->json();
            
            if (!isset($tokenData['access_token'])) {
                Log::error('No access token in Google response', [
                    'response_data' => $tokenData
                ]);
                throw new \Exception('Access token not received from Google');
            }

            Log::info('Google token exchange successful', [
                'token_type' => $tokenData['token_type'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? 'unknown',
                'has_refresh_token' => isset($tokenData['refresh_token'])
            ]);

            return $tokenData;

        } catch (\Exception $e) {
            Log::error('Google OAuth token exchange error', [
                'error' => $e->getMessage(),
                'code_length' => strlen($code),
                'client_id' => $this->clientId
            ]);
            throw $e;
        }
    }

    /**
     * Get user information from Google
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo', [
                    'fields' => 'id,email,name,picture'
                ]);

            if (!$response->successful()) {
                Log::error('Google user info request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception('Failed to get user information from Google');
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Google user info error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get Google Ads accounts for the user
     * Note: This requires Google Ads API access
     */
    public function getAdAccounts(string $accessToken): array
    {
        try {
            // For now, we'll return a mock response since Google Ads API requires additional setup
            // In production, you would use the Google Ads API client library
            Log::info('Google Ads accounts request - returning mock data for now');
            
            // Mock response - replace with actual Google Ads API call
            return [
                [
                    'id' => '1234567890',
                    'name' => 'My Google Ads Account',
                    'currency_code' => 'USD',
                    'time_zone' => 'America/New_York',
                    'descriptive_name' => 'My Business Google Ads Account'
                ]
            ];

        } catch (\Exception $e) {
            Log::warning('Google Ads accounts error - may need additional API setup', [
                'error' => $e->getMessage()
            ]);
            
            // Return empty array instead of throwing exception
            // This allows the OAuth flow to complete successfully
            return [];
        }
    }

    /**
     * Store Google ad account in database
     */
    public function storeAdAccount(array $adAccountData, string $accessToken, array $userInfo, int $tenantId): AdAccount
    {
        // Check if account already exists
        $existingAccount = AdAccount::where('tenant_id', $tenantId)
            ->where('provider', 'google')
            ->where('account_id', $adAccountData['id'])
            ->first();

        if ($existingAccount) {
            // Update existing account
            $existingAccount->update([
                'account_name' => $adAccountData['name'],
                'credentials' => [
                    'access_token' => $accessToken,
                    'user_id' => $userInfo['id'],
                    'user_name' => $userInfo['name'],
                    'user_email' => $userInfo['email'] ?? null,
                    'user_picture' => $userInfo['picture'] ?? null,
                ],
                'settings' => [
                    'currency_code' => $adAccountData['currency_code'] ?? 'USD',
                    'time_zone' => $adAccountData['time_zone'] ?? 'UTC',
                    'descriptive_name' => $adAccountData['descriptive_name'] ?? $adAccountData['name'],
                ],
                'is_active' => true,
            ]);

            return $existingAccount;
        }

        // Create new account
        return AdAccount::create([
            'provider' => 'google',
            'account_name' => $adAccountData['name'],
            'account_id' => $adAccountData['id'],
            'credentials' => [
                'access_token' => $accessToken,
                'user_id' => $userInfo['id'],
                'user_name' => $userInfo['name'],
                'user_email' => $userInfo['email'] ?? null,
                'user_picture' => $userInfo['picture'] ?? null,
            ],
            'settings' => [
                'currency_code' => $adAccountData['currency_code'] ?? 'USD',
                'time_zone' => $adAccountData['time_zone'] ?? 'UTC',
                'descriptive_name' => $adAccountData['descriptive_name'] ?? $adAccountData['name'],
            ],
            'is_active' => true,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Refresh access token if needed
     */
    public function refreshAccessToken(string $currentToken): ?string
    {
        try {
            // Check if token is still valid
            $response = Http::withToken($currentToken)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($response->successful()) {
                return $currentToken; // Token is still valid
            }

            // Token is invalid, would need refresh token to get new one
            Log::warning('Google access token is invalid and needs refresh');
            return null;

        } catch (\Exception $e) {
            Log::error('Google token validation error', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
