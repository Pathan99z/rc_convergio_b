<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class StripeService
{
    private string $secretKey;
    private string $publicKey;
    private string $mode;
    private string $webhookSecret;
    private int $tenantId;

    public function __construct(int $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->loadStripeConfiguration();
    }

    /**
     * Load Stripe configuration for the tenant.
     */
    private function loadStripeConfiguration(): void
    {
        if ($this->tenantId) {
            // Load customer-specific Stripe configuration
            $settings = CommerceSetting::where('tenant_id', $this->tenantId)->first();
            
            if ($settings && $settings->stripe_secret_key) {
                $this->secretKey = $settings->stripe_secret_key;
                $this->publicKey = $settings->stripe_public_key ?? '';
                $this->webhookSecret = $settings->stripe_webhook_secret ?? '';
                $this->mode = $settings->is_live_mode ? 'live' : 'test';
                return;
            }
        }

        // Fallback to global configuration
        $this->mode = config('commerce.stripe.mode', 'test');
        $this->secretKey = $this->mode === 'live' 
            ? config('commerce.stripe.live_secret_key', '')
            : config('commerce.stripe.test_secret_key', '');
        $this->publicKey = $this->mode === 'live'
            ? config('commerce.stripe.live_public_key', '')
            : config('commerce.stripe.test_public_key', '');
        $this->webhookSecret = config('commerce.stripe.webhook_secret', '');
    }

    /**
     * Check if Stripe is configured for the tenant.
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey) && !empty($this->publicKey);
    }

    /**
     * Get configuration status.
     */
    public function getConfigurationStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'mode' => $this->mode,
            'has_secret_key' => !empty($this->secretKey),
            'has_public_key' => !empty($this->publicKey),
            'has_webhook_secret' => !empty($this->webhookSecret),
            'tenant_id' => $this->tenantId,
        ];
    }

    /**
     * Create a Stripe checkout session.
     */
    public function createCheckoutSession(array $lineItems, string $successUrl, string $cancelUrl, array $metadata = []): array
    {
        try {
            $payload = [
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => $metadata,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://api.stripe.com/v1/checkout/sessions', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::channel('commerce')->info('Stripe checkout session created', [
                    'session_id' => $data['id'],
                    'mode' => $this->mode,
                    'amount_total' => $data['amount_total'] ?? null,
                ]);

                return [
                    'success' => true,
                    'session_id' => $data['id'],
                    'url' => $data['url'],
                    'public_key' => $this->publicKey,
                ];
            }

            Log::channel('commerce')->error('Stripe checkout session creation failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create checkout session',
                'details' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::channel('commerce')->error('Stripe checkout session creation exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred while creating checkout session',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (!$this->webhookSecret) {
            Log::channel('commerce')->warning('No webhook secret configured for signature verification');
            return false;
        }

        try {
            $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
            $providedSignature = str_replace('sha256=', '', $signature);

            return hash_equals($expectedSignature, $providedSignature);
        } catch (\Exception $e) {
            Log::channel('commerce')->error('Webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Parse webhook event.
     */
    public function parseEvent(string $payload): ?array
    {
        try {
            $event = json_decode($payload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::channel('commerce')->error('Invalid JSON in webhook payload', [
                    'error' => json_last_error_msg(),
                ]);
                return null;
            }

            return $event;
        } catch (\Exception $e) {
            Log::channel('commerce')->error('Failed to parse webhook event', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Test connection to Stripe API.
     */
    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get('https://api.stripe.com/v1/account');

            if ($response->successful()) {
                $account = $response->json();
                
                return [
                    'success' => true,
                    'account_id' => $account['id'] ?? null,
                    'country' => $account['country'] ?? null,
                    'charges_enabled' => $account['charges_enabled'] ?? false,
                    'payouts_enabled' => $account['payouts_enabled'] ?? false,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to connect to Stripe API',
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception occurred while testing connection',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Stripe configuration for frontend.
     */
    public function getFrontendConfig(): array
    {
        return [
            'public_key' => $this->publicKey,
            'mode' => $this->mode,
        ];
    }

    /**
     * Create payment intent (alternative to checkout session).
     */
    public function createPaymentIntent(int $amount, string $currency, array $metadata = []): array
    {
        try {
            $payload = [
                'amount' => $amount,
                'currency' => strtolower($currency),
                'metadata' => $metadata,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://api.stripe.com/v1/payment_intents', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'client_secret' => $data['client_secret'],
                    'id' => $data['id'],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to create payment intent',
                'details' => $response->json(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception occurred while creating payment intent',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve a checkout session.
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'session' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to retrieve checkout session',
                'details' => $response->json(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception occurred while retrieving checkout session',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a refund.
     */
    public function createRefund(string $paymentIntentId, int $amount = null, string $reason = null): array
    {
        try {
            $payload = [
                'payment_intent' => $paymentIntentId,
            ];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            if ($reason) {
                $payload['reason'] = $reason;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://api.stripe.com/v1/refunds', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::channel('commerce')->info('Stripe refund created', [
                    'refund_id' => $data['id'],
                    'payment_intent_id' => $paymentIntentId,
                    'amount' => $data['amount'],
                    'tenant_id' => $this->tenantId,
                ]);

                return [
                    'success' => true,
                    'refund_id' => $data['id'],
                    'amount' => $data['amount'],
                    'status' => $data['status'],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to create refund',
                'details' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::channel('commerce')->error('Stripe refund creation exception', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntentId,
                'tenant_id' => $this->tenantId,
            ]);

            return [
                'success' => false,
                'error' => 'Exception occurred while creating refund',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment status.
     */
    public function getPaymentStatus(string $paymentIntentId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get("https://api.stripe.com/v1/payment_intents/{$paymentIntentId}");

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'status' => $data['status'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'charges' => $data['charges']['data'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get payment status',
                'details' => $response->json(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception occurred while getting payment status',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate Stripe keys.
     */
    public function validateKeys(): array
    {
        if (!$this->isConfigured()) {
            return [
                'valid' => false,
                'error' => 'Stripe keys not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get('https://api.stripe.com/v1/account');

            if ($response->successful()) {
                $account = $response->json();
                
                return [
                    'valid' => true,
                    'account_id' => $account['id'],
                    'country' => $account['country'],
                    'charges_enabled' => $account['charges_enabled'] ?? false,
                    'payouts_enabled' => $account['payouts_enabled'] ?? false,
                    'details_submitted' => $account['details_submitted'] ?? false,
                ];
            }

            return [
                'valid' => false,
                'error' => 'Invalid Stripe keys',
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Exception occurred while validating keys',
                'details' => $e->getMessage(),
            ];
        }
    }
}
