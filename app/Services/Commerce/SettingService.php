<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class SettingService
{
    /**
     * Get Stripe keys for a tenant.
     */
    public function getKeys(int $tenantId): array
    {
        $settings = CommerceSetting::getForTenant($tenantId);

        return [
            'stripe_public_key' => $settings->getStripePublicKey(),
            'stripe_secret_key' => $settings->getStripeSecretKey() ? '***' . substr($settings->getStripeSecretKey(), -4) : null,
            'mode' => $settings->mode,
            'has_keys' => $settings->hasStripeKeys(),
        ];
    }

    /**
     * Update Stripe keys for a tenant.
     */
    public function updateKeys(int $tenantId, array $data): CommerceSetting
    {
        $settings = CommerceSetting::getForTenant($tenantId);

        $updateData = [];

        if (isset($data['stripe_public_key'])) {
            $updateData['stripe_public_key'] = $data['stripe_public_key'];
        }

        if (isset($data['stripe_secret_key'])) {
            $updateData['stripe_secret_key'] = $data['stripe_secret_key'];
        }

        if (isset($data['mode'])) {
            $updateData['mode'] = $data['mode'];
        }

        $settings->update($updateData);

        Log::info('Commerce settings updated', [
            'tenant_id' => $tenantId,
            'mode' => $settings->mode,
            'has_public_key' => !empty($settings->stripe_public_key),
            'has_secret_key' => !empty($settings->stripe_secret_key),
        ]);

        return $settings->fresh();
    }

    /**
     * Get settings for a tenant.
     */
    public function getSettings(int $tenantId): CommerceSetting
    {
        return CommerceSetting::getForTenant($tenantId);
    }

    /**
     * Check if tenant has Stripe configured.
     */
    public function hasStripeConfigured(int $tenantId): bool
    {
        $settings = CommerceSetting::getForTenant($tenantId);
        return $settings->hasStripeKeys();
    }

    /**
     * Get Stripe configuration for API usage.
     */
    public function getStripeConfig(int $tenantId): array
    {
        $settings = CommerceSetting::getForTenant($tenantId);

        return [
            'public_key' => $settings->getStripePublicKey(),
            'secret_key' => $settings->getStripeSecretKey(),
            'mode' => $settings->mode,
            'configured' => $settings->hasStripeKeys(),
        ];
    }

    /**
     * Validate Stripe keys format.
     */
    public function validateStripeKeys(array $data): array
    {
        $errors = [];

        if (isset($data['stripe_public_key']) && !empty($data['stripe_public_key'])) {
            if (!str_starts_with($data['stripe_public_key'], 'pk_')) {
                $errors['stripe_public_key'] = 'Invalid Stripe public key format. Must start with "pk_".';
            }
        }

        if (isset($data['stripe_secret_key']) && !empty($data['stripe_secret_key'])) {
            if (!str_starts_with($data['stripe_secret_key'], 'sk_')) {
                $errors['stripe_secret_key'] = 'Invalid Stripe secret key format. Must start with "sk_".';
            }
        }

        if (isset($data['mode']) && !in_array($data['mode'], ['test', 'live'])) {
            $errors['mode'] = 'Mode must be either "test" or "live".';
        }

        return $errors;
    }

    /**
     * Reset Stripe keys for a tenant.
     */
    public function resetKeys(int $tenantId): CommerceSetting
    {
        $settings = CommerceSetting::getForTenant($tenantId);

        $settings->update([
            'stripe_public_key' => null,
            'stripe_secret_key' => null,
            'mode' => 'test',
        ]);

        Log::info('Commerce settings reset', [
            'tenant_id' => $tenantId,
        ]);

        return $settings->fresh();
    }

    /**
     * Test Stripe connection.
     */
    public function testStripeConnection(int $tenantId): array
    {
        $config = $this->getStripeConfig($tenantId);

        if (!$config['configured']) {
            return [
                'success' => false,
                'message' => 'Stripe keys not configured',
            ];
        }

        try {
            // First validate key format
            if ($config['mode'] === 'test') {
                if (!str_starts_with($config['secret_key'], 'sk_test_')) {
                    return [
                        'success' => false,
                        'message' => 'Invalid test secret key format',
                    ];
                }
            } else {
                if (!str_starts_with($config['secret_key'], 'sk_live_')) {
                    return [
                        'success' => false,
                        'message' => 'Invalid live secret key format',
                    ];
                }
            }

            // Now actually test the Stripe API connection using cURL
            $result = $this->validateStripeKey($config['secret_key'], $config['mode']);
            
            if (!$result['success']) {
                return $result;
            }

            return [
                'success' => true,
                'message' => 'Stripe connection successful',
                'mode' => $config['mode'],
                'account_id' => $result['account_id'] ?? 'unknown',
                'account_type' => $result['account_type'] ?? 'standard',
            ];
        } catch (\Exception $e) {
            Log::error('Stripe connection test failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Stripe connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate Stripe key by making a real API call using cURL.
     */
    private function validateStripeKey(string $secretKey, string $mode): array
    {
        try {
            // Make a real API call to Stripe to validate the key
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/account');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return [
                    'success' => false,
                    'message' => 'Network error: ' . $error,
                ];
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode === 200 && $data && isset($data['id'])) {
                // Valid key - return account info
                return [
                    'success' => true,
                    'account_id' => $data['id'],
                    'account_type' => $data['type'] ?? 'standard',
                ];
            } elseif ($httpCode === 401) {
                // Invalid key
                return [
                    'success' => false,
                    'message' => 'Invalid Stripe API keys - authentication failed',
                ];
            } else {
                // Other error
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                return [
                    'success' => false,
                    'message' => 'Stripe API error: ' . $errorMessage,
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
            ];
        }
    }
}
