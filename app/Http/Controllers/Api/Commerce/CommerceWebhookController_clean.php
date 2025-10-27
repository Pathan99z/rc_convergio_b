<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\CommerceOrder;
use App\Models\Commerce\CommercePaymentLink;
use App\Models\Commerce\CommerceTransaction;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommerceWebhookController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Handle Stripe webhook events.
     */
    public function stripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Parse the event first to get tenant information
        $event = json_decode($payload, true);
        if (!$event) {
            Log::channel('commerce')->error('Failed to parse Stripe webhook event');
            return response()->json(['error' => 'Invalid event data'], 400);
        }

        $eventType = $event['type'] ?? null;
        $eventId = $event['id'] ?? null;

        // Extract tenant information from metadata
        $tenantId = $this->extractTenantIdFromEvent($event);
        
        // Initialize StripeService with tenant-specific configuration
        $stripeService = new StripeService($tenantId);

        // Verify webhook signature
        if (!$stripeService->verifyWebhookSignature($payload, $signature)) {
            Log::channel('commerce')->error('Invalid Stripe webhook signature', [
                'signature' => $signature,
                'payload_length' => strlen($payload),
                'event_id' => $eventId,
                'tenant_id' => $tenantId,
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Check for duplicate events
        if ($eventId && CommerceTransaction::where('provider_event_id', $eventId)->exists()) {
            Log::channel('commerce')->info('Duplicate Stripe webhook event ignored', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            return response()->json(['status' => 'duplicate']);
        }

        try {
            DB::beginTransaction();

            // Create transaction record for all events
            $transaction = CommerceTransaction::create([
                'payment_provider' => 'stripe',
                'provider_event_id' => $eventId,
                'amount' => $this->extractAmount($event),
                'currency' => $this->extractCurrency($event),
                'status' => $this->extractStatus($event),
                'event_type' => $eventType,
                'raw_payload' => $event,
                'tenant_id' => $this->extractTenantId($event),
                'team_id' => $this->extractTeamId($event),
            ]);

            // Handle specific event types
            $result = $this->handleEvent($event, $transaction);

            DB::commit();

            Log::channel('commerce')->info('Stripe webhook processed successfully', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'transaction_id' => $transaction->id,
            ]);

            return response()->json([
                'status' => 'success',
                'event_id' => $eventId,
                'event_type' => $eventType,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('commerce')->error('Stripe webhook processing failed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle specific Stripe event types.
     */
    private function handleEvent(array $event, CommerceTransaction $transaction): array
    {
        $eventType = $event['type'] ?? null;
        $data = $event['data']['object'] ?? [];

        switch ($eventType) {
            case 'checkout.session.completed':
                return $this->handleCheckoutSessionCompleted($data, $transaction);

            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($data, $transaction);

            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($data, $transaction);

            case 'charge.refunded':
                return $this->handleChargeRefunded($data, $transaction);

            case 'invoice.payment_succeeded':
                return $this->handleInvoicePaymentSucceeded($data, $transaction);

            default:
                Log::channel('commerce')->info('Unhandled Stripe event type', [
                    'event_type' => $eventType,
                    'event_id' => $event['id'] ?? null,
                ]);

                return ['status' => 'unhandled'];
        }
    }

    /**
     * Handle checkout session completed event.
     */
    private function handleCheckoutSessionCompleted(array $session, CommerceTransaction $transaction): array
    {
        $sessionId = $session['id'] ?? null;
        $metadata = $session['metadata'] ?? [];

        // Find payment link by session ID
        $paymentLink = CommercePaymentLink::where('stripe_session_id', $sessionId)->first();
        
        if (!$paymentLink) {
            Log::channel('commerce')->warning('Payment link not found for checkout session', [
                'session_id' => $sessionId,
            ]);

            return ['status' => 'payment_link_not_found'];
        }

        // Update payment link status
        $paymentLink->update(['status' => 'completed']);

        // Find or create order
        $order = null;
        if ($paymentLink->order_id) {
            $order = CommerceOrder::find($paymentLink->order_id);
        } elseif ($paymentLink->quote_id) {
            // Create order from quote
            $quote = \App\Models\Quote::find($paymentLink->quote_id);
            if ($quote) {
                $order = $this->orderService->syncFromQuote($quote);
                $paymentLink->update(['order_id' => $order->id]);
            }
        }

        if ($order) {
            // Extract detailed payment information from Stripe session
            $paymentMethod = $session['payment_method_types'][0] ?? 'card';
            $paymentIntentId = $session['payment_intent'] ?? $sessionId;
            
            // Update order status to paid with detailed payment info
            $this->orderService->updateStatus($order->id, 'paid', [
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentIntentId,
            ]);

            // Update transaction with order reference
            $transaction->update(['order_id' => $order->id, 'payment_link_id' => $paymentLink->id]);

            return [
                'status' => 'success',
                'order_id' => $order->id,
                'payment_link_id' => $paymentLink->id,
                'order_number' => $order->order_number,
            ];
        }

        return ['status' => 'order_not_created'];
    }

    /**
     * Handle payment intent succeeded event.
     */
    private function handlePaymentIntentSucceeded(array $paymentIntent, CommerceTransaction $transaction): array
    {
        $paymentIntentId = $paymentIntent['id'] ?? null;
        $metadata = $paymentIntent['metadata'] ?? [];

        // Try to find order by metadata
        if (isset($metadata['order_id'])) {
            $order = CommerceOrder::find($metadata['order_id']);
            if ($order) {
                // Extract detailed payment information from payment intent
                $paymentMethod = $paymentIntent['payment_method_types'][0] ?? 'card';
                $chargeId = $paymentIntent['charges']['data'][0]['id'] ?? $paymentIntentId;
                
                $this->orderService->updateStatus($order->id, 'paid', [
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentIntentId,
                ]);

                $transaction->update(['order_id' => $order->id]);

                return [
                    'status' => 'success',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ];
            }
        }

        return ['status' => 'order_not_found'];
    }

    /**
     * Handle payment intent failed event.
     */
    private function handlePaymentIntentFailed(array $paymentIntent, CommerceTransaction $transaction): array
    {
        $paymentIntentId = $paymentIntent['id'] ?? null;
        $metadata = $paymentIntent['metadata'] ?? [];

        // Try to find order by metadata
        if (isset($metadata['order_id'])) {
            $order = CommerceOrder::find($metadata['order_id']);
            if ($order) {
                $this->orderService->updateStatus($order->id, 'failed', [
                    'payment_method' => 'stripe',
                    'payment_reference' => $paymentIntentId,
                ]);

                $transaction->update(['order_id' => $order->id]);

                return [
                    'status' => 'success',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ];
            }
        }

        return ['status' => 'order_not_found'];
    }

    /**
     * Handle charge refunded event.
     */
    private function handleChargeRefunded(array $charge, CommerceTransaction $transaction): array
    {
        $chargeId = $charge['id'] ?? null;
        $metadata = $charge['metadata'] ?? [];

        // Try to find order by metadata
        if (isset($metadata['order_id'])) {
            $order = CommerceOrder::find($metadata['order_id']);
            if ($order) {
                $refundAmount = $charge['amount_refunded'] ?? 0;
                $refundAmount = $refundAmount / 100; // Convert from cents

                $this->orderService->refund($order->id, $refundAmount);
                $transaction->update(['order_id' => $order->id]);

                return [
                    'status' => 'success',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'refund_amount' => $refundAmount,
                ];
            }
        }

        return ['status' => 'order_not_found'];
    }

    /**
     * Handle invoice payment succeeded event.
     */
    private function handleInvoicePaymentSucceeded(array $invoice, CommerceTransaction $transaction): array
    {
        // Handle subscription payments if needed
        Log::channel('commerce')->info('Invoice payment succeeded', [
            'invoice_id' => $invoice['id'] ?? null,
        ]);

        return ['status' => 'handled'];
    }

    /**
     * Extract amount from event data.
     */
    private function extractAmount(array $event): float
    {
        $data = $event['data']['object'] ?? [];
        $amount = $data['amount_total'] ?? $data['amount'] ?? $data['amount_refunded'] ?? 0;
        return $amount / 100; // Convert from cents
    }

    /**
     * Extract currency from event data.
     */
    private function extractCurrency(array $event): string
    {
        $data = $event['data']['object'] ?? [];
        return strtoupper($data['currency'] ?? 'USD');
    }

    /**
     * Extract status from event data.
     */
    private function extractStatus(array $event): string
    {
        $data = $event['data']['object'] ?? [];
        $eventType = $event['type'] ?? '';

        if (str_contains($eventType, 'succeeded') || str_contains($eventType, 'completed')) {
            return 'succeeded';
        }

        if (str_contains($eventType, 'failed')) {
            return 'failed';
        }

        if (str_contains($eventType, 'refunded')) {
            return 'refunded';
        }

        return $data['status'] ?? 'unknown';
    }

    /**
     * Extract tenant ID from event metadata.
     */
    private function extractTenantId(array $event): int
    {
        $data = $event['data']['object'] ?? [];
        $metadata = $data['metadata'] ?? [];
        return (int) ($metadata['tenant_id'] ?? 1);
    }

    /**
     * Extract team ID from event metadata.
     */
    private function extractTeamId(array $event): ?int
    {
        $data = $event['data']['object'] ?? [];
        $metadata = $data['metadata'] ?? [];
        $teamId = $metadata['team_id'] ?? null;
        return $teamId ? (int) $teamId : null;
    }

    /**
     * Extract tenant ID from Stripe event metadata.
     */
    private function extractTenantIdFromEvent(array $event): ?int
    {
        // Try to get tenant_id from various sources in the event
        $data = $event['data']['object'] ?? [];
        
        // Check metadata first
        if (isset($data['metadata']['tenant_id'])) {
            return (int) $data['metadata']['tenant_id'];
        }
        
        // Check session metadata for checkout events
        if (isset($data['metadata']['tenant_id'])) {
            return (int) $data['metadata']['tenant_id'];
        }
        
        // For payment intent events, check metadata
        if (isset($data['metadata']['tenant_id'])) {
            return (int) $data['metadata']['tenant_id'];
        }
        
        // Try to find payment link by session ID
        if (isset($data['id'])) {
            $paymentLink = CommercePaymentLink::where('stripe_session_id', $data['id'])->first();
            if ($paymentLink) {
                return $paymentLink->tenant_id;
            }
        }
        
        // Default to null if not found
        return null;
    }
}
