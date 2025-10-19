<?php

namespace App\Http\Controllers;

use App\Models\Commerce\CommercePaymentLink;
use App\Models\Commerce\CommerceOrder;
use App\Models\Commerce\SubscriptionInvoice;
use App\Models\TenantBranding;
use App\Models\Quote;
use App\Services\Commerce\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PublicCommerceController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * Display the public checkout page for a payment link.
     */
    public function checkout(Request $request, int $paymentLinkId)
    {
        try {
            // Find the payment link
            $paymentLink = CommercePaymentLink::with(['quote.contact', 'quote.deal', 'order'])
                ->findOrFail($paymentLinkId);

            // Check if payment link is active and not expired
            if ($paymentLink->status !== 'active') {
                return $this->renderCheckoutError('Payment link is not active.');
            }

            if ($paymentLink->expires_at && $paymentLink->expires_at->isPast()) {
                return $this->renderCheckoutError('Payment link has expired.');
            }

            // Get quote or order data
            $quote = $paymentLink->quote;
            $order = $paymentLink->order;

            if (!$quote && !$order) {
                return $this->renderCheckoutError('No quote or order found for this payment link.');
            }

            // Prepare checkout data
            $checkoutData = [
                'payment_link' => $paymentLink,
                'quote' => $quote,
                'order' => $order,
                'items' => $quote ? $quote->items : ($order ? $order->items : []),
                'total_amount' => $quote ? $quote->total_amount : $order->total_amount,
                'currency' => $quote ? $quote->currency : $order->currency,
                'contact' => $quote ? $quote->contact : $order->contact,
            ];

            // Render the checkout page
            return $this->renderCheckoutPage($checkoutData);

        } catch (\Exception $e) {
            Log::error('Public checkout page error', [
                'payment_link_id' => $paymentLinkId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->renderCheckoutError('An error occurred while loading the checkout page.');
        }
    }

    /**
     * Process the payment (for test mode).
     */
    public function processPayment(Request $request, int $paymentLinkId)
    {
        try {
            $paymentLink = CommercePaymentLink::findOrFail($paymentLinkId);

            // Validate payment link
            if ($paymentLink->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment link is not active.',
                ], 400);
            }

            if ($paymentLink->expires_at && $paymentLink->expires_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment link has expired.',
                ], 400);
            }

            // For test mode, simulate payment processing
            if (str_contains($paymentLink->stripe_session_id, 'test_session_')) {
                // Mark payment link as completed
                $paymentLink->update(['status' => 'completed']);

                // Create or update order
                if ($paymentLink->quote_id) {
                    $quote = $paymentLink->quote;
                    $order = CommerceOrder::create([
                        'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(uniqid()),
                        'quote_id' => $quote->id,
                        'deal_id' => $quote->deal_id,
                        'contact_id' => $quote->contact_id,
                        'total_amount' => $quote->total_amount,
                        'currency' => $quote->currency,
                        'status' => 'paid',
                        'payment_method' => 'stripe',
                        'payment_reference' => $paymentLink->stripe_session_id,
                        'tenant_id' => $quote->tenant_id,
                        'team_id' => $quote->team_id,
                        'owner_id' => $quote->owner_id,
                    ]);

                    // Copy quote items to order
                    foreach ($quote->items as $quoteItem) {
                        $order->items()->create([
                            'product_id' => $quoteItem->product_id,
                            'name' => $quoteItem->name,
                            'description' => $quoteItem->description,
                            'quantity' => $quoteItem->quantity,
                            'unit_price' => $quoteItem->unit_price,
                            'discount' => $quoteItem->discount,
                            'tax_rate' => $quoteItem->tax_rate,
                            'subtotal' => $quoteItem->subtotal,
                        ]);
                    }

                    // Update payment link with order reference
                    $paymentLink->update(['order_id' => $order->id]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully!',
                    'order_number' => $order->order_number ?? null,
                ]);
            }

            // For real Stripe payments, redirect to Stripe Checkout
            if ($paymentLink->public_url) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $paymentLink->public_url,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment processing not available.',
            ], 400);

        } catch (\Exception $e) {
            Log::error('Payment processing error', [
                'payment_link_id' => $paymentLinkId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing payment.',
            ], 500);
        }
    }

    /**
     * Render the checkout page HTML.
     */
    private function renderCheckoutPage(array $data): Response
    {
        $html = $this->generateCheckoutHTML($data);
        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Render an error page.
     */
    private function renderCheckoutError(string $message): Response
    {
        $html = $this->generateErrorHTML($message);
        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Generate the checkout page HTML.
     */
    private function generateCheckoutHTML(array $data): string
    {
        $paymentLink = $data['payment_link'];
        $quote = $data['quote'];
        $order = $data['order'];
        $items = $data['items'];
        $totalAmount = $data['total_amount'];
        $currency = $data['currency'];
        $contact = $data['contact'];

        $isTestMode = str_contains($paymentLink->stripe_session_id, 'test_session_');

        return "
        <!DOCTYPE html>
        <html lang=\"en\">
        <head>
            <meta charset=\"UTF-8\">
            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
            <title>Payment Checkout - RC Convergio</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 30px; margin-bottom: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #3b82f6; margin-bottom: 10px; }
                .title { font-size: 28px; color: #1f2937; margin-bottom: 10px; }
                .subtitle { color: #6b7280; font-size: 16px; }
                .item { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #e5e7eb; }
                .item:last-child { border-bottom: none; }
                .item-name { font-weight: 500; color: #1f2937; }
                .item-price { color: #6b7280; }
                .total { display: flex; justify-content: space-between; padding: 20px 0; border-top: 2px solid #e5e7eb; font-size: 18px; font-weight: bold; color: #1f2937; }
                .payment-section { text-align: center; margin-top: 30px; }
                .pay-button { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; border: none; padding: 15px 40px; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
                .pay-button:hover { transform: translateY(-2px); }
                .pay-button:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
                .test-mode { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
                .loading { display: none; }
                .success { display: none; background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 15px; border-radius: 6px; margin-top: 20px; }
                .error { display: none; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 15px; border-radius: 6px; margin-top: 20px; }
                .contact-info { background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .contact-info h3 { color: #1f2937; margin-bottom: 10px; }
                .contact-info p { color: #6b7280; margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"card\">
                    <div class=\"header\">
                        <div class=\"logo\">RC Convergio</div>
                        <h1 class=\"title\">Payment Checkout</h1>
                        <p class=\"subtitle\">Complete your payment securely</p>
                    </div>

                    " . ($isTestMode ? '<div class="test-mode">🧪 TEST MODE - This is a demonstration payment</div>' : '') . "

                    " . ($contact ? "
                    <div class=\"contact-info\">
                        <h3>Bill To:</h3>
                        <p><strong>{$contact->first_name} {$contact->last_name}</strong></p>
                        <p>{$contact->email}</p>
                        " . ($contact->phone ? "<p>{$contact->phone}</p>" : "") . "
                    </div>
                    " : "") . "

                    <div class=\"items-section\">
                        <h3 style=\"margin-bottom: 15px; color: #1f2937;\">Order Items:</h3>
                        " . $this->generateItemsHTML($items) . "
                        <div class=\"total\">
                            <span>Total Amount:</span>
                            <span>{$currency} " . number_format($totalAmount, 2) . "</span>
                        </div>
                    </div>

                    <div class=\"payment-section\">
                        <button class=\"pay-button\" onclick=\"processPayment()\" id=\"payButton\">
                            " . ($isTestMode ? '🧪 Process Test Payment' : '💳 Pay with Stripe') . "
                        </button>
                        <div class=\"loading\" id=\"loading\">Processing payment...</div>
                        <div class=\"success\" id=\"success\"></div>
                        <div class=\"error\" id=\"error\"></div>
                    </div>
                </div>
            </div>

            <script>
                async function processPayment() {
                    const payButton = document.getElementById('payButton');
                    const loading = document.getElementById('loading');
                    const success = document.getElementById('success');
                    const error = document.getElementById('error');

                    payButton.disabled = true;
                    loading.style.display = 'block';
                    success.style.display = 'none';
                    error.style.display = 'none';

                    try {
                        const response = await fetch('/commerce/checkout/{$paymentLink->id}/process', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''
                            }
                        });

                        const data = await response.json();

                        if (data.success) {
                            if (data.redirect_url) {
                                window.location.href = data.redirect_url;
                            } else {
                                success.innerHTML = '✅ ' + data.message + (data.order_number ? '<br><strong>Order Number: ' + data.order_number + '</strong>' : '');
                                success.style.display = 'block';
                                payButton.style.display = 'none';
                            }
                        } else {
                            error.innerHTML = '❌ ' + data.message;
                            error.style.display = 'block';
                            payButton.disabled = false;
                        }
                    } catch (err) {
                        error.innerHTML = '❌ An error occurred while processing payment.';
                        error.style.display = 'block';
                        payButton.disabled = false;
                    }

                    loading.style.display = 'none';
                }
            </script>
        </body>
        </html>";
    }

    /**
     * Generate items HTML.
     */
    private function generateItemsHTML($items): string
    {
        $html = '';
        foreach ($items as $item) {
            $html .= "
            <div class=\"item\">
                <div class=\"item-name\">{$item->name}</div>
                <div class=\"item-price\">{$item->currency} " . number_format($item->subtotal, 2) . "</div>
            </div>";
        }
        return $html;
    }

    /**
     * Generate error page HTML.
     */
    private function generateErrorHTML(string $message): string
    {
        return "
        <!DOCTYPE html>
        <html lang=\"en\">
        <head>
            <meta charset=\"UTF-8\">
            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
            <title>Payment Error - RC Convergio</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
                .error-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 40px; text-align: center; max-width: 500px; }
                .error-icon { font-size: 48px; margin-bottom: 20px; }
                .error-title { font-size: 24px; color: #1f2937; margin-bottom: 15px; }
                .error-message { color: #6b7280; font-size: 16px; margin-bottom: 30px; }
                .logo { font-size: 20px; font-weight: bold; color: #3b82f6; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class=\"error-card\">
                <div class=\"logo\">RC Convergio</div>
                <div class=\"error-icon\">❌</div>
                <h1 class=\"error-title\">Payment Error</h1>
                <p class=\"error-message\">{$message}</p>
                <p style=\"color: #9ca3af; font-size: 14px;\">Please contact support if you continue to experience issues.</p>
            </div>
        </body>
        </html>";
    }

    /**
     * Demo subscription checkout page.
     */
    public function subscriptionCheckoutDemo(Request $request)
    {
        $planId = $request->input('plan_id');
        $customerEmail = $request->input('customer_email');
        $customerName = $request->input('customer_name');
        $amount = $request->input('amount', 0);
        $currency = $request->input('currency', 'usd');
        $interval = $request->input('interval', 'monthly');
        $trialDays = $request->input('trial_days', 0);

        if (!$planId || !$customerEmail) {
            return $this->renderCheckoutError('Invalid subscription checkout parameters');
        }

        $formattedAmount = number_format($amount / 100, 2);
        $intervalText = ucfirst($interval);

        return "
        <!DOCTYPE html>
        <html lang=\"en\">
        <head>
            <meta charset=\"UTF-8\">
            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
            <title>Subscription Checkout - RC Convergio</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 30px; margin-bottom: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #3b82f6; margin-bottom: 10px; }
                .title { font-size: 28px; color: #1f2937; margin-bottom: 10px; }
                .subtitle { color: #6b7280; font-size: 16px; }
                .plan-details { background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .plan-name { font-size: 20px; font-weight: bold; color: #1f2937; margin-bottom: 10px; }
                .plan-price { font-size: 24px; color: #3b82f6; font-weight: bold; margin-bottom: 5px; }
                .plan-interval { color: #6b7280; }
                .trial-info { background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
                .demo-mode { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: 600; }
                .customer-info { background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .customer-info h3 { color: #1f2937; margin-bottom: 10px; }
                .customer-info p { color: #6b7280; margin: 5px 0; }
                .payment-section { text-align: center; margin-top: 30px; }
                .subscribe-button { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; border: none; padding: 15px 40px; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
                .subscribe-button:hover { transform: translateY(-2px); }
                .subscribe-button:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
                .loading { display: none; }
                .success { display: none; background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 15px; border-radius: 6px; margin-top: 20px; }
                .error { display: none; background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 15px; border-radius: 6px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"card\">
                    <div class=\"header\">
                        <div class=\"logo\">RC Convergio</div>
                        <h1 class=\"title\">Subscription Checkout</h1>
                        <p class=\"subtitle\">Start your subscription today</p>
                    </div>

                    <div class=\"demo-mode\">
                        🧪 DEMO MODE - This is a demonstration subscription checkout
                    </div>

                    <div class=\"plan-details\">
                        <div class=\"plan-name\">Subscription Plan</div>
                        <div class=\"plan-price\">\${$formattedAmount} {$currency}</div>
                        <div class=\"plan-interval\">per {$intervalText}</div>
                    </div>

                    " . ($trialDays > 0 ? "<div class=\"trial-info\">🎉 {$trialDays} days free trial included!</div>" : "") . "

                    <div class=\"customer-info\">
                        <h3>Customer Information:</h3>
                        <p><strong>Name:</strong> {$customerName}</p>
                        <p><strong>Email:</strong> {$customerEmail}</p>
                    </div>

                    <div class=\"payment-section\">
                        <button class=\"subscribe-button\" onclick=\"processSubscription()\">
                            Start Subscription
                        </button>
                        <div class=\"loading\" id=\"loading\">Processing subscription...</div>
                        <div class=\"success\" id=\"success\">
                            ✅ Subscription created successfully! You will be redirected shortly.
                        </div>
                        <div class=\"error\" id=\"error\"></div>
                    </div>
                </div>
            </div>

            <script>
                function processSubscription() {
                    const button = document.querySelector('.subscribe-button');
                    const loading = document.getElementById('loading');
                    const success = document.getElementById('success');
                    const error = document.getElementById('error');

                    button.disabled = true;
                    loading.style.display = 'block';
                    error.style.display = 'none';

                    fetch('/commerce/subscription-checkout/demo/process', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            plan_id: {$planId},
                            customer_email: '{$customerEmail}',
                            customer_name: '{$customerName}',
                            amount: {$amount},
                            currency: '{$currency}',
                            interval: '{$interval}',
                            trial_days: {$trialDays}
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        loading.style.display = 'none';
                        if (data.success) {
                            success.style.display = 'block';
                            success.innerHTML = 'Subscription created successfully! Redirecting to management portal...';
                            setTimeout(() => {
                                window.location.href = '/commerce/subscription-portal/demo/' + data.data.subscription_id;
                            }, 3000);
                        } else {
                            error.textContent = data.message || 'Failed to create subscription';
                            error.style.display = 'block';
                            button.disabled = false;
                        }
                    })
                    .catch(err => {
                        loading.style.display = 'none';
                        error.textContent = 'Network error. Please try again.';
                        error.style.display = 'block';
                        button.disabled = false;
                    });
                }
            </script>
        </body>
        </html>";
    }

    /**
     * Process demo subscription payment.
     */
    public function processSubscriptionDemo(Request $request)
    {
        try {
            $planId = $request->input('plan_id');
            $customerEmail = $request->input('customer_email');
            $customerName = $request->input('customer_name');
            $amount = $request->input('amount', 0);
            $currency = $request->input('currency', 'usd');
            $interval = $request->input('interval', 'monthly');
            $trialDays = $request->input('trial_days', 0);

            // Create actual demo subscription record
            $subscription = $this->createDemoSubscription($planId, $customerEmail, $customerName, $amount, $currency, $interval, $trialDays);
            
            return response()->json([
                'success' => true,
                'message' => 'Demo subscription created successfully',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'customer_email' => $customerEmail,
                    'plan_id' => $planId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'interval' => $interval,
                    'trial_days' => $trialDays,
                    'demo_mode' => true,
                    'subscription' => $subscription,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create demo subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a demo subscription record.
     */
    private function createDemoSubscription($planId, $customerEmail, $customerName, $amount, $currency, $interval, $trialDays)
    {
        // Get the plan
        $plan = \App\Models\Commerce\SubscriptionPlan::findOrFail($planId);
        
        // Create or find a demo user
        $user = \App\Models\User::firstOrCreate(
            ['email' => $customerEmail],
            [
                'name' => $customerName,
                'tenant_id' => $plan->tenant_id,
                'password' => bcrypt('demo_password'),
                'email_verified_at' => now(),
            ]
        );

        // Calculate trial and billing dates
        $now = now();
        $trialEndsAt = $trialDays > 0 ? $now->copy()->addDays($trialDays) : null;
        $currentPeriodStart = $trialEndsAt ? $trialEndsAt : $now;
        $currentPeriodEnd = $currentPeriodStart->copy()->addMonths($interval === 'yearly' ? 12 : 1);

        // Create the subscription
        $subscription = \App\Models\Commerce\Subscription::create([
            'tenant_id' => $plan->tenant_id,
            'team_id' => $plan->team_id,
            'user_id' => $user->id,
            'customer_id' => 'demo_customer_' . time(),
            'stripe_customer_id' => 'cus_demo_' . time(),
            'stripe_subscription_id' => 'sub_demo_' . time(),
            'plan_id' => $planId,
            'status' => $trialDays > 0 ? 'trialing' : 'active',
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'cancel_at_period_end' => false,
            'trial_ends_at' => $trialEndsAt,
            'metadata' => [
                'demo_mode' => true,
                'created_via' => 'demo_checkout',
                'customer_name' => $customerName,
            ],
        ]);

        // Create demo invoice for the first payment
        $this->createDemoInvoice($subscription, $amount, $currency);

        return $subscription;
    }

    /**
     * Create a demo invoice for the subscription.
     */
    private function createDemoInvoice($subscription, $amount, $currency)
    {
        \App\Models\Commerce\SubscriptionInvoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => 'in_demo_' . time(),
            'amount_cents' => $amount,
            'currency' => $currency,
            'status' => 'paid',
            'paid_at' => now(),
            'raw_payload' => [
                'demo_mode' => true,
                'amount_paid' => $amount,
                'currency' => $currency,
            ],
        ]);

        // Create transaction record
        \App\Models\Commerce\CommerceTransaction::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'amount' => $amount / 100,
            'currency' => $currency,
            'status' => 'completed',
            'payment_provider' => 'demo',
            'provider' => 'demo',
            'provider_event_id' => 'evt_demo_' . time(),
            'event_type' => 'invoice.payment_succeeded',
            'transaction_type' => 'subscription_payment',
            'metadata' => json_encode([
                'demo_mode' => true,
                'subscription_id' => $subscription->id,
            ]),
        ]);
    }

    /**
     * Demo billing portal for subscription management.
     */
    public function demoBillingPortal(Request $request, int $subscriptionId)
    {
        $subscription = \App\Models\Commerce\Subscription::with(['plan', 'user', 'invoices'])
            ->findOrFail($subscriptionId);

        if (!$subscription->metadata || !($subscription->metadata['demo_mode'] ?? false)) {
            return $this->renderCheckoutError('This is not a demo subscription');
        }

        $availablePlans = \App\Models\Commerce\SubscriptionPlan::where('tenant_id', $subscription->tenant_id)
            ->where('active', true)
            ->where('id', '!=', $subscription->plan_id)
            ->get();

        return "
        <!DOCTYPE html>
        <html lang=\"en\">
        <head>
            <meta charset=\"UTF-8\">
            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
            <title>Subscription Management - RC Convergio</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
                .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
                .header { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 30px; margin-bottom: 20px; }
                .logo { font-size: 24px; font-weight: bold; color: #3b82f6; margin-bottom: 10px; }
                .title { font-size: 28px; color: #1f2937; margin-bottom: 10px; }
                .subtitle { color: #6b7280; font-size: 16px; }
                .demo-badge { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; margin-bottom: 20px; }
                .subscription-info { background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
                .info-row:last-child { border-bottom: none; }
                .info-label { font-weight: 500; color: #1f2937; }
                .info-value { color: #6b7280; }
                .status-active { color: #10b981; font-weight: 600; }
                .status-trialing { color: #3b82f6; font-weight: 600; }
                .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
                .action-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; }
                .action-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 10px; }
                .action-description { color: #6b7280; margin-bottom: 15px; }
                .btn { padding: 10px 20px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; }
                .btn-primary { background: #3b82f6; color: white; }
                .btn-primary:hover { background: #2563eb; }
                .btn-danger { background: #ef4444; color: white; }
                .btn-danger:hover { background: #dc2626; }
                .btn:disabled { opacity: 0.6; cursor: not-allowed; }
                .invoices { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; }
                .invoice-item { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #e5e7eb; }
                .invoice-item:last-child { border-bottom: none; }
                .invoice-date { color: #6b7280; }
                .invoice-amount { font-weight: 600; color: #1f2937; }
                .invoice-status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
                .status-paid { background: #d1fae5; color: #065f46; }
                .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
                .modal-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; padding: 30px; max-width: 500px; width: 90%; }
                .modal-title { font-size: 20px; font-weight: 600; margin-bottom: 15px; }
                .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
                .form-group { margin-bottom: 15px; }
                .form-label { display: block; margin-bottom: 5px; font-weight: 500; }
                .form-select { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"header\">
                    <div class=\"logo\">RC Convergio</div>
                    <div class=\"demo-badge\">🧪 DEMO MODE - Subscription Management Portal</div>
                    <h1 class=\"title\">Subscription Management</h1>
                    <p class=\"subtitle\">Manage your subscription, billing, and account settings</p>
                </div>

                <div class=\"subscription-info\">
                    <div class=\"info-row\">
                        <span class=\"info-label\">Plan:</span>
                        <span class=\"info-value\">{$subscription->plan->name}</span>
                    </div>
                    <div class=\"info-row\">
                        <span class=\"info-label\">Status:</span>
                        <span class=\"info-value status-" . ($subscription->status === 'active' ? 'active' : 'trialing') . "\">" . ucfirst($subscription->status) . "</span>
                    </div>
                    <div class=\"info-row\">
                        <span class=\"info-label\">Amount:</span>
                        <span class=\"info-value\">\${$subscription->plan->formatted_amount} {$subscription->plan->currency} / {$subscription->plan->interval}</span>
                    </div>
                    <div class=\"info-row\">
                        <span class=\"info-label\">Next Billing:</span>
                        <span class=\"info-value\">{$subscription->current_period_end->format('M d, Y')}</span>
                    </div>
                    " . ($subscription->trial_ends_at ? "
                    <div class=\"info-row\">
                        <span class=\"info-label\">Trial Ends:</span>
                        <span class=\"info-value\">{$subscription->trial_ends_at->format('M d, Y')}</span>
                    </div>
                    " : "") . "
                </div>

                <div class=\"actions\">
                    <div class=\"action-card\">
                        <h3 class=\"action-title\">Change Plan</h3>
                        <p class=\"action-description\">Upgrade or downgrade your subscription plan</p>
                        <button class=\"btn btn-primary\" onclick=\"showChangePlanModal()\">Change Plan</button>
                    </div>
                    <div class=\"action-card\">
                        <h3 class=\"action-title\">Cancel Subscription</h3>
                        <p class=\"action-description\">Cancel your subscription at the end of the current period</p>
                        <button class=\"btn btn-danger\" onclick=\"showCancelModal()\">Cancel Subscription</button>
                    </div>
                </div>

                <div class=\"invoices\">
                    <h3 style=\"margin-bottom: 15px;\">Billing History</h3>
                    " . $this->renderDemoInvoices($subscription) . "
                </div>
            </div>

            <!-- Change Plan Modal -->
            <div id=\"changePlanModal\" class=\"modal\">
                <div class=\"modal-content\">
                    <h3 class=\"modal-title\">Change Subscription Plan</h3>
                    <form id=\"changePlanForm\">
                        <div class=\"form-group\">
                            <label class=\"form-label\">Select New Plan:</label>
                            <select class=\"form-select\" name=\"new_plan_id\" required>
                                <option value=\"\">Choose a plan...</option>
                                " . $this->renderPlanOptions($availablePlans) . "
                            </select>
                        </div>
                        <div class=\"modal-actions\">
                            <button type=\"button\" class=\"btn\" onclick=\"hideChangePlanModal()\">Cancel</button>
                            <button type=\"submit\" class=\"btn btn-primary\">Change Plan</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cancel Modal -->
            <div id=\"cancelModal\" class=\"modal\">
                <div class=\"modal-content\">
                    <h3 class=\"modal-title\">Cancel Subscription</h3>
                    <p>Are you sure you want to cancel your subscription? You will continue to have access until {$subscription->current_period_end->format('M d, Y')}.</p>
                    <div class=\"modal-actions\">
                        <button type=\"button\" class=\"btn\" onclick=\"hideCancelModal()\">Keep Subscription</button>
                        <button type=\"button\" class=\"btn btn-danger\" onclick=\"cancelSubscription()\">Cancel Subscription</button>
                    </div>
                </div>
            </div>

            <script>
                function showChangePlanModal() {
                    document.getElementById('changePlanModal').style.display = 'block';
                }

                function hideChangePlanModal() {
                    document.getElementById('changePlanModal').style.display = 'none';
                }

                function showCancelModal() {
                    document.getElementById('cancelModal').style.display = 'block';
                }

                function hideCancelModal() {
                    document.getElementById('cancelModal').style.display = 'none';
                }

                document.getElementById('changePlanForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    
                    fetch('/commerce/subscription-portal/demo/{$subscriptionId}/change-plan', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            new_plan_id: formData.get('new_plan_id')
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Plan changed successfully!');
                            location.reload();
                        } else {
                            alert('Failed to change plan: ' + data.message);
                        }
                    })
                    .catch(err => {
                        alert('Error changing plan. Please try again.');
                    });
                });

                function cancelSubscription() {
                    fetch('/commerce/subscription-portal/demo/{$subscriptionId}/cancel', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Subscription cancelled successfully!');
                            location.reload();
                        } else {
                            alert('Failed to cancel subscription: ' + data.message);
                        }
                    })
                    .catch(err => {
                        alert('Error cancelling subscription. Please try again.');
                    });
                }
            </script>
        </body>
        </html>";
    }

    /**
     * Demo cancel subscription.
     */
    public function demoCancelSubscription(Request $request, int $subscriptionId)
    {
        try {
            $subscription = \App\Models\Commerce\Subscription::findOrFail($subscriptionId);
            
            if (!$subscription->metadata || !($subscription->metadata['demo_mode'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not a demo subscription'
                ], 400);
            }

            $subscription->update([
                'cancel_at_period_end' => true,
                'cancel_at' => $subscription->current_period_end,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'cancelled_via' => 'demo_portal',
                    'cancelled_at' => now()->toISOString(),
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription will be cancelled at the end of the current period',
                'data' => $subscription
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Demo change plan.
     */
    public function demoChangePlan(Request $request, int $subscriptionId)
    {
        try {
            $subscription = \App\Models\Commerce\Subscription::findOrFail($subscriptionId);
            $newPlanId = $request->input('new_plan_id');
            
            if (!$subscription->metadata || !($subscription->metadata['demo_mode'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not a demo subscription'
                ], 400);
            }

            $newPlan = \App\Models\Commerce\SubscriptionPlan::findOrFail($newPlanId);
            
            $subscription->update([
                'plan_id' => $newPlanId,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'plan_changed_via' => 'demo_portal',
                    'plan_changed_at' => now()->toISOString(),
                    'previous_plan_id' => $subscription->plan_id,
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plan changed successfully',
                'data' => $subscription
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Render demo invoices.
     */
    private function renderDemoInvoices($subscription)
    {
        $invoices = $subscription->invoices;
        if ($invoices->isEmpty()) {
            return '<p style="color: #6b7280;">No invoices yet.</p>';
        }

        $html = '';
        foreach ($invoices as $invoice) {
            $html .= "
            <div class=\"invoice-item\">
                <div>
                    <div class=\"invoice-date\">" . $invoice->created_at->format('M d, Y') . "</div>
                    <div>Invoice #{$invoice->stripe_invoice_id}</div>
                </div>
                <div>
                    <div class=\"invoice-amount\">\$" . ($invoice->amount_cents / 100) . "</div>
                    <div class=\"invoice-status status-paid\">Paid</div>
                </div>
            </div>";
        }

        return $html;
    }

    /**
     * Render plan options for change plan modal.
     */
    private function renderPlanOptions($plans)
    {
        $html = '';
        foreach ($plans as $plan) {
            $html .= "<option value=\"{$plan->id}\">{$plan->name} - \${$plan->formatted_amount}/{$plan->interval}</option>";
        }
        return $html;
    }

    /**
     * View invoice (public route - no authentication required).
     */
    public function viewInvoice(Request $request, int $invoiceId): Response
    {
        $invoice = SubscriptionInvoice::with(['subscription.plan', 'subscription.user'])
            ->findOrFail($invoiceId);

        $branding = TenantBranding::getDefaultBranding($invoice->tenant_id);
        $subscription = $invoice->subscription;

        // Generate HTML content
        $html = view('invoices.professional', compact('invoice', 'branding', 'subscription'))->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Download invoice (public route - no authentication required).
     */
    public function downloadInvoice(Request $request, int $invoiceId): Response
    {
        $invoice = SubscriptionInvoice::with(['subscription.plan', 'subscription.user'])
            ->findOrFail($invoiceId);

        $branding = TenantBranding::getDefaultBranding($invoice->tenant_id);
        $subscription = $invoice->subscription;

        // Generate HTML content
        $html = view('invoices.professional', compact('invoice', 'branding', 'subscription'))->render();

        $filename = "invoice-{$invoice->stripe_invoice_id}.html";

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
