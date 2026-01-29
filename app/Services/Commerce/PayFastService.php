<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayFastService
{
    private string $merchantId;
    private string $merchantKey;
    private string $passphrase;
    private string $webhookSecret;
    private string $mode;
    private ?int $tenantId;
    
    // PayFast URLs from environment
    private string $testUrl;
    private string $liveUrl;
    private string $testApiUrl;
    private string $liveApiUrl;

    public function __construct(int $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->loadPayFastConfiguration();
        $this->loadPayFastUrls();
    }

    /**
     * Load PayFast URLs from environment variables.
     */
    private function loadPayFastUrls(): void
    {
        $this->testUrl = env('PAYFAST_TEST_URL', 'https://sandbox.payfast.co.za/eng/process');
        $this->liveUrl = env('PAYFAST_LIVE_URL', 'https://www.payfast.co.za/eng/process');
        $this->testApiUrl = env('PAYFAST_TEST_API_URL', 'https://sandbox.payfast.co.za');
        $this->liveApiUrl = env('PAYFAST_LIVE_API_URL', 'https://api.payfast.co.za');
    }

    /**
     * Load PayFast configuration for the tenant.
     */
    private function loadPayFastConfiguration(): void
    {
        if ($this->tenantId) {
            // Load tenant-specific PayFast configuration
            $settings = CommerceSetting::where('tenant_id', $this->tenantId)->first();
            
            if ($settings && $settings->payfast_merchant_id) {
                $this->merchantId = $settings->payfast_merchant_id;
                $this->merchantKey = $settings->payfast_merchant_key ?? '';
                $this->passphrase = $settings->payfast_passphrase ?? '';
                $this->webhookSecret = $settings->payfast_webhook_secret ?? '';
                $this->mode = $settings->is_live_mode ? 'live' : 'test';
                return;
            }
        }

        // Fallback to global configuration from .env
        $this->mode = config('services.payfast.mode', 'test');
        $this->merchantId = config('services.payfast.merchant_id', '');
        $this->merchantKey = config('services.payfast.merchant_key', '');
        $this->passphrase = config('services.payfast.passphrase', '');
        $this->webhookSecret = config('services.payfast.webhook_secret', '');
    }

    /**
     * Check if PayFast is configured for the tenant.
     */
    public function isConfigured(): bool
    {
        return !empty($this->merchantId) && !empty($this->merchantKey);
    }

    /**
     * Get configuration status.
     */
    public function getConfigurationStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'mode' => $this->mode,
            'has_merchant_id' => !empty($this->merchantId),
            'has_merchant_key' => !empty($this->merchantKey),
            'has_passphrase' => !empty($this->passphrase),
            'has_webhook_secret' => !empty($this->webhookSecret),
            'tenant_id' => $this->tenantId,
        ];
    }

    /**
     * Generate PayFast payment URL for a quote or subscription.
     */
    public function createPaymentUrl(array $data): array
    {
        try {
            // PayFast payment data
            $paymentData = [
                'merchant_id' => $this->merchantId,
                'merchant_key' => $this->merchantKey,
                'return_url' => $data['return_url'] ?? env('COMMERCE_SUCCESS_URL', url('/commerce/success')),
                'cancel_url' => $data['cancel_url'] ?? env('COMMERCE_CANCEL_URL', url('/commerce/cancel')),
                'notify_url' => $data['notify_url'] ?? env('COMMERCE_WEBHOOK_URL', url('/api/commerce/webhooks/payfast')),
                'name_first' => $data['name_first'] ?? '',
                'name_last' => $data['name_last'] ?? '',
                'email_address' => $data['email_address'] ?? '',
                'cell_number' => $data['cell_number'] ?? '',
                'm_payment_id' => $data['payment_id'] ?? '', // Payment link ID or subscription ID
                'amount' => number_format($data['amount'], 2, '.', ''),
                'item_name' => $data['item_name'] ?? 'Payment',
                'item_description' => $data['item_description'] ?? '',
            ];

            // Add currency if provided (PayFast supports ZAR, USD, EUR, GBP, etc.)
            // For subscriptions, currency MUST be ZAR (PayFast requirement)
            if (isset($data['subscription_type'])) {
                // Force ZAR for subscriptions
                $paymentData['currency'] = 'ZAR';
            } elseif (isset($data['currency']) && !empty($data['currency'])) {
                // For one-time payments, use provided currency
                // Trim whitespace and convert to uppercase
                $currency = strtoupper(trim($data['currency']));
                
                // Validate: PayFast requires exactly 3 uppercase letters (ISO currency code)
                // Supported currencies: ZAR, USD, EUR, GBP, AUD, etc.
                if (preg_match('/^[A-Z]{3}$/', $currency)) {
                    $paymentData['currency'] = $currency;
                } else {
                    // Invalid format (has spaces, special chars, or wrong length)
                    // Default to ZAR and log warning
                    Log::channel('commerce')->warning('Invalid currency format for PayFast, defaulting to ZAR', [
                        'provided_currency' => $data['currency'],
                        'trimmed_currency' => $currency,
                        'tenant_id' => $this->tenantId,
                    ]);
                    $paymentData['currency'] = 'ZAR';
                }
            } else {
                // No currency provided, default to ZAR
                $paymentData['currency'] = 'ZAR';
            }

            // Add subscription-specific fields if provided
            if (isset($data['subscription_type'])) {
                $paymentData['subscription_type'] = $data['subscription_type'];
                $paymentData['billing_date'] = $data['billing_date'] ?? date('Y-m-d');
                $paymentData['recurring_amount'] = number_format($data['recurring_amount'], 2, '.', '');
                // PayFast frequency must be INTEGER: 1=Daily, 2=Weekly, 3=Monthly, 4=Quarterly, 5=Biannually, 6=Yearly
                $paymentData['frequency'] = $data['frequency'] ?? 3; // Default to Monthly (3) - INTEGER, not 'M'
                $paymentData['cycles'] = $data['cycles'] ?? '0'; // 0 = infinite
            }

            // Generate signature
            $signature = $this->generateSignature($paymentData);
            $paymentData['signature'] = $signature;

            $paymentUrl = ($this->mode === 'live' ? $this->liveUrl : $this->testUrl);

            Log::channel('commerce')->info('PayFast payment URL created', [
                'payment_id' => $data['payment_id'] ?? null,
                'amount' => $data['amount'],
                'mode' => $this->mode,
                'tenant_id' => $this->tenantId,
            ]);

            return [
                'success' => true,
                'url' => $paymentUrl,
                'payment_data' => $paymentData,
                'payment_id' => $data['payment_id'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::channel('commerce')->error('PayFast payment URL creation failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $this->tenantId,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create payment URL',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate PayFast signature for payment data.
     */
    private function generateSignature(array $data): string
    {
        // Remove signature and empty values
        unset($data['signature']);
        $data = array_filter($data, function($value) {
            return $value !== '' && $value !== null;
        });

        // Sort by key
        ksort($data);

        // Create parameter string
        $pfParamString = '';
        foreach ($data as $key => $value) {
            $pfParamString .= $key . '=' . urlencode(stripslashes($value)) . '&';
        }
        $pfParamString = substr($pfParamString, 0, -1);

        // Add passphrase if set
        if (!empty($this->passphrase)) {
            $pfParamString .= '&passphrase=' . urlencode($this->passphrase);
        }

        // Generate signature
        return md5($pfParamString);
    }

    /**
     * Verify PayFast ITN (Instant Transaction Notification) signature.
     */
    public function verifyITN(array $data): bool
    {
        try {
            // Get the signature from the data
            $providedSignature = $data['signature'] ?? '';
            
            if (empty($providedSignature)) {
                Log::channel('commerce')->warning('PayFast ITN signature missing', [
                    'tenant_id' => $this->tenantId,
                ]);
                return false;
            }

            // Reconstruct signature
            $expectedSignature = $this->generateSignature($data);
            
            // Compare signatures
            return hash_equals($expectedSignature, $providedSignature);
        } catch (\Exception $e) {
            Log::channel('commerce')->error('PayFast ITN verification failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $this->tenantId,
            ]);
            return false;
        }
    }

    /**
     * Query payment status from PayFast.
     */
    public function queryPaymentStatus(string $paymentId): array
    {
        try {
            $apiUrl = ($this->mode === 'live' ? $this->liveApiUrl : $this->testApiUrl) . '/query/checkout';
            
            $response = Http::asForm()->post($apiUrl, [
                'merchant_id' => $this->merchantId,
                'merchant_key' => $this->merchantKey,
                'pf_payment_id' => $paymentId,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to query payment status',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test connection to PayFast API.
     */
    public function testConnection(): array
    {
        try {
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'PayFast credentials not configured',
                ];
            }

            // Try to query a test payment (this will validate credentials)
            $apiUrl = ($this->mode === 'live' ? $this->liveApiUrl : $this->testApiUrl) . '/query/checkout';
            
            $response = Http::asForm()->post($apiUrl, [
                'merchant_id' => $this->merchantId,
                'merchant_key' => $this->merchantKey,
                'pf_payment_id' => 'test_' . time(),
            ]);

            // Even if payment not found, if we get a proper response, credentials are valid
            if ($response->status() === 200 || $response->status() === 404) {
                return [
                    'success' => true,
                    'message' => 'PayFast connection successful',
                    'mode' => $this->mode,
                    'merchant_id' => substr($this->merchantId, 0, 4) . '***',
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to connect to PayFast API',
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
     * Get PayFast configuration for frontend (public key equivalent).
     */
    public function getFrontendConfig(): array
    {
        return [
            'merchant_id' => substr($this->merchantId, 0, 4) . '***', // Partially masked
            'mode' => $this->mode,
            'configured' => $this->isConfigured(),
        ];
    }

    /**
     * Validate PayFast credentials format.
     */
    public function validateKeys(): array
    {
        $errors = [];

        if (empty($this->merchantId)) {
            $errors[] = 'Merchant ID is required';
        } elseif (strlen($this->merchantId) < 5) {
            $errors[] = 'Invalid Merchant ID format';
        }

        if (empty($this->merchantKey)) {
            $errors[] = 'Merchant Key is required';
        } elseif (strlen($this->merchantKey) < 5) {
            $errors[] = 'Invalid Merchant Key format';
        }

        return $errors;
    }
}

