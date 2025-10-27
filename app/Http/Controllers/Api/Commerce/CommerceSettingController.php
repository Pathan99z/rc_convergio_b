<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Services\Commerce\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommerceSettingController extends Controller
{
    public function __construct(
        private SettingService $settingService
    ) {}

    /**
     * Display the commerce settings.
     */
    public function show(Request $request): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $keys = $this->settingService->getKeys($tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Commerce settings retrieved successfully',
            'data' => $keys,
        ]);
    }

    /**
     * Update the commerce settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stripe_public_key' => 'nullable|string|max:255',
            'stripe_secret_key' => 'nullable|string|max:255',
            'mode' => 'string|in:test,live',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        // Validate Stripe keys format
        $keyErrors = $this->settingService->validateStripeKeys($request->all());
        if (!empty($keyErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe key validation failed',
                'errors' => $keyErrors,
            ], 422);
        }

        $settings = $this->settingService->updateKeys($tenantId, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Commerce settings updated successfully',
            'data' => [
                'stripe_public_key' => $settings->getStripePublicKey(),
                'stripe_secret_key' => $settings->getStripeSecretKey() ? '***' . substr($settings->getStripeSecretKey(), -4) : null,
                'mode' => $settings->mode,
                'has_keys' => $settings->hasStripeKeys(),
            ],
        ]);
    }

    /**
     * Test Stripe connection.
     */
    public function testConnection(Request $request): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $result = $this->settingService->testStripeConnection($tenantId);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result,
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Reset Stripe keys.
     */
    public function reset(Request $request): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $settings = $this->settingService->resetKeys($tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Commerce settings reset successfully',
            'data' => [
                'stripe_public_key' => null,
                'stripe_secret_key' => null,
                'mode' => 'test',
                'has_keys' => false,
            ],
        ]);
    }

    /**
     * Get Stripe configuration for frontend.
     */
    public function getStripeConfig(Request $request): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $config = $this->settingService->getStripeConfig($tenantId);

        // Only return public key for frontend use
        return response()->json([
            'success' => true,
            'message' => 'Stripe configuration retrieved successfully',
            'data' => [
                'public_key' => $config['public_key'],
                'mode' => $config['mode'],
                'configured' => $config['configured'],
            ],
        ]);
    }

    /**
     * Send a test email with a payment link.
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'customer_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get tenant_id from header or use user's organization as fallback
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                // Use organization_name to determine tenant_id
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4; // chitti's organization
                } else {
                    $tenantId = 1; // default tenant
                }
            }

            // Get a quote for testing
            $quote = \App\Models\Quote::where('tenant_id', $tenantId)->first();
            
            if (!$quote) {
                return response()->json([
                    'success' => false,
                    'message' => 'No quotes found for testing. Please create a quote first.',
                ], 404);
            }

            // Create a test payment link
            $paymentLinkService = new \App\Services\Commerce\PaymentLinkService();
            $paymentLink = $paymentLinkService->createPaymentLink($quote, [
                'title' => 'Test Payment Link - ' . $request->input('customer_name'),
                'description' => 'This is a test payment link to verify email functionality.',
                'expires_at' => now()->addDays(7),
            ]);

            // Send the test email
            $emailSent = $paymentLinkService->sendPaymentLinkEmail(
                $paymentLink,
                $request->input('email'),
                $request->input('customer_name'),
                []
            );

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully',
                'data' => [
                    'email_sent' => $emailSent,
                    'recipient' => $request->input('email'),
                    'customer_name' => $request->input('customer_name'),
                    'payment_link_id' => $paymentLink->id,
                    'payment_link_url' => $paymentLink->public_url_signed,
                    'quote_number' => $quote->quote_number,
                    'amount' => $paymentLink->amount,
                    'currency' => $paymentLink->currency,
                ],
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Send test email error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
