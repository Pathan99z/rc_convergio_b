<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GoogleOAuthController extends Controller
{
    public function __construct(
        private GoogleOAuthService $googleOAuthService
    ) {}

    /**
     * Redirect user to Google OAuth
     */
    public function redirect(): JsonResponse
    {
        try {
            $authUrl = $this->googleOAuthService->getAuthorizationUrl();
            
            return response()->json([
                'message' => 'Redirect to Google OAuth',
                'auth_url' => $authUrl,
                'redirect_required' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Google OAuth redirect error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to initiate Google OAuth',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback(Request $request)
    {
        try {
            $code = $request->query('code');
            $state = $request->query('state');
            $error = $request->query('error');

            // Check for OAuth errors
            if ($error) {
                $errorDescription = $request->query('error_description', 'Unknown error');
                Log::warning('Google OAuth error', [
                    'error' => $error,
                    'description' => $errorDescription
                ]);

                // Redirect to frontend error page
                return redirect('http://localhost:5173/marketing/ads?google_oauth=error&error=' . urlencode($error) . '&error_description=' . urlencode($errorDescription));
            }

            // Validate required parameters
            if (!$code || !$state) {
                // Redirect to frontend error page
                return redirect('http://localhost:5173/marketing/ads?google_oauth=error&error=invalid_request&error_description=' . urlencode('Missing required OAuth parameters'));
            }

            // Debug: Log the state parameter for troubleshooting
            Log::info('Google OAuth callback received', [
                'code_present' => !empty($code),
                'state_present' => !empty($state),
                'state_length' => strlen($state),
                'session_has_state' => session()->has('google_oauth_state')
            ]);

            DB::beginTransaction();

            try {
                // Get state data to identify the user first
                $stateData = json_decode(base64_decode($state), true);
                if (!$stateData || !isset($stateData['tenant_id'])) {
                    throw new \Exception('Invalid state data - missing tenant information');
                }
                $tenantId = $stateData['tenant_id'];

                // Exchange code for access token
                $tokenData = $this->googleOAuthService->exchangeCodeForToken($code, $state);
                $accessToken = $tokenData['access_token'];

                // Get user information from Google
                $userInfo = $this->googleOAuthService->getUserInfo($accessToken);

                // Get ad accounts (may be empty if Google Ads API not fully configured)
                $adAccounts = $this->googleOAuthService->getAdAccounts($accessToken);

                $storedAccounts = [];
                $errors = [];

                // Store each ad account (if any)
                foreach ($adAccounts as $adAccountData) {
                    try {
                        $storedAccount = $this->googleOAuthService->storeAdAccount(
                            $adAccountData,
                            $accessToken,
                            $userInfo,
                            $tenantId
                        );
                        $storedAccounts[] = $storedAccount;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'account_id' => $adAccountData['id'] ?? 'unknown',
                            'account_name' => $adAccountData['name'] ?? 'unknown',
                            'error' => $e->getMessage()
                        ];
                        Log::error('Failed to store Google ad account', [
                            'account_data' => $adAccountData,
                            'error' => $e->getMessage(),
                            'tenant_id' => $tenantId
                        ]);
                    }
                }

                DB::commit();

                $message = 'Google OAuth completed successfully';
                if (empty($adAccounts)) {
                    $message .= '. Note: No ad accounts found - this may be due to Google Ads API configuration requirements.';
                }

                // Log success for debugging
                Log::info('Google OAuth completed successfully', [
                    'user_info' => $userInfo,
                    'accounts_stored' => count($storedAccounts),
                    'total_found' => count($adAccounts)
                ]);

                // Redirect to frontend success page
                return redirect('http://localhost:5173/marketing/ads?google_oauth=success&user_name=' . urlencode($userInfo['name']) . '&accounts_count=' . count($storedAccounts));

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Google OAuth callback error', [
                'error' => $e->getMessage(),
                'request_params' => $request->query()
            ]);

            // Redirect to frontend error page
            return redirect('http://localhost:5173/marketing/ads?google_oauth=error&error=connection_failed&error_description=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Get current user's Google ad accounts
     */
    public function getAdAccounts(): JsonResponse
    {
        try {
            $user = Auth::user();
            $googleAccounts = \App\Models\AdAccount::where('tenant_id', $user->tenant_id)
                ->where('provider', 'google')
                ->where('is_active', true)
                ->get();

            return response()->json([
                'message' => 'Google ad accounts retrieved successfully',
                'data' => $googleAccounts->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'account_name' => $account->account_name,
                        'account_id' => $account->account_id,
                        'is_active' => $account->is_active,
                        'settings' => $account->settings,
                        'created_at' => $account->created_at,
                        'updated_at' => $account->updated_at,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Google ad accounts', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve Google ad accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect a Google ad account
     */
    public function disconnect(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'account_id' => 'required|integer|exists:ad_accounts,id'
            ]);

            $user = Auth::user();
            $account = \App\Models\AdAccount::where('id', $request->account_id)
                ->where('tenant_id', $user->tenant_id)
                ->where('provider', 'google')
                ->firstOrFail();

            $account->delete();

            return response()->json([
                'message' => 'Google ad account disconnected successfully',
                'account_name' => $account->account_name
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to disconnect Google ad account', [
                'error' => $e->getMessage(),
                'account_id' => $request->account_id ?? null,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to disconnect Google ad account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh Google ad account connection
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'account_id' => 'required|integer|exists:ad_accounts,id'
            ]);

            $user = Auth::user();
            $account = \App\Models\AdAccount::where('id', $request->account_id)
                ->where('tenant_id', $user->tenant_id)
                ->where('provider', 'google')
                ->firstOrFail();

            $credentials = $account->credentials;
            $accessToken = $credentials['access_token'] ?? null;

            if (!$accessToken) {
                return response()->json([
                    'message' => 'No access token found for this account',
                    'error' => 'missing_token'
                ], 400);
            }

            // Check if token is still valid
            $newToken = $this->googleOAuthService->refreshAccessToken($accessToken);

            if (!$newToken) {
                return response()->json([
                    'message' => 'Access token is invalid. Please reconnect your Google account.',
                    'error' => 'invalid_token',
                    'requires_reauth' => true
                ], 400);
            }

            // Update token if it changed
            if ($newToken !== $accessToken) {
                $credentials['access_token'] = $newToken;
                $account->update(['credentials' => $credentials]);
            }

            return response()->json([
                'message' => 'Google ad account connection refreshed successfully',
                'account_name' => $account->account_name,
                'is_valid' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to refresh Google ad account', [
                'error' => $e->getMessage(),
                'account_id' => $request->account_id ?? null,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to refresh Google ad account connection',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}