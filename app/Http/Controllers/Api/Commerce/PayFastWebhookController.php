<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\CommerceOrder;
use App\Models\Commerce\CommercePaymentLink;
use App\Models\Commerce\CommerceTransaction;
use App\Models\Commerce\Subscription;
use App\Models\Commerce\SubscriptionInvoice;
use App\Models\Commerce\SubscriptionPlan;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\PayFastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayFastWebhookController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PayFastService $payfastService
    ) {}

    /**
     * Handle PayFast ITN (Instant Transaction Notification).
     */
    public function handle(Request $request)
    {
        $data = $request->all();
        
        Log::channel('commerce')->info('PayFast ITN received', [
            'data' => $data,
        ]);

        // Extract payment ID
        $paymentId = $data['m_payment_id'] ?? null;
        
        if (!$paymentId) {
            Log::channel('commerce')->error('PayFast ITN missing payment ID');
            return response()->json(['error' => 'Missing payment ID'], 400);
        }

        // Check if this is a subscription payment or payment link
        $isSubscription = strpos($paymentId, 'subscription_') === 0;
        
        if ($isSubscription) {
            // Handle subscription payment
            return $this->handleSubscriptionPayment($data, $paymentId);
        }

        // Handle payment link (existing logic)
        $paymentLink = CommercePaymentLink::find($paymentId);
        
        if (!$paymentLink) {
            Log::channel('commerce')->warning('Payment link not found for PayFast ITN', [
                'payment_id' => $paymentId,
            ]);
            return response()->json(['error' => 'Payment link not found'], 404);
        }

        // Initialize PayFastService with tenant-specific configuration
        $payfastService = new PayFastService($paymentLink->tenant_id);

        // Verify ITN signature
        if (!$payfastService->verifyITN($data)) {
            Log::channel('commerce')->error('Invalid PayFast ITN signature', [
                'payment_id' => $paymentId,
                'tenant_id' => $paymentLink->tenant_id,
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $paymentStatus = $data['payment_status'] ?? '';
        $pfPaymentId = $data['pf_payment_id'] ?? $paymentId;

        // Check for duplicate events
        if ($pfPaymentId && CommerceTransaction::where('provider_event_id', $pfPaymentId)->exists()) {
            Log::channel('commerce')->info('Duplicate PayFast ITN event ignored', [
                'pf_payment_id' => $pfPaymentId,
                'payment_status' => $paymentStatus,
            ]);

            return response()->json(['status' => 'duplicate']);
        }

        try {
            DB::beginTransaction();

            // Create transaction record
            $transaction = CommerceTransaction::create([
                'payment_provider' => 'payfast',
                'provider_event_id' => $pfPaymentId,
                'amount' => (float) ($data['amount_gross'] ?? 0),
                'currency' => strtoupper($data['currency'] ?? 'ZAR'),
                'status' => $paymentStatus === 'COMPLETE' ? 'succeeded' : 'failed',
                'event_type' => 'payment.' . strtolower($paymentStatus),
                'raw_payload' => $data,
                'tenant_id' => $paymentLink->tenant_id,
                'team_id' => $paymentLink->team_id,
                'payment_link_id' => $paymentLink->id,
            ]);

            if ($paymentStatus === 'COMPLETE') {
                // Update payment link status
                $paymentLink->update(['status' => 'completed']);

                // Create order from quote
                if ($paymentLink->quote_id) {
                    $quote = $paymentLink->quote;
                    $order = $this->orderService->syncFromQuote($quote);
                    
                    $this->orderService->updateStatus($order->id, 'paid', [
                        'payment_method' => 'payfast',
                        'payment_reference' => $pfPaymentId,
                        'payment_status' => 'paid',
                    ]);

                    $paymentLink->update(['order_id' => $order->id]);
                    $transaction->update(['order_id' => $order->id]);
                }
            } else {
                // Payment failed or cancelled
                $paymentLink->update(['status' => 'cancelled']);
                
                if ($paymentLink->quote_id) {
                    $quote = $paymentLink->quote;
                    $order = $this->orderService->syncFromQuote($quote);
                    
                    $this->orderService->updateStatus($order->id, 'failed', [
                        'payment_method' => 'payfast',
                        'payment_reference' => $pfPaymentId,
                        'payment_status' => 'failed',
                    ]);

                    $paymentLink->update(['order_id' => $order->id]);
                    $transaction->update(['order_id' => $order->id]);
                }
            }

            DB::commit();

            Log::channel('commerce')->info('PayFast ITN processed successfully', [
                'pf_payment_id' => $pfPaymentId,
                'payment_status' => $paymentStatus,
                'transaction_id' => $transaction->id,
                'order_id' => $paymentLink->order_id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'ITN processed successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::channel('commerce')->error('PayFast ITN processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_id' => $paymentId,
                'tenant_id' => $paymentLink->tenant_id ?? null,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle PayFast subscription payment ITN.
     */
    private function handleSubscriptionPayment(array $data, string $paymentId)
    {
        try {
            // Extract plan_id from payment_id format: "subscription_{planId}_{timestamp}"
            $parts = explode('_', $paymentId);
            if (count($parts) < 2) {
                Log::channel('commerce')->error('Invalid subscription payment ID format', [
                    'payment_id' => $paymentId,
                ]);
                return response()->json(['error' => 'Invalid payment ID format'], 400);
            }

            $planId = (int) $parts[1];
            $plan = SubscriptionPlan::find($planId);
            
            if (!$plan) {
                Log::channel('commerce')->error('Plan not found for subscription payment', [
                    'payment_id' => $paymentId,
                    'plan_id' => $planId,
                ]);
                return response()->json(['error' => 'Plan not found'], 404);
            }

            $tenantId = $plan->tenant_id;
            $payfastService = new PayFastService($tenantId);

            // Verify ITN signature
            if (!$payfastService->verifyITN($data)) {
                Log::channel('commerce')->error('Invalid PayFast ITN signature for subscription', [
                    'payment_id' => $paymentId,
                    'tenant_id' => $tenantId,
                ]);
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $paymentStatus = $data['payment_status'] ?? '';
            $pfPaymentId = $data['pf_payment_id'] ?? $paymentId;
            $amount = (float) ($data['amount_gross'] ?? 0);
            $currency = strtoupper($data['currency'] ?? $plan->currency ?? 'ZAR');
            $customerEmail = $data['email_address'] ?? '';

            // Check for duplicate events
            if ($pfPaymentId && CommerceTransaction::where('provider_event_id', $pfPaymentId)->exists()) {
                Log::channel('commerce')->info('Duplicate PayFast subscription ITN event ignored', [
                    'pf_payment_id' => $pfPaymentId,
                    'payment_status' => $paymentStatus,
                ]);
                return response()->json(['status' => 'duplicate']);
            }

            DB::beginTransaction();

            // Create transaction record
            $transaction = CommerceTransaction::create([
                'payment_provider' => 'payfast',
                'provider_event_id' => $pfPaymentId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $paymentStatus === 'COMPLETE' ? 'succeeded' : 'failed',
                'event_type' => 'subscription.payment.' . strtolower($paymentStatus),
                'raw_payload' => $data,
                'tenant_id' => $tenantId,
                'team_id' => $plan->team_id,
            ]);

            if ($paymentStatus === 'COMPLETE') {
                // Find or create subscription
                $subscription = $this->findOrCreateSubscription($tenantId, $planId, $customerEmail, $pfPaymentId);

                // Update subscription periods
                $this->updateSubscriptionPeriods($subscription, $plan);

                // Create invoice
                $invoice = $this->createSubscriptionInvoice($subscription, $amount, $currency, $pfPaymentId, $data);

                // Link transaction to subscription and invoice
                $transaction->update([
                    'subscription_id' => $subscription->id,
                ]);

                DB::commit();

                Log::channel('commerce')->info('PayFast subscription payment processed successfully', [
                    'pf_payment_id' => $pfPaymentId,
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'currency' => $currency,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Subscription payment processed successfully',
                ], 200);
            } else {
                // Payment failed
                $subscription = Subscription::where('tenant_id', $tenantId)
                    ->where('plan_id', $planId)
                    ->where('metadata->payfast_payment_id', $pfPaymentId)
                    ->first();

                if ($subscription) {
                    $subscription->update(['status' => 'past_due']);
                }

                $transaction->update([
                    'subscription_id' => $subscription->id ?? null,
                ]);

                DB::commit();

                Log::channel('commerce')->warning('PayFast subscription payment failed', [
                    'pf_payment_id' => $pfPaymentId,
                    'payment_status' => $paymentStatus,
                ]);

                return response()->json([
                    'status' => 'failed',
                    'message' => 'Subscription payment failed',
                ], 200);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::channel('commerce')->error('PayFast subscription payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_id' => $paymentId,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Find or create subscription record.
     */
    private function findOrCreateSubscription(int $tenantId, int $planId, string $customerEmail, string $pfPaymentId): Subscription
    {
        // Try to find existing subscription by PayFast payment ID in metadata
        $subscription = Subscription::where('tenant_id', $tenantId)
            ->where('plan_id', $planId)
            ->whereJsonContains('metadata->payfast_payment_id', $pfPaymentId)
            ->first();

        if ($subscription) {
            return $subscription;
        }

        // Try to find by customer email in metadata
        if (!empty($customerEmail)) {
            $subscription = Subscription::where('tenant_id', $tenantId)
                ->where('plan_id', $planId)
                ->whereJsonContains('metadata->customer_email', $customerEmail)
                ->first();

            if ($subscription) {
                // Update metadata with PayFast payment ID
                $metadata = $subscription->metadata ?? [];
                $metadata['payfast_payment_id'] = $pfPaymentId;
                if (empty($metadata['customer_email'])) {
                    $metadata['customer_email'] = $customerEmail;
                }
                $subscription->update(['metadata' => $metadata]);
                return $subscription;
            }
        }

        // Create new subscription
        $plan = SubscriptionPlan::findOrFail($planId);
        $now = Carbon::now();
        
        // Calculate period end based on interval
        $periodEnd = match($plan->interval) {
            'monthly' => $now->copy()->addMonth(),
            'yearly' => $now->copy()->addYear(),
            'weekly' => $now->copy()->addWeek(),
            default => $now->copy()->addMonth(),
        };

        $subscription = Subscription::create([
            'tenant_id' => $tenantId,
            'team_id' => $plan->team_id,
            'plan_id' => $planId,
            'status' => 'active',
            'current_period_start' => $now,
            'current_period_end' => $periodEnd,
            'metadata' => [
                'payfast_payment_id' => $pfPaymentId,
                'customer_email' => $customerEmail,
            ],
        ]);

        return $subscription;
    }

    /**
     * Update subscription periods after payment.
     */
    private function updateSubscriptionPeriods(Subscription $subscription, SubscriptionPlan $plan): void
    {
        $now = Carbon::now();
        
        // Calculate next period end based on interval
        $periodEnd = match($plan->interval) {
            'monthly' => $now->copy()->addMonth(),
            'yearly' => $now->copy()->addYear(),
            'weekly' => $now->copy()->addWeek(),
            default => $now->copy()->addMonth(),
        };

        $subscription->update([
            'status' => 'active',
            'current_period_start' => $now,
            'current_period_end' => $periodEnd,
        ]);
    }

    /**
     * Create subscription invoice.
     */
    private function createSubscriptionInvoice(Subscription $subscription, float $amount, string $currency, string $pfPaymentId, array $rawData): SubscriptionInvoice
    {
        // Convert amount to cents
        $amountCents = (int) ($amount * 100);

        $invoice = SubscriptionInvoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => 'payfast_' . $pfPaymentId, // Use PayFast payment ID as invoice ID
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'status' => 'paid',
            'paid_at' => Carbon::now(),
            'raw_payload' => $rawData,
        ]);

        return $invoice;
    }
}

