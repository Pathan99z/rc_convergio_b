<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FacebookOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FacebookOAuthController extends Controller
{
    public function __construct(
        private FacebookOAuthService $facebookOAuthService
    ) {}

    /**
     * Redirect user to Facebook OAuth
     */
    public function redirect(): JsonResponse
    {
        try {
            $authUrl = $this->facebookOAuthService->getAuthorizationUrl();
            
            return response()->json([
                'message' => 'Redirect to Facebook OAuth',
                'auth_url' => $authUrl,
                'redirect_required' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Facebook OAuth redirect error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to initiate Facebook OAuth',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Facebook OAuth callback
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
                Log::warning('Facebook OAuth error', [
                    'error' => $error,
                    'description' => $errorDescription
                ]);

                // Redirect to frontend error page
                return redirect('http://localhost:5173/marketing/ads?facebook_oauth=error&error=' . urlencode($error) . '&error_description=' . urlencode($errorDescription));
            }

            // Validate required parameters
            if (!$code || !$state) {
                // Redirect to frontend error page
                return redirect('http://localhost:5173/marketing/ads?facebook_oauth=error&error=invalid_request&error_description=' . urlencode('Missing required OAuth parameters'));
            }

            // Debug: Log the state parameter for troubleshooting
            Log::info('Facebook OAuth callback received', [
                'code_present' => !empty($code),
                'state_present' => !empty($state),
                'state_length' => strlen($state),
                'session_has_state' => session()->has('facebook_oauth_state')
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
                $tokenData = $this->facebookOAuthService->exchangeCodeForToken($code, $state);
                $accessToken = $tokenData['access_token'];

                // Get user information from Facebook
                $userInfo = $this->facebookOAuthService->getUserInfo($accessToken);

                // Get ad accounts (may be empty if ads_read permission not available)
                $adAccounts = $this->facebookOAuthService->getAdAccounts($accessToken);

                $storedAccounts = [];
                $errors = [];

                // Store each ad account (if any)
                foreach ($adAccounts as $adAccountData) {
                    try {
                        $storedAccount = $this->facebookOAuthService->storeAdAccount(
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
                        Log::error('Failed to store Facebook ad account', [
                            'account_data' => $adAccountData,
                            'error' => $e->getMessage(),
                            'tenant_id' => $tenantId
                        ]);
                    }
                }

                DB::commit();

                $message = 'Facebook OAuth completed successfully';
                if (empty($adAccounts)) {
                    $message .= '. Note: No ad accounts found - this may be due to missing ads_read permission which requires Facebook App Review.';
                }

                // Log success for debugging
                Log::info('Facebook OAuth completed successfully', [
                    'user_info' => $userInfo,
                    'accounts_stored' => count($storedAccounts),
                    'total_found' => count($adAccounts)
                ]);

                // Redirect to frontend success page
                return redirect('http://localhost:5173/marketing/ads?facebook_oauth=success&user_name=' . urlencode($userInfo['name']) . '&accounts_count=' . count($storedAccounts));

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Facebook OAuth callback error', [
                'error' => $e->getMessage(),
                'request_params' => $request->query()
            ]);

            // Redirect to frontend error page
            return redirect('http://localhost:5173/marketing/ads?facebook_oauth=error&error=connection_failed&error_description=' . urlencode($e->getMessage()));
        }
    }


    /**
     * Get current user's Facebook ad accounts
     */
    public function getAdAccounts(): JsonResponse
    {
        try {
            $user = Auth::user();
            $facebookAccounts = \App\Models\AdAccount::where('tenant_id', $user->tenant_id)
                ->where('provider', 'facebook')
                ->where('is_active', true)
                ->get();

            return response()->json([
                'message' => 'Facebook ad accounts retrieved successfully',
                'data' => $facebookAccounts->map(function ($account) {
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
            Log::error('Failed to get Facebook ad accounts', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve Facebook ad accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect a Facebook ad account
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
                ->where('provider', 'facebook')
                ->firstOrFail();

            $account->delete();

            return response()->json([
                'message' => 'Facebook ad account disconnected successfully',
                'account_name' => $account->account_name
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to disconnect Facebook ad account', [
                'error' => $e->getMessage(),
                'account_id' => $request->account_id ?? null,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to disconnect Facebook ad account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh Facebook ad account connection
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
                ->where('provider', 'facebook')
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
            $newToken = $this->facebookOAuthService->refreshAccessToken($accessToken);

            if (!$newToken) {
                return response()->json([
                    'message' => 'Access token is invalid. Please reconnect your Facebook account.',
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
                'message' => 'Facebook ad account connection refreshed successfully',
                'account_name' => $account->account_name,
                'is_valid' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to refresh Facebook ad account', [
                'error' => $e->getMessage(),
                'account_id' => $request->account_id ?? null,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to refresh Facebook ad account connection',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
