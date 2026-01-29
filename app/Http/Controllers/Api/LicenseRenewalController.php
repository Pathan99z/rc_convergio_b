<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\Commerce\PayFastService;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LicenseRenewalController extends Controller
{
    protected LicenseService $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Get all active plans for license renewal (pricing page).
     * 
     * @return JsonResponse
     */
    public function getPlans(): JsonResponse
    {
        try {
            $plans = Plan::active()
                ->ordered()
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'description' => $plan->description,
                        'duration_days' => $plan->duration_days,
                        'price' => number_format($plan->price, 2, '.', ''),
                        'currency' => 'ZAR', // PayFast requires ZAR for license renewals
                        'features' => $plan->features ?? [],
                        'is_active' => $plan->is_active,
                        'sort_order' => $plan->sort_order,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch license plans', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch plans',
                'message' => 'An error occurred while fetching license plans.',
            ], 500);
        }
    }

    /**
     * Get current user's license status.
     * 
     * @return JsonResponse
     */
    public function getStatus(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $licenseInfo = $this->licenseService->getLicenseInfo($user);
            
            if (!$licenseInfo) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'has_license' => false,
                        'is_valid' => false,
                        'status' => 'no_license',
                        'message' => 'No license found for this user.',
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'has_license' => true,
                    'license' => $licenseInfo,
                    'license_check_enabled' => $this->licenseService->isLicenseCheckEnabled(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch license status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Request failed',
                'message' => 'An error occurred while fetching license status.',
            ], 500);
        }
    }

    /**
     * Create PayFast payment URL for license renewal.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function renew(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plan_id' => ['required', 'integer', 'exists:plans,id'],
                'customer_email' => ['required', 'email'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $plan = Plan::findOrFail($request->plan_id);

            if (!$plan->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => 'Plan is not active',
                    'message' => 'The selected plan is not available for purchase.',
                ], 400);
            }

            // Get user/tenant by email
            $user = User::where('email', $request->customer_email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                    'message' => 'No user found with the provided email address.',
                ], 404);
            }

            // Use company PayFast (tenant_id = null)
            $payfastService = new PayFastService(null);

            if (!$payfastService->isConfigured()) {
                Log::error('PayFast not configured for license renewal', [
                    'user_email' => $request->customer_email,
                    'plan_id' => $plan->id,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Payment gateway not configured',
                    'message' => 'Payment gateway is not configured. Please contact support.',
                ], 500);
            }

            // Generate payment ID
            $paymentId = 'license_renewal_' . $plan->id . '_' . time();

            // Get return URLs from environment
            $returnUrl = env('LICENSE_RENEWAL_SUCCESS_URL', url('/license/renewal/success'));
            $cancelUrl = env('LICENSE_RENEWAL_CANCEL_URL', url('/license/renewal/cancel'));
            $notifyUrl = env('LICENSE_RENEWAL_WEBHOOK_URL', url('/api/license/webhooks/payfast'));

            // Create PayFast payment URL
            $payfastData = [
                'payment_id' => $paymentId,
                'amount' => $plan->price,
                'currency' => 'ZAR', // Default currency, can be made configurable
                'item_name' => $plan->name . ' License Renewal',
                'item_description' => $plan->description ?? 'License renewal for ' . $plan->name . ' plan',
                'email_address' => $request->customer_email,
                'name_first' => $user->name ? explode(' ', $user->name)[0] : '',
                'name_last' => $user->name && count(explode(' ', $user->name)) > 1 ? implode(' ', array_slice(explode(' ', $user->name), 1)) : '',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'notify_url' => $notifyUrl,
            ];

            $payfastResult = $payfastService->createPaymentUrl($payfastData);

            if (!$payfastResult['success']) {
                Log::error('Failed to create PayFast payment URL for license renewal', [
                    'user_email' => $request->customer_email,
                    'plan_id' => $plan->id,
                    'error' => $payfastResult['error'] ?? 'Unknown error',
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Payment URL creation failed',
                    'message' => 'Failed to create payment URL. Please try again.',
                ], 500);
            }

            Log::info('License renewal payment URL created', [
                'user_email' => $request->customer_email,
                'plan_id' => $plan->id,
                'payment_id' => $paymentId,
                'amount' => $plan->price,
            ]);

            return response()->json([
                'success' => true,
                'payment_url' => $payfastResult['url'],
                'payment_data' => $payfastResult['payment_data'] ?? null, // Include payment_data for PayFast POST form submission
                'payment_id' => $paymentId,
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => number_format($plan->price, 2, '.', ''),
                    'duration_days' => $plan->duration_days,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('License renewal request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Request failed',
                'message' => 'An error occurred while processing your request. Please try again.',
            ], 500);
        }
    }
}

