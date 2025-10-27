<?php

namespace App\Services\Commerce;

use App\Models\Commerce\CommerceOrder;
use App\Models\Commerce\CommerceOrderItem;
use App\Models\Commerce\CommerceTransaction;
use App\Models\Quote;
use App\Models\Deal;
use App\Models\Contact;
use App\Services\ActivityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /**
     * Create a new order manually.
     */
    public function createManual(array $data): CommerceOrder
    {
        return DB::transaction(function () use ($data) {
            $order = CommerceOrder::create([
                'order_number' => CommerceOrder::generateOrderNumber(),
                'deal_id' => $data['deal_id'] ?? null,
                'quote_id' => $data['quote_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'customer_snapshot' => $data['customer_snapshot'] ?? null,
                'subtotal' => $data['subtotal'] ?? 0,
                'tax' => $data['tax'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'total_amount' => $data['total_amount'] ?? 0,
                'currency' => $data['currency'] ?? 'USD',
                'status' => $data['status'] ?? 'pending',
                'payment_method' => $data['payment_method'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'tenant_id' => $data['tenant_id'],
                'team_id' => $data['team_id'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            // Create order items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->createOrderItem($order, $itemData);
                }
            }

            // Log activity
            if (class_exists(ActivityService::class)) {
                $activityService = new ActivityService();
                $activityService->log('order_created', [
                    'subject' => 'Order created',
                    'description' => 'Order created',
                    'owner_id' => $data['created_by'] ?? Auth::id() ?? 1,
                    'tenant_id' => $order->tenant_id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                ]);
            }

            Log::channel('commerce')->info('Commerce order created', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tenant_id' => $order->tenant_id,
                'total_amount' => $order->total_amount,
            ]);

            return $order->load(['items', 'deal', 'quote', 'contact']);
        });
    }

    /**
     * Create a new order (alias for createManual for backward compatibility).
     */
    public function createOrder(array $data): CommerceOrder
    {
        return $this->createManual($data);
    }

    /**
     * Create an order item.
     */
    public function createOrderItem(CommerceOrder $order, array $itemData): CommerceOrderItem
    {
        $item = CommerceOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $itemData['product_id'] ?? null,
            'name' => $itemData['name'],
            'description' => $itemData['description'] ?? null,
            'quantity' => $itemData['quantity'] ?? 1,
            'unit_price' => $itemData['unit_price'],
            'discount' => $itemData['discount'] ?? 0,
            'tax_rate' => $itemData['tax_rate'] ?? 0,
            'subtotal' => 0, // Will be calculated automatically
        ]);

        return $item;
    }

    /**
     * Sync order from quote.
     */
    public function syncFromQuote(Quote $quote): CommerceOrder
    {
        $totalAmount = 0;
        $items = [];

        // Calculate total from quote items
        foreach ($quote->items as $quoteItem) {
            $subtotal = ($quoteItem->unit_price * $quoteItem->quantity) - ($quoteItem->discount ?? 0);
            $tax = $subtotal * (($quoteItem->tax_rate ?? 0) / 100);
            $itemTotal = $subtotal + $tax;
            $totalAmount += $itemTotal;

            $items[] = [
                'product_id' => $quoteItem->product_id,
                'name' => $quoteItem->name,
                'quantity' => $quoteItem->quantity,
                'unit_price' => $quoteItem->unit_price,
                'discount' => $quoteItem->discount ?? 0,
                'tax_rate' => $quoteItem->tax_rate ?? 0,
            ];
        }

        $orderData = [
            'quote_id' => $quote->id,
            'contact_id' => $quote->contact_id,
            'deal_id' => $quote->deal_id,
            'total_amount' => $totalAmount,
            'currency' => $quote->currency ?? 'USD',
            'status' => 'pending',
            'tenant_id' => $quote->tenant_id,
            'team_id' => $quote->team_id,
            'owner_id' => $quote->owner_id,
            'created_by' => Auth::id() ?? 1,
            'items' => $items,
        ];

        return $this->createManual($orderData);
    }

    /**
     * Update order status.
     */
    public function updateStatus(int $orderId, string $status, array $meta = []): CommerceOrder
    {
        $order = CommerceOrder::findOrFail($orderId);
        $oldStatus = $order->status;
        
        $updateData = array_merge(['status' => $status], $meta);
        $order->update($updateData);

        // Log activity
        if (class_exists(ActivityService::class)) {
            $activityService = new ActivityService();
            $activityService->log('order_status_updated', [
                'subject' => 'Order status updated',
                'description' => 'Order status updated',
                'owner_id' => Auth::id() ?? 1,
                'tenant_id' => $order->tenant_id,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'order_number' => $order->order_number,
            ]);
        }

        Log::channel('commerce')->info('Commerce order status updated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'tenant_id' => $order->tenant_id,
        ]);

        return $order->fresh();
    }

    /**
     * Update order status (alias for backward compatibility).
     */
    public function updateOrderStatus(CommerceOrder $order, string $status, array $additionalData = []): CommerceOrder
    {
        return $this->updateStatus($order->id, $status, $additionalData);
    }

    /**
     * Calculate order total.
     */
    public function calculateOrderTotal(CommerceOrder $order): float
    {
        $total = 0;

        foreach ($order->items as $item) {
            $total += $item->subtotal;
        }

        return $total;
    }

    /**
     * Recalculate order total and update.
     */
    public function recalculateOrderTotal(CommerceOrder $order): CommerceOrder
    {
        $total = $this->calculateOrderTotal($order);
        
        $order->update(['total_amount' => $total]);

        return $order->fresh();
    }

    /**
     * Attach payment evidence to order.
     */
    public function attachPaymentEvidence(int $orderId, string $provider, string $providerId, string $receiptUrl = null): CommerceOrder
    {
        $order = CommerceOrder::findOrFail($orderId);
        
        $order->update([
            'payment_method' => $provider,
            'payment_reference' => $providerId,
        ]);

        // Create transaction record
        CommerceTransaction::create([
            'order_id' => $order->id,
            'payment_provider' => $provider,
            'provider_event_id' => $providerId,
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'status' => 'succeeded',
            'event_type' => 'payment.completed',
            'tenant_id' => $order->tenant_id,
            'team_id' => $order->team_id,
            'raw_payload' => [
                'receipt_url' => $receiptUrl,
                'provider_id' => $providerId,
            ],
        ]);

        // Log activity
        if (class_exists(ActivityService::class)) {
            $activityService = new ActivityService();
            $activityService->log('payment_evidence_attached', [
                'subject' => 'Payment evidence attached to order',
                'description' => 'Payment evidence attached to order',
                'owner_id' => Auth::id() ?? 1,
                'tenant_id' => $order->tenant_id,
                'provider' => $provider,
                'provider_id' => $providerId,
                'order_number' => $order->order_number,
            ]);
        }

        return $order->fresh();
    }

    /**
     * Process refund for order.
     */
    public function refund(int $orderId, float $amount): CommerceOrder
    {
        $order = CommerceOrder::findOrFail($orderId);
        
        if (!$order->canBeRefunded()) {
            throw new \Exception('Order cannot be refunded');
        }

        // Update order status
        $order->update(['status' => 'refunded']);

        // Create transaction record for refund
        CommerceTransaction::create([
            'order_id' => $order->id,
            'payment_provider' => $order->payment_method ?? 'unknown',
            'amount' => $amount,
            'currency' => $order->currency,
            'status' => 'refunded',
            'event_type' => 'payment.refunded',
            'tenant_id' => $order->tenant_id,
            'team_id' => $order->team_id,
        ]);

        // Log activity
        if (class_exists(ActivityService::class)) {
            $activityService = new ActivityService();
            $activityService->log('order_refunded', [
                'subject' => 'Order refunded',
                'description' => 'Order refunded',
                'owner_id' => Auth::id() ?? 1,
                'tenant_id' => $order->tenant_id,
                'refund_amount' => $amount,
                'order_number' => $order->order_number,
            ]);
        }

        return $order->fresh();
    }

    /**
     * Get order statistics.
     */
    public function stats(int $tenantId, int $teamId = null): array
    {
        $query = CommerceOrder::where('tenant_id', $tenantId);
        
        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return [
            'total_orders' => $query->count(),
            'pending_orders' => $query->clone()->where('status', 'pending')->count(),
            'paid_orders' => $query->clone()->where('status', 'paid')->count(),
            'failed_orders' => $query->clone()->where('status', 'failed')->count(),
            'refunded_orders' => $query->clone()->where('status', 'refunded')->count(),
            'cancelled_orders' => $query->clone()->where('status', 'cancelled')->count(),
            'total_revenue' => $query->clone()->where('status', 'paid')->sum('total_amount'),
            'average_order_value' => $query->clone()->where('status', 'paid')->avg('total_amount'),
        ];
    }

    /**
     * Delete an order.
     */
    public function deleteOrder(CommerceOrder $order): bool
    {
        $orderId = $order->id;
        $tenantId = $order->tenant_id;

        // Delete associated order items first
        $order->items()->delete();

        // Delete associated transactions
        $order->transactions()->delete();

        // Delete the order
        $deleted = $order->delete();

        if ($deleted) {
            // Log the deletion
            Log::info('Order deleted', [
                'order_id' => $orderId,
                'order_number' => $order->order_number,
                'tenant_id' => $tenantId,
            ]);

            // Log activity
            if (class_exists(ActivityService::class)) {
                $activityService = new ActivityService();
                $activityService->log('order.deleted', [
                    'subject' => 'Order deleted',
                    'description' => 'Order deleted',
                    'owner_id' => Auth::id() ?? 1,
                    'tenant_id' => $tenantId,
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                ]);
            }
        }

        return $deleted;
    }
}
