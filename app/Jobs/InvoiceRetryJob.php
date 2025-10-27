<?php

namespace App\Jobs;

use App\Models\Commerce\Subscription;
use App\Services\Commerce\StripeSubscriptionAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InvoiceRetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300; // 5 minutes

    private int $tenantId;
    private int $subscriptionId;
    private string $stripeInvoiceId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tenantId, int $subscriptionId, string $stripeInvoiceId)
    {
        $this->tenantId = $tenantId;
        $this->subscriptionId = $subscriptionId;
        $this->stripeInvoiceId = $stripeInvoiceId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $subscription = Subscription::where('tenant_id', $this->tenantId)
                ->findOrFail($this->subscriptionId);

            if (!$subscription->stripe_customer_id) {
                Log::error('No Stripe customer ID for subscription', [
                    'subscription_id' => $this->subscriptionId,
                    'tenant_id' => $this->tenantId,
                ]);
                return;
            }

            // Get Stripe configuration
            $stripeConfig = $this->getStripeConfig($this->tenantId);
            if (!$stripeConfig['configured']) {
                Log::error('Stripe not configured for tenant', [
                    'tenant_id' => $this->tenantId,
                ]);
                return;
            }

            $stripeAdapter = new StripeSubscriptionAdapter(
                $this->tenantId,
                $stripeConfig['secret_key'],
                $stripeConfig['public_key'],
                $stripeConfig['webhook_secret']
            );

            // Retry the invoice payment
            $this->retryInvoicePayment($stripeAdapter, $subscription);

            Log::info('Invoice retry job completed', [
                'subscription_id' => $this->subscriptionId,
                'stripe_invoice_id' => $this->stripeInvoiceId,
                'tenant_id' => $this->tenantId,
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice retry job failed', [
                'subscription_id' => $this->subscriptionId,
                'stripe_invoice_id' => $this->stripeInvoiceId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                // Mark subscription as past_due after max retries
                $subscription = Subscription::where('tenant_id', $this->tenantId)
                    ->find($this->subscriptionId);
                
                if ($subscription) {
                    $subscription->update(['status' => 'past_due']);
                }
            }

            throw $e;
        }
    }

    /**
     * Retry invoice payment.
     */
    private function retryInvoicePayment(StripeSubscriptionAdapter $stripeAdapter, Subscription $subscription): void
    {
        // In a real implementation, you would:
        // 1. Send payment reminder email to customer
        // 2. Attempt to charge the customer's default payment method
        // 3. Update subscription status based on result
        
        Log::info('Retrying invoice payment', [
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => $this->stripeInvoiceId,
            'customer_id' => $subscription->stripe_customer_id,
        ]);

        // For now, just log the retry attempt
        // In production, implement actual payment retry logic
    }

    /**
     * Get Stripe configuration for tenant.
     */
    private function getStripeConfig(int $tenantId): array
    {
        $settings = \App\Models\Commerce\CommerceSetting::where('tenant_id', $tenantId)->first();
        
        if (!$settings) {
            return [
                'configured' => false,
                'secret_key' => '',
                'public_key' => '',
                'webhook_secret' => '',
            ];
        }

        return [
            'configured' => !empty($settings->stripe_secret_key),
            'secret_key' => $settings->stripe_secret_key ?: '',
            'public_key' => $settings->stripe_public_key ?: '',
            'webhook_secret' => $settings->stripe_webhook_secret ?: '',
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Invoice retry job permanently failed', [
            'subscription_id' => $this->subscriptionId,
            'stripe_invoice_id' => $this->stripeInvoiceId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
