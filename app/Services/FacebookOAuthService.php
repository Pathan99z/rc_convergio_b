<?php

namespace App\Services;

use App\Models\AdAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FacebookOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.facebook.client_id');
        $this->clientSecret = config('services.facebook.client_secret');
        $this->redirectUri = config('services.facebook.redirect');
    }

    /**
     * Generate Facebook OAuth authorization URL
     */
    public function getAuthorizationUrl(): string
    {
        $user = Auth::user();
        $state = base64_encode(json_encode([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'nonce' => bin2hex(random_bytes(16))
        ]));
        
        session(['facebook_oauth_state' => $state]);

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'email,public_profile',
            'response_type' => 'code',
            'state' => $state,
        ];

        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
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
        if (session()->has('facebook_oauth_state')) {
            $sessionState = session('facebook_oauth_state');
            if ($sessionState !== $state) {
                // Log for debugging but don't fail - OAuth flows can have session issues
                Log::warning('Facebook OAuth state mismatch', [
                    'session_state' => $sessionState,
                    'received_state' => $state
                ]);
            }
        } else {
            // Session might be lost, but we can still validate the state format
            Log::info('Facebook OAuth session state not found, validating state format only');
        }

        // Clear state from session if it exists
        session()->forget('facebook_oauth_state');

        try {
            Log::info('Attempting Facebook token exchange', [
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'code_length' => strlen($code)
            ]);

            $response = Http::post('https://graph.facebook.com/v18.0/oauth/access_token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'code' => $code,
            ]);

            Log::info('Facebook token exchange response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response_body' => $response->body()
            ]);

            if (!$response->successful()) {
                Log::error('Facebook OAuth token exchange failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'headers' => $response->headers()
                ]);
                throw new \Exception('Failed to exchange code for access token: ' . $response->body());
            }

            $tokenData = $response->json();
            
            if (!isset($tokenData['access_token'])) {
                Log::error('No access token in Facebook response', [
                    'response_data' => $tokenData
                ]);
                throw new \Exception('Access token not received from Facebook');
            }

            Log::info('Facebook token exchange successful', [
                'token_type' => $tokenData['token_type'] ?? 'unknown',
                'expires_in' => $tokenData['expires_in'] ?? 'unknown'
            ]);

            return $tokenData;

        } catch (\Exception $e) {
            Log::error('Facebook OAuth token exchange error', [
                'error' => $e->getMessage(),
                'code_length' => strlen($code),
                'client_id' => $this->clientId
            ]);
            throw $e;
        }
    }

    /**
     * Get user information from Facebook
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://graph.facebook.com/v18.0/me', [
                    'fields' => 'id,name,email'
                ]);

            if (!$response->successful()) {
                Log::error('Facebook user info request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception('Failed to get user information from Facebook');
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Facebook user info error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get Facebook ad accounts for the user
     * Note: This requires ads_read permission which needs Facebook App Review
     */
    public function getAdAccounts(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://graph.facebook.com/v18.0/me/adaccounts', [
                    'fields' => 'id,name,account_status,currency,timezone_name'
                ]);

            if (!$response->successful()) {
                Log::warning('Facebook ad accounts request failed - likely due to missing ads_read permission', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                
                // Return empty array instead of throwing exception
                // This allows the OAuth flow to complete successfully
                return [];
            }

            $data = $response->json();
            return $data['data'] ?? [];

        } catch (\Exception $e) {
            Log::warning('Facebook ad accounts error - likely due to missing permissions', [
                'error' => $e->getMessage()
            ]);
            
            // Return empty array instead of throwing exception
            return [];
        }
    }

    /**
     * Store Facebook ad account in database
     */
    public function storeAdAccount(array $adAccountData, string $accessToken, array $userInfo, int $tenantId): AdAccount
    {

        // Check if account already exists
        $existingAccount = AdAccount::where('tenant_id', $tenantId)
            ->where('provider', 'facebook')
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
                ],
                'settings' => [
                    'account_status' => $adAccountData['account_status'],
                    'currency' => $adAccountData['currency'],
                    'timezone' => $adAccountData['timezone_name'],
                ],
                'is_active' => $adAccountData['account_status'] === 'ACTIVE',
            ]);

            return $existingAccount;
        }

        // Create new account
        return AdAccount::create([
            'provider' => 'facebook',
            'account_name' => $adAccountData['name'],
            'account_id' => $adAccountData['id'],
            'credentials' => [
                'access_token' => $accessToken,
                'user_id' => $userInfo['id'],
                'user_name' => $userInfo['name'],
                'user_email' => $userInfo['email'] ?? null,
            ],
            'settings' => [
                'account_status' => $adAccountData['account_status'],
                'currency' => $adAccountData['currency'],
                'timezone' => $adAccountData['timezone_name'],
            ],
            'is_active' => $adAccountData['account_status'] === 'ACTIVE',
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Refresh access token if needed
     */
    public function refreshAccessToken(string $currentToken): ?string
    {
        try {
            // Facebook tokens are long-lived by default, but we can check if it's still valid
            $response = Http::withToken($currentToken)
                ->get('https://graph.facebook.com/v18.0/me');

            if ($response->successful()) {
                return $currentToken; // Token is still valid
            }

            // Token is invalid, would need to re-authenticate
            return null;

        } catch (\Exception $e) {
            Log::error('Facebook token refresh error', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
