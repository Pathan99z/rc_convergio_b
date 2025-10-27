<?php

namespace App\Services\Commerce;

use Illuminate\Support\Facades\Log;

class StripeSubscriptionAdapter
{
    private string $secretKey;
    private string $publicKey;
    private string $webhookSecret;
    private int $tenantId;

    public function __construct(int $tenantId, string $secretKey, string $publicKey = '', string $webhookSecret = '')
    {
        $this->tenantId = $tenantId;
        $this->secretKey = $secretKey;
        $this->publicKey = $publicKey ?: '';
        $this->webhookSecret = $webhookSecret ?: '';
    }

    /**
     * Create a Stripe product.
     */
    public function createStripeProduct(array $attrs): string
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/products');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $postData = http_build_query($attrs);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("cURL error: $error");
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception("Stripe API error: $errorMessage");
            }
            
            return $data['id'];
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe product', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'attrs' => $attrs,
            ]);
            throw $e;
        }
    }

    /**
     * Create a Stripe price.
     */
    public function createPrice(string $productId, int $amountCents, string $currency, string $interval, int $intervalCount = 1): string
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/prices');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $postData = http_build_query([
                'product' => $productId,
                'unit_amount' => $amountCents,
                'currency' => $currency,
                'recurring' => json_encode([
                    'interval' => $interval,
                    'interval_count' => $intervalCount,
                ]),
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("cURL error: $error");
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception("Stripe API error: $errorMessage");
            }
            
            return $data['id'];
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe price', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'product_id' => $productId,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'interval' => $interval,
            ]);
            throw $e;
        }
    }

    /**
     * Create a Stripe customer.
     */
    public function createCustomer(array $customerData): string
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/customers');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $postData = http_build_query($customerData);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("cURL error: $error");
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception("Stripe API error: $errorMessage");
            }
            
            return $data['id'];
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe customer', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'customer_data' => $customerData,
            ]);
            throw $e;
        }
    }

    /**
     * Create a checkout session for subscription.
     */
    public function createCheckoutSessionForSubscription(array $params): string
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $postData = http_build_query($params);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("cURL error: $error");
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception("Stripe API error: $errorMessage");
            }
            
            return $data['url'];
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe checkout session', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            throw $e;
        }
    }

    /**
     * Create a billing portal session.
     */
    public function createBillingPortalSession(string $customerId, string $returnUrl): string
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/billing_portal/sessions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $postData = http_build_query([
                'customer' => $customerId,
                'return_url' => $returnUrl,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("cURL error: $error");
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception("Stripe API error: $errorMessage");
            }
            
            return $data['url'];
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe billing portal session', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'customer_id' => $customerId,
                'return_url' => $returnUrl,
            ]);
            throw $e;
        }
    }

    /**
     * Retrieve a Stripe subscription.
     */
    public function retrieveSubscription(string $subscriptionId): array
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/subscriptions/$subscriptionId");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("cURL error: $error");
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception("Stripe API error: $errorMessage");
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve Stripe subscription', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);
            throw $e;
        }
    }

    /**
     * Update a Stripe subscription.
     */
    public function updateSubscription(string $subscriptionId, array $params): array
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/subscriptions/$subscriptionId");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $postData = http_build_query($params);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("cURL error: $error");
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception("Stripe API error: $errorMessage");
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to update Stripe subscription', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
                'params' => $params,
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a Stripe subscription.
     */
    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = true): array
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/subscriptions/$subscriptionId");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $postData = http_build_query([
                'cancel_at_period_end' => $atPeriodEnd,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("cURL error: $error");
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception("Stripe API error: $errorMessage");
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to cancel Stripe subscription', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
                'at_period_end' => $atPeriodEnd,
            ]);
            throw $e;
        }
    }

    /**
     * List invoices for a customer.
     */
    public function listInvoicesForCustomer(string $customerId): array
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/invoices?customer=$customerId");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("cURL error: $error");
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMessage = $data['error']['message'] ?? 'Unknown error';
                throw new \Exception("Stripe API error: $errorMessage");
            }
            
            return $data['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to list Stripe invoices', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return false;
        }

        try {
            // Simple signature verification (in production, use Stripe's official method)
            $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
            return hash_equals($expectedSignature, $signature);
        } catch (\Exception $e) {
            Log::error('Failed to verify webhook signature', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
