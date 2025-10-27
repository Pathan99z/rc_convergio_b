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

class ReconcileSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300; // 5 minutes

    private int $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get Stripe configuration
            $stripeConfig = $this->getStripeConfig($this->tenantId);
            if (!$stripeConfig['configured']) {
                Log::info('Stripe not configured for tenant, skipping reconciliation', [
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

            // Get all subscriptions for this tenant
            $subscriptions = Subscription::where('tenant_id', $this->tenantId)
                ->whereNotNull('stripe_subscription_id')
                ->get();

            $reconciledCount = 0;
            $errorCount = 0;

            foreach ($subscriptions as $subscription) {
                try {
                    $this->reconcileSubscription($stripeAdapter, $subscription);
                    $reconciledCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to reconcile subscription', [
                        'subscription_id' => $subscription->id,
                        'stripe_subscription_id' => $subscription->stripe_subscription_id,
                        'tenant_id' => $this->tenantId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Subscription reconciliation completed', [
                'tenant_id' => $this->tenantId,
                'reconciled_count' => $reconciledCount,
                'error_count' => $errorCount,
                'total_subscriptions' => $subscriptions->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription reconciliation job failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Reconcile a single subscription.
     */
    private function reconcileSubscription(StripeSubscriptionAdapter $stripeAdapter, Subscription $subscription): void
    {
        try {
            // Fetch subscription from Stripe
            $stripeSubscription = $stripeAdapter->retrieveSubscription($subscription->stripe_subscription_id);

            // Compare and update local subscription
            $needsUpdate = false;
            $updates = [];

            if ($subscription->status !== $stripeSubscription['status']) {
                $updates['status'] = $stripeSubscription['status'];
                $needsUpdate = true;
            }

            $currentPeriodStart = $stripeSubscription['current_period_start'] ? 
                \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_start']) : null;
            if ($subscription->current_period_start != $currentPeriodStart) {
                $updates['current_period_start'] = $currentPeriodStart;
                $needsUpdate = true;
            }

            $currentPeriodEnd = $stripeSubscription['current_period_end'] ? 
                \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_end']) : null;
            if ($subscription->current_period_end != $currentPeriodEnd) {
                $updates['current_period_end'] = $currentPeriodEnd;
                $needsUpdate = true;
            }

            if ($subscription->cancel_at_period_end !== $stripeSubscription['cancel_at_period_end']) {
                $updates['cancel_at_period_end'] = $stripeSubscription['cancel_at_period_end'];
                $needsUpdate = true;
            }

            $trialEndsAt = $stripeSubscription['trial_end'] ? 
                \Carbon\Carbon::createFromTimestamp($stripeSubscription['trial_end']) : null;
            if ($subscription->trial_ends_at != $trialEndsAt) {
                $updates['trial_ends_at'] = $trialEndsAt;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $subscription->update($updates);

                Log::info('Subscription reconciled and updated', [
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $subscription->stripe_subscription_id,
                    'updates' => $updates,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to reconcile individual subscription', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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
        Log::error('Subscription reconciliation job permanently failed', [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
