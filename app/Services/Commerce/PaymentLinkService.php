<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommercePaymentLink;
use App\Models\Commerce\CommerceOrder;
use App\Models\Commerce\CommerceOrderItem;
use App\Models\Quote;
use App\Mail\PaymentLinkMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaymentLinkService
{
    /**
     * Create a payment link for a quote (real Stripe or test mode).
     */
    public function createPaymentLink(Quote $quote, array $data = []): CommercePaymentLink
    {
        $stripeService = new StripeService($quote->tenant_id);
        
        // Check if Stripe is configured for this tenant
        if ($stripeService->isConfigured()) {
            return $this->createRealStripeLink($quote, $stripeService, $data);
        } else {
            return $this->createTestLink($quote, $data);
        }
    }

    /**
     * Create a real Stripe payment link for a quote.
     */
    private function createRealStripeLink(Quote $quote, StripeService $stripeService, array $data = []): CommercePaymentLink
    {
        try {
            // Prepare line items for Stripe
            $lineItems = [];
            
            if ($quote->items && $quote->items->count() > 0) {
                // Use quote items
                foreach ($quote->items as $item) {
                    $lineItems[] = [
                        'price_data' => [
                            'currency' => strtolower($quote->currency ?? 'usd'),
                            'product_data' => [
                                'name' => $item->name,
                                'description' => $item->description,
                            ],
                            'unit_amount' => (int) ($item->unit_price * 100), // Convert to cents
                        ],
                        'quantity' => $item->quantity,
                    ];
                }
            } else {
                // Single line item for the total amount
                $lineItems[] = [
                    'price_data' => [
                        'currency' => strtolower($quote->currency ?? 'usd'),
                        'product_data' => [
                            'name' => $data['title'] ?? "Payment for Quote {$quote->quote_number}",
                            'description' => $data['description'] ?? 'Quote payment',
                        ],
                        'unit_amount' => (int) (($quote->total ?? $quote->total_amount) * 100), // Convert to cents
                    ],
                    'quantity' => 1,
                ];
            }

            // Create Stripe checkout session
            $successUrl = url('/commerce/success?session_id={CHECKOUT_SESSION_ID}');
            $cancelUrl = url('/commerce/cancel');
            
            $metadata = [
                'quote_id' => $quote->id,
                'tenant_id' => $quote->tenant_id,
                'quote_number' => $quote->quote_number,
            ];

            $stripeResult = $stripeService->createCheckoutSession(
                $lineItems,
                $successUrl,
                $cancelUrl,
                $metadata
            );

            if (!$stripeResult['success']) {
                throw new \Exception('Failed to create Stripe checkout session: ' . ($stripeResult['error'] ?? 'Unknown error'));
            }

            // Create payment link with real Stripe data
            $paymentLink = CommercePaymentLink::create([
                'quote_id' => $quote->id,
                'stripe_session_id' => $stripeResult['session_id'],
                'url' => $stripeResult['url'], // Real Stripe checkout URL
                'title' => $data['title'] ?? "Payment for Quote {$quote->quote_number}",
                'description' => $data['description'] ?? 'Complete your payment',
                'amount' => $quote->total ?? $quote->total_amount,
                'currency' => $quote->currency ?? 'USD',
                'status' => 'active',
                'expires_at' => $data['expires_at'] ?? now()->addDays(30),
                'tenant_id' => $quote->tenant_id,
                'team_id' => $quote->team_id,
                'created_by' => Auth::id() ?? 1,
            ]);

            Log::info('Real Stripe payment link created', [
                'payment_link_id' => $paymentLink->id,
                'quote_id' => $quote->id,
                'stripe_session_id' => $stripeResult['session_id'],
                'tenant_id' => $quote->tenant_id,
                'amount' => $paymentLink->amount,
            ]);

            return $paymentLink->fresh();

        } catch (\Exception $e) {
            Log::error('Failed to create real Stripe payment link', [
                'quote_id' => $quote->id,
                'tenant_id' => $quote->tenant_id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to test link if Stripe fails
            return $this->createTestLink($quote, $data);
        }
    }

    /**
     * Create a test payment link for a quote (fallback when Stripe not configured).
     */
    private function createTestLink(Quote $quote, array $data = []): CommercePaymentLink
    {
        $paymentLink = CommercePaymentLink::create([
            'quote_id' => $quote->id,
            'stripe_session_id' => 'test_session_' . Str::random(24),
            'url' => $this->generateTestUrl($quote),
            'title' => $data['title'] ?? "Payment for Quote {$quote->quote_number}",
            'description' => $data['description'] ?? 'Complete your payment (Test Mode)',
            'amount' => $quote->total ?? $quote->total_amount,
            'currency' => $quote->currency ?? 'USD',
            'status' => 'active',
            'expires_at' => $data['expires_at'] ?? now()->addDays(30),
            'tenant_id' => $quote->tenant_id,
            'team_id' => $quote->team_id,
            'created_by' => Auth::id() ?? 1,
        ]);

        // Update the URL with the actual payment link ID
        $paymentLink->update([
            'url' => url("/commerce/checkout/{$paymentLink->id}")
        ]);

        Log::info('Test payment link created', [
            'payment_link_id' => $paymentLink->id,
            'quote_id' => $quote->id,
            'tenant_id' => $quote->tenant_id,
            'url' => $paymentLink->url,
            'note' => 'Stripe not configured - using test mode',
        ]);

        return $paymentLink->fresh();
    }

    /**
     * Create a real Stripe payment link for a quote.
     */
    public function createRealLink(Quote $quote, array $stripeData): CommercePaymentLink
    {
        $paymentLink = CommercePaymentLink::create([
            'quote_id' => $quote->id,
            'stripe_session_id' => $stripeData['session_id'] ?? null,
            'url' => $stripeData['url'] ?? null,
            'status' => 'active',
            'expires_at' => isset($stripeData['expires_at']) ? now()->parse($stripeData['expires_at']) : now()->addDays(30),
            'tenant_id' => $quote->tenant_id,
            'team_id' => $quote->team_id,
        ]);

        Log::info('Real payment link created', [
            'payment_link_id' => $paymentLink->id,
            'quote_id' => $quote->id,
            'tenant_id' => $quote->tenant_id,
            'stripe_session_id' => $paymentLink->stripe_session_id,
        ]);

        return $paymentLink;
    }

    /**
     * Activate a payment link.
     */
    public function activateLink(CommercePaymentLink $paymentLink): CommercePaymentLink
    {
        $paymentLink->update(['status' => 'active']);

        Log::info('Payment link activated', [
            'payment_link_id' => $paymentLink->id,
            'quote_id' => $paymentLink->quote_id,
            'tenant_id' => $paymentLink->tenant_id,
        ]);

        return $paymentLink->fresh();
    }

    /**
     * Deactivate a payment link.
     */
    public function deactivateLink(CommercePaymentLink $paymentLink): CommercePaymentLink
    {
        $paymentLink->update(['status' => 'draft']);

        Log::info('Payment link deactivated', [
            'payment_link_id' => $paymentLink->id,
            'quote_id' => $paymentLink->quote_id,
            'tenant_id' => $paymentLink->tenant_id,
        ]);

        return $paymentLink->fresh();
    }

    /**
     * Mark payment link as completed.
     */
    public function completeLink(CommercePaymentLink $paymentLink): CommercePaymentLink
    {
        $paymentLink->update(['status' => 'completed']);

        Log::info('Payment link completed', [
            'payment_link_id' => $paymentLink->id,
            'quote_id' => $paymentLink->quote_id,
            'tenant_id' => $paymentLink->tenant_id,
        ]);

        return $paymentLink->fresh();
    }

    /**
     * Mark payment link as expired.
     */
    public function expireLink(CommercePaymentLink $paymentLink): CommercePaymentLink
    {
        $paymentLink->update(['status' => 'expired']);

        Log::info('Payment link expired', [
            'payment_link_id' => $paymentLink->id,
            'quote_id' => $paymentLink->quote_id,
            'tenant_id' => $paymentLink->tenant_id,
        ]);

        return $paymentLink->fresh();
    }

    /**
     * Generate a test payment URL.
     */
    private function generateTestUrl(Quote $quote): string
    {
        // We'll update this after the payment link is created
        return url("/commerce/checkout/PLACEHOLDER");
    }

    /**
     * Get payment link for a quote.
     */
    public function getLinkForQuote(Quote $quote): ?CommercePaymentLink
    {
        return CommercePaymentLink::where('quote_id', $quote->id)
            ->where('tenant_id', $quote->tenant_id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Check if a quote has an active payment link.
     */
    public function hasActiveLink(Quote $quote): bool
    {
        return $this->getLinkForQuote($quote) !== null;
    }

    /**
     * Clean up expired payment links.
     */
    public function cleanupExpiredLinks(): int
    {
        $expiredCount = CommercePaymentLink::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        if ($expiredCount > 0) {
            Log::info('Expired payment links cleaned up', [
                'expired_count' => $expiredCount,
            ]);
        }

        return $expiredCount;
    }

    /**
     * Update a payment link.
     */
    public function updateLink(CommercePaymentLink $paymentLink, array $data): CommercePaymentLink
    {
        $paymentLink->update($data);

        // Log the update
        Log::info('Payment link updated', [
            'payment_link_id' => $paymentLink->id,
            'updated_fields' => array_keys($data),
            'tenant_id' => $paymentLink->tenant_id,
        ]);

        return $paymentLink->fresh();
    }

    /**
     * Delete a payment link.
     */
    public function deleteLink(CommercePaymentLink $paymentLink): bool
    {
        $paymentLinkId = $paymentLink->id;
        $tenantId = $paymentLink->tenant_id;

        $deleted = $paymentLink->delete();

        if ($deleted) {
            // Log the deletion
            Log::info('Payment link deleted', [
                'payment_link_id' => $paymentLinkId,
                'tenant_id' => $tenantId,
            ]);
        }

        return $deleted;
    }

    /**
     * Create an order from a quote when payment is completed.
     */
    public function createOrderFromQuote(Quote $quote, array $paymentDetails = []): CommerceOrder
    {
        // Create the order
        $order = CommerceOrder::create([
            'order_number' => CommerceOrder::generateOrderNumber(),
            'quote_id' => $quote->id,
            'contact_id' => $quote->contact_id,
            'deal_id' => $quote->deal_id,
            'total_amount' => $quote->total ?? $quote->total_amount,
            'subtotal' => $quote->subtotal,
            'tax' => $quote->tax,
            'discount' => $quote->discount,
            'currency' => $quote->currency,
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => $paymentDetails['method'] ?? null,
            'payment_reference' => $paymentDetails['reference'] ?? null,
            'customer_snapshot' => [
                'quote_number' => $quote->quote_number,
                'valid_until' => $quote->valid_until,
            ],
            'tenant_id' => $quote->tenant_id,
            'team_id' => $quote->team_id,
            'created_by' => $quote->created_by,
        ]);

        // Create order items from quote items
        foreach ($quote->items as $quoteItem) {
            CommerceOrderItem::create([
                'order_id' => $order->id,
                'name' => $quoteItem->name,
                'description' => $quoteItem->description,
                'quantity' => $quoteItem->quantity,
                'unit_price' => $quoteItem->unit_price,
                'subtotal' => $quoteItem->subtotal,
                'tenant_id' => $quote->tenant_id,
            ]);
        }

        // Log the order creation
        Log::info('Order created from completed payment', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'quote_id' => $quote->id,
            'total_amount' => $order->total_amount,
            'payment_method' => $order->payment_method,
            'payment_reference' => $order->payment_reference,
            'tenant_id' => $order->tenant_id,
        ]);

        return $order;
    }

    /**
     * Send payment link via email.
     */
    public function sendPaymentLinkEmail(
        CommercePaymentLink $paymentLink,
        string $customerEmail,
        string $customerName = null,
        array $additionalData = []
    ): bool {
        try {
            $quote = $paymentLink->quote;
            $customerName = $customerName ?? $quote->contact->name ?? 'Valued Customer';

            // Send the email
            Mail::to($customerEmail)->send(new PaymentLinkMail(
                $paymentLink,
                $customerName,
                $customerEmail,
                $quote
            ));

            // Log the email sent
            Log::info('Payment link email sent', [
                'payment_link_id' => $paymentLink->id,
                'customer_email' => $customerEmail,
                'customer_name' => $customerName,
                'quote_id' => $quote?->id,
                'tenant_id' => $paymentLink->tenant_id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment link email', [
                'payment_link_id' => $paymentLink->id,
                'customer_email' => $customerEmail,
                'error' => $e->getMessage(),
                'tenant_id' => $paymentLink->tenant_id,
            ]);

            return false;
        }
    }

    /**
     * Send payment link to multiple recipients.
     */
    public function sendBulkPaymentLinkEmails(
        CommercePaymentLink $paymentLink,
        array $recipients,
        array $additionalData = []
    ): array {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($recipients as $recipient) {
            $email = $recipient['email'] ?? $recipient;
            $name = $recipient['name'] ?? null;

            if ($this->sendPaymentLinkEmail($paymentLink, $email, $name, $additionalData)) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to send to {$email}";
            }
        }

        Log::info('Bulk payment link emails sent', [
            'payment_link_id' => $paymentLink->id,
            'total_recipients' => count($recipients),
            'sent' => $results['sent'],
            'failed' => $results['failed'],
            'tenant_id' => $paymentLink->tenant_id,
        ]);

        return $results;
    }

    /**
     * Create and send payment link in one operation.
     */
    public function createAndSendPaymentLink(
        Quote $quote,
        string $customerEmail,
        string $customerName = null,
        array $data = []
    ): array {
        try {
            // Create the payment link
            $paymentLink = $this->createPaymentLink($quote, $data);

            // Send the email
            $emailSent = $this->sendPaymentLinkEmail($paymentLink, $customerEmail, $customerName);

            return [
                'success' => true,
                'payment_link' => $paymentLink,
                'email_sent' => $emailSent,
                'message' => $emailSent 
                    ? 'Payment link created and email sent successfully'
                    : 'Payment link created but email failed to send',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create and send payment link', [
                'quote_id' => $quote->id,
                'customer_email' => $customerEmail,
                'error' => $e->getMessage(),
                'tenant_id' => $quote->tenant_id,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_link' => null,
                'email_sent' => false,
            ];
        }
    }

    /**
     * Resend payment link email.
     */
    public function resendPaymentLinkEmail(CommercePaymentLink $paymentLink, string $customerEmail, string $customerName = null): bool
    {
        return $this->sendPaymentLinkEmail($paymentLink, $customerEmail, $customerName);
    }
}
