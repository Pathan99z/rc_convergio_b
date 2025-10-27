<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceSetting;
use App\Models\Commerce\Subscription;
use App\Models\Commerce\SubscriptionEvent;
use App\Models\Commerce\SubscriptionInvoice;
use App\Models\Commerce\SubscriptionPlan;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    private ActivityService $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * Create a subscription plan from admin.
     */
    public function createPlanFromAdmin(int $tenantId, array $planData): SubscriptionPlan
    {
        $plan = SubscriptionPlan::create([
            'tenant_id' => $tenantId,
            'team_id' => $planData['team_id'] ?? null,
            'name' => $planData['name'],
            'slug' => $planData['slug'],
            'interval' => $planData['interval'],
            'amount_cents' => $planData['amount_cents'],
            'currency' => $planData['currency'] ?? 'usd',
            'active' => $planData['active'] ?? true,
            'trial_days' => $planData['trial_days'] ?? 0,
            'metadata' => $planData['metadata'] ?? [],
        ]);

        // Create Stripe product and price if Stripe is configured
        $stripeConfig = $this->getStripeConfig($tenantId);
        if ($stripeConfig['configured']) {
            try {
                $stripeAdapter = new StripeSubscriptionAdapter(
                    $tenantId,
                    $stripeConfig['secret_key'],
                    $stripeConfig['public_key'],
                    $stripeConfig['webhook_secret']
                );

                $stripeProductId = $stripeAdapter->createStripeProduct([
                    'name' => $plan->name,
                    'description' => $plan->metadata['description'] ?? '',
                ]);

                $stripePriceId = $stripeAdapter->createPrice(
                    $stripeProductId,
                    $plan->amount_cents,
                    $plan->currency,
                    $plan->interval
                );

                $plan->update([
                    'stripe_product_id' => $stripeProductId,
                    'stripe_price_id' => $stripePriceId,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create Stripe product/price for plan', [
                    'tenant_id' => $tenantId,
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->activityService->log('subscription_plan_created', [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'tenant_id' => $tenantId,
        ]);

        return $plan;
    }

    /**
     * Ensure Stripe customer exists for contact/user.
     */
    public function ensureStripeCustomerForContact(int $tenantId, $contact): string
    {
        $stripeConfig = $this->getStripeConfig($tenantId);
        if (!$stripeConfig['configured']) {
            throw new \Exception('Stripe not configured for tenant');
        }

        $stripeAdapter = new StripeSubscriptionAdapter(
            $tenantId,
            $stripeConfig['secret_key'],
            $stripeConfig['public_key'],
            $stripeConfig['webhook_secret']
        );

        // Check if user already has a Stripe customer ID
        if (isset($contact->stripe_customer_id) && $contact->stripe_customer_id) {
            return $contact->stripe_customer_id;
        }

        // Create new Stripe customer
        $customerData = [
            'email' => $contact->email ?? $contact->contact_email,
            'name' => $contact->name ?? $contact->contact_name,
            'metadata' => json_encode([
                'tenant_id' => $tenantId,
                'contact_id' => $contact->id,
            ]),
        ];

        $stripeCustomerId = $stripeAdapter->createCustomer($customerData);

        // Update contact with Stripe customer ID
        if (method_exists($contact, 'update')) {
            $contact->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        return $stripeCustomerId;
    }

    /**
     * Create subscription from plan.
     */
    public function createSubscriptionFromPlan(int $tenantId, $user, int $planId, int $trialDays = null): array
    {
        $plan = SubscriptionPlan::where('tenant_id', $tenantId)->findOrFail($planId);
        $stripeConfig = $this->getStripeConfig($tenantId);

        // Demo mode: Return demo checkout URL when Stripe is not configured
        if (!$stripeConfig['configured']) {
            $sessionUrl = $this->createDemoCheckoutUrl($plan, $user, $trialDays);
            
            // Send demo subscription email
            $this->sendDemoSubscriptionEmail($plan, $user, $sessionUrl, $trialDays);
            
            return [
                'session_url' => $sessionUrl,
                'plan' => $plan,
                'customer_id' => 'demo_customer_' . time(),
                'demo_mode' => true,
            ];
        }

        $stripeAdapter = new StripeSubscriptionAdapter(
            $tenantId,
            $stripeConfig['secret_key'],
            $stripeConfig['public_key'],
            $stripeConfig['webhook_secret']
        );

        $stripeCustomerId = $this->ensureStripeCustomerForContact($tenantId, $user);

        $checkoutParams = [
            'mode' => 'subscription',
            'customer' => $stripeCustomerId,
            'line_items' => json_encode([[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]]),
            'success_url' => $user->success_url ?? url('/subscription/success'),
            'cancel_url' => $user->cancel_url ?? url('/subscription/cancel'),
            'metadata' => json_encode([
                'tenant_id' => $tenantId,
                'plan_id' => $planId,
                'user_id' => $user->id,
            ]),
        ];

        if ($trialDays || $plan->trial_days > 0) {
            $trialDays = $trialDays ?? $plan->trial_days;
            $checkoutParams['subscription_data'] = json_encode([
                'trial_period_days' => $trialDays,
            ]);
        }

        $sessionUrl = $stripeAdapter->createCheckoutSessionForSubscription($checkoutParams);

        // Send real subscription email
        $this->sendRealSubscriptionEmail($plan, $user, $sessionUrl, $trialDays);

        return [
            'session_url' => $sessionUrl,
            'plan' => $plan,
            'customer_id' => $stripeCustomerId,
            'demo_mode' => false,
        ];
    }

    /**
     * Create demo checkout URL for testing without Stripe.
     */
    private function createDemoCheckoutUrl($plan, $user, int $trialDays = null): string
    {
        $params = [
            'plan_id' => $plan->id,
            'customer_email' => $user->email,
            'customer_name' => $user->name ?? '',
            'amount' => $plan->amount_cents,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
            'trial_days' => $trialDays ?? $plan->trial_days,
            'demo_mode' => 'true',
        ];

        return url('/commerce/subscription-checkout/demo?' . http_build_query($params));
    }

    /**
     * Handle Stripe webhook event.
     */
    public function handleStripeWebhookEvent(int $tenantId, array $event): void
    {
        $eventType = $event['type'] ?? '';
        $eventId = $event['id'] ?? '';

        // Check if event already processed
        $existingEvent = SubscriptionEvent::where('tenant_id', $tenantId)
            ->where('stripe_event_id', $eventId)
            ->first();

        if ($existingEvent && $existingEvent->isProcessed()) {
            Log::info('Stripe webhook event already processed', [
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);
            return;
        }

        // Store event
        $subscriptionEvent = SubscriptionEvent::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'stripe_event_id' => $eventId,
            ],
            [
                'event_type' => $eventType,
                'payload' => $event,
                'subscription_id' => $this->extractSubscriptionId($event),
            ]
        );

        try {
            switch ($eventType) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($tenantId, $event);
                    break;
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($tenantId, $event);
                    break;
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($tenantId, $event);
                    break;
                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($tenantId, $event);
                    break;
                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($tenantId, $event);
                    break;
                case 'invoice.finalized':
                    $this->handleInvoiceFinalized($tenantId, $event);
                    break;
                default:
                    Log::info('Unhandled Stripe webhook event type', [
                        'tenant_id' => $tenantId,
                        'event_type' => $eventType,
                        'event_id' => $eventId,
                    ]);
            }

            $subscriptionEvent->markAsProcessed();
        } catch (\Exception $e) {
            Log::error('Failed to process Stripe webhook event', [
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reconcile Stripe invoice.
     */
    public function reconcileStripeInvoice(int $tenantId, array $invoiceData): SubscriptionInvoice
    {
        $subscriptionId = $this->findSubscriptionByStripeId($tenantId, $invoiceData['subscription'] ?? '');
        
        if (!$subscriptionId) {
            throw new \Exception('Subscription not found for invoice');
        }

        $invoice = SubscriptionInvoice::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'stripe_invoice_id' => $invoiceData['id'],
            ],
            [
                'subscription_id' => $subscriptionId,
                'amount_cents' => $invoiceData['amount_paid'] ?? $invoiceData['amount_due'],
                'currency' => $invoiceData['currency'],
                'status' => $invoiceData['status'],
                'paid_at' => $invoiceData['status_transitions']['paid_at'] ?? null,
                'raw_payload' => $invoiceData,
            ]
        );

        // Create transaction record
        \App\Models\Commerce\CommerceTransaction::create([
            'tenant_id' => $tenantId,
            'subscription_id' => $subscriptionId,
            'amount' => $invoice->amount_dollars,
            'currency' => $invoice->currency,
            'status' => 'completed',
            'provider' => 'stripe',
            'provider_event_id' => $invoiceData['id'],
            'event_type' => 'invoice.payment_succeeded',
            'transaction_type' => 'subscription_payment',
            'metadata' => [
                'invoice_id' => $invoice->id,
                'stripe_invoice_id' => $invoiceData['id'],
            ],
        ]);

        $this->activityService->log('subscription_invoice_paid', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscriptionId,
            'amount' => $invoice->amount_dollars,
            'tenant_id' => $tenantId,
        ]);

        return $invoice;
    }

    /**
     * Cancel subscription locally.
     */
    public function cancelSubscriptionLocal(int $subscriptionId, bool $atPeriodEnd = true): Subscription
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        
        $stripeConfig = $this->getStripeConfig($subscription->tenant_id);
        if ($stripeConfig['configured'] && $subscription->stripe_subscription_id) {
            $stripeAdapter = new StripeSubscriptionAdapter(
                $subscription->tenant_id,
                $stripeConfig['secret_key'],
                $stripeConfig['public_key'],
                $stripeConfig['webhook_secret']
            );

            $stripeAdapter->cancelSubscription($subscription->stripe_subscription_id, $atPeriodEnd);
        }

        $subscription->update([
            'cancel_at_period_end' => $atPeriodEnd,
            'cancel_at' => $atPeriodEnd ? $subscription->current_period_end : now(),
            'status' => $atPeriodEnd ? 'cancel_at_period_end' : 'cancelled',
        ]);

        $this->activityService->log('subscription_cancelled', [
            'subscription_id' => $subscription->id,
            'at_period_end' => $atPeriodEnd,
            'tenant_id' => $subscription->tenant_id,
        ]);

        return $subscription;
    }

    /**
     * Change subscription plan.
     */
    public function changePlan(int $subscriptionId, int $newPlanId, string $prorationBehavior = 'create_prorations'): Subscription
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        $newPlan = SubscriptionPlan::where('tenant_id', $subscription->tenant_id)->findOrFail($newPlanId);

        $stripeConfig = $this->getStripeConfig($subscription->tenant_id);
        if ($stripeConfig['configured'] && $subscription->stripe_subscription_id) {
            $stripeAdapter = new StripeSubscriptionAdapter(
                $subscription->tenant_id,
                $stripeConfig['secret_key'],
                $stripeConfig['public_key'],
                $stripeConfig['webhook_secret']
            );

            $stripeAdapter->updateSubscription($subscription->stripe_subscription_id, [
                'items' => json_encode([[
                    'id' => $subscription->stripe_subscription_id,
                    'price' => $newPlan->stripe_price_id,
                ]]),
                'proration_behavior' => $prorationBehavior,
            ]);
        }

        $subscription->update(['plan_id' => $newPlanId]);

        $this->activityService->log('subscription_plan_changed', [
            'subscription_id' => $subscription->id,
            'old_plan_id' => $subscription->plan_id,
            'new_plan_id' => $newPlanId,
            'tenant_id' => $subscription->tenant_id,
        ]);

        return $subscription;
    }

    /**
     * Get Stripe configuration for tenant.
     */
    private function getStripeConfig(int $tenantId): array
    {
        $settings = CommerceSetting::where('tenant_id', $tenantId)->first();
        
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
     * Extract subscription ID from event.
     */
    private function extractSubscriptionId(array $event): ?int
    {
        $object = $event['data']['object'] ?? [];
        
        if (isset($object['subscription'])) {
            return $this->findSubscriptionByStripeId($event['tenant_id'] ?? 0, $object['subscription']);
        }
        
        if (isset($object['id']) && strpos($object['id'], 'sub_') === 0) {
            return $this->findSubscriptionByStripeId($event['tenant_id'] ?? 0, $object['id']);
        }
        
        return null;
    }

    /**
     * Find subscription by Stripe ID.
     */
    private function findSubscriptionByStripeId(int $tenantId, string $stripeId): ?int
    {
        $subscription = Subscription::where('tenant_id', $tenantId)
            ->where('stripe_subscription_id', $stripeId)
            ->first();
            
        return $subscription ? $subscription->id : null;
    }

    /**
     * Handle checkout session completed.
     */
    private function handleCheckoutSessionCompleted(int $tenantId, array $event): void
    {
        $session = $event['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];
        
        if (!isset($metadata['plan_id']) || !isset($metadata['user_id'])) {
            return;
        }

        // Subscription will be created via customer.subscription.created webhook
        Log::info('Checkout session completed for subscription', [
            'tenant_id' => $tenantId,
            'session_id' => $session['id'],
            'plan_id' => $metadata['plan_id'],
            'user_id' => $metadata['user_id'],
        ]);
    }

    /**
     * Handle subscription updated.
     */
    private function handleSubscriptionUpdated(int $tenantId, array $event): void
    {
        $stripeSubscription = $event['data']['object'] ?? [];
        $stripeId = $stripeSubscription['id'] ?? '';
        
        if (!$stripeId) {
            return;
        }

        $subscription = Subscription::where('tenant_id', $tenantId)
            ->where('stripe_subscription_id', $stripeId)
            ->first();

        if (!$subscription) {
            // Create new subscription
            $metadata = $stripeSubscription['metadata'] ?? [];
            $planId = $metadata['plan_id'] ?? null;
            
            if (!$planId) {
                Log::error('No plan_id in subscription metadata', [
                    'tenant_id' => $tenantId,
                    'stripe_subscription_id' => $stripeId,
                ]);
                return;
            }

            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'user_id' => $metadata['user_id'] ?? null,
                'stripe_customer_id' => $stripeSubscription['customer'],
                'stripe_subscription_id' => $stripeId,
                'plan_id' => $planId,
                'status' => $stripeSubscription['status'],
                'current_period_start' => $stripeSubscription['current_period_start'] ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_start']) : null,
                'current_period_end' => $stripeSubscription['current_period_end'] ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_end']) : null,
                'cancel_at_period_end' => $stripeSubscription['cancel_at_period_end'],
                'trial_ends_at' => $stripeSubscription['trial_end'] ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['trial_end']) : null,
                'metadata' => $metadata,
            ]);
        } else {
            // Update existing subscription
            $subscription->update([
                'status' => $stripeSubscription['status'],
                'current_period_start' => $stripeSubscription['current_period_start'] ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_start']) : null,
                'current_period_end' => $stripeSubscription['current_period_end'] ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['current_period_end']) : null,
                'cancel_at_period_end' => $stripeSubscription['cancel_at_period_end'],
                'trial_ends_at' => $stripeSubscription['trial_end'] ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription['trial_end']) : null,
            ]);
        }

        $this->activityService->log('subscription_updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Handle subscription deleted.
     */
    private function handleSubscriptionDeleted(int $tenantId, array $event): void
    {
        $stripeSubscription = $event['data']['object'] ?? [];
        $stripeId = $stripeSubscription['id'] ?? '';
        
        if (!$stripeId) {
            return;
        }

        $subscription = Subscription::where('tenant_id', $tenantId)
            ->where('stripe_subscription_id', $stripeId)
            ->first();

        if ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'cancel_at' => now(),
            ]);

            $this->activityService->log('subscription_deleted', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    /**
     * Handle invoice payment succeeded.
     */
    private function handleInvoicePaymentSucceeded(int $tenantId, array $event): void
    {
        $invoice = $event['data']['object'] ?? [];
        
        if ($invoice['billing_reason'] === 'subscription_cycle') {
            $this->reconcileStripeInvoice($tenantId, $invoice);
        }
    }

    /**
     * Handle invoice payment failed.
     */
    private function handleInvoicePaymentFailed(int $tenantId, array $event): void
    {
        $invoice = $event['data']['object'] ?? [];
        $subscriptionId = $this->findSubscriptionByStripeId($tenantId, $invoice['subscription'] ?? '');
        
        if ($subscriptionId) {
            Subscription::where('id', $subscriptionId)->update(['status' => 'past_due']);
            
            // Schedule retry job
            \App\Jobs\InvoiceRetryJob::dispatch($tenantId, $subscriptionId, $invoice['id'])
                ->delay(now()->addHours(24));
        }

        $this->activityService->log('subscription_payment_failed', [
            'subscription_id' => $subscriptionId,
            'invoice_id' => $invoice['id'],
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Handle invoice finalized.
     */
    private function handleInvoiceFinalized(int $tenantId, array $event): void
    {
        $invoice = $event['data']['object'] ?? [];
        
        Log::info('Invoice finalized', [
            'tenant_id' => $tenantId,
            'invoice_id' => $invoice['id'],
            'amount' => $invoice['amount_due'],
        ]);
    }

    /**
     * Send demo subscription email.
     */
    private function sendDemoSubscriptionEmail($plan, $user, string $checkoutUrl, int $trialDays = null): void
    {
        try {
            // Get tenant branding
            $branding = \App\Models\TenantBranding::getDefaultBranding($plan->tenant_id);
            
            // Prepare email data
            $emailData = [
                'plan_name' => $plan->name,
                'customer_name' => $user->name ?? 'Valued Customer',
                'customer_email' => $user->email,
                'amount' => $plan->formatted_amount,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'trial_days' => $trialDays ?? $plan->trial_days,
                'checkout_url' => $checkoutUrl,
                'company_name' => $branding->company_name,
                'company_email' => $branding->company_email,
                'company_phone' => $branding->company_phone,
                'company_website' => $branding->company_website,
                'demo_mode' => true,
            ];

            // Send email using Laravel Mail
            \Illuminate\Support\Facades\Mail::send('emails.subscription-demo', $emailData, function ($message) use ($user, $branding) {
                $message->to($user->email, $user->name ?? '')
                       ->subject('Your Subscription Checkout Link - ' . $branding->company_name)
                       ->from($branding->company_email, $branding->company_name);
            });

            Log::info('Demo subscription email sent', [
                'tenant_id' => $plan->tenant_id,
                'customer_email' => $user->email,
                'plan_id' => $plan->id,
                'checkout_url' => $checkoutUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send demo subscription email', [
                'tenant_id' => $plan->tenant_id,
                'customer_email' => $user->email,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send real subscription email.
     */
    private function sendRealSubscriptionEmail($plan, $user, string $checkoutUrl, int $trialDays = null): void
    {
        try {
            // Get tenant branding
            $branding = \App\Models\TenantBranding::getDefaultBranding($plan->tenant_id);
            
            // Prepare email data
            $emailData = [
                'plan_name' => $plan->name,
                'customer_name' => $user->name ?? 'Valued Customer',
                'customer_email' => $user->email,
                'amount' => $plan->formatted_amount,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'trial_days' => $trialDays ?? $plan->trial_days,
                'checkout_url' => $checkoutUrl,
                'company_name' => $branding->company_name,
                'company_email' => $branding->company_email,
                'company_phone' => $branding->company_phone,
                'company_website' => $branding->company_website,
                'demo_mode' => false,
            ];

            // Send email using Laravel Mail
            \Illuminate\Support\Facades\Mail::send('emails.subscription-real', $emailData, function ($message) use ($user, $branding) {
                $message->to($user->email, $user->name ?? '')
                       ->subject('Complete Your Subscription - ' . $branding->company_name)
                       ->from($branding->company_email, $branding->company_name);
            });

            Log::info('Real subscription email sent', [
                'tenant_id' => $plan->tenant_id,
                'customer_email' => $user->email,
                'plan_id' => $plan->id,
                'checkout_url' => $checkoutUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send real subscription email', [
                'tenant_id' => $plan->tenant_id,
                'customer_email' => $user->email,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
