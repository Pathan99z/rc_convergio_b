<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\Subscription;
use App\Services\Commerce\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display a listing of subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $userId = $request->user()->id;
        
        // Create cache key with tenant and user isolation
        $cacheKey = "commerce_subscriptions_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        // Cache subscriptions list for 5 minutes (300 seconds)
        $subscriptions = Cache::remember($cacheKey, 300, function () use ($tenantId, $request) {
            return Subscription::where('tenant_id', $tenantId)
                ->with(['plan', 'user', 'invoices'])
                ->when($request->has('status'), function ($query) use ($request) {
                    return $query->where('status', $request->input('status'));
                })
                ->when($request->has('plan_id'), function ($query) use ($request) {
                    return $query->where('plan_id', $request->input('plan_id'));
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    /**
     * Display the specified subscription.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $userId = $request->user()->id;
        
        // Create cache key with tenant, user, and subscription ID isolation
        $cacheKey = "commerce_subscription_show_{$tenantId}_{$userId}_{$id}";
        
        // Cache subscription detail for 15 minutes (900 seconds)
        $subscription = Cache::remember($cacheKey, 900, function () use ($tenantId, $id) {
            return Subscription::where('tenant_id', $tenantId)
                ->with(['plan', 'user', 'invoices'])
                ->findOrFail($id);
        });

        return response()->json([
            'success' => true,
            'data' => $subscription,
        ]);
    }

    /**
     * Cancel the specified subscription.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'at_period_end' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $atPeriodEnd = $request->input('at_period_end', true);
            $subscription = $this->subscriptionService->cancelSubscriptionLocal($id, $atPeriodEnd);

            // Clear cache after cancelling subscription
            $tenantId = $request->user()->tenant_id;
            $this->clearCommerceSubscriptionsCache($tenantId, $request->user()->id);
            Cache::forget("commerce_subscription_show_{$tenantId}_{$request->user()->id}_{$id}");

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully',
                'data' => $subscription,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change the plan of the specified subscription.
     */
    public function changePlan(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'new_plan_id' => 'required|integer|exists:commerce_plans,id',
            'proration_behavior' => 'string|in:create_prorations,none,always_invoice',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $newPlanId = $request->input('new_plan_id');
            $prorationBehavior = $request->input('proration_behavior', 'create_prorations');
            
            $subscription = $this->subscriptionService->changePlan($id, $newPlanId, $prorationBehavior);

            // Clear cache after changing plan
            $tenantId = $request->user()->tenant_id;
            $this->clearCommerceSubscriptionsCache($tenantId, $request->user()->id);
            Cache::forget("commerce_subscription_show_{$tenantId}_{$request->user()->id}_{$id}");

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan changed successfully',
                'data' => $subscription,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change subscription plan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create billing portal session for subscription.
     */
    public function portal(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $subscription = Subscription::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$subscription->stripe_customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'No Stripe customer associated with this subscription',
            ], 422);
        }

        try {
            $stripeConfig = $this->getStripeConfig($tenantId);
            if (!$stripeConfig['configured']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe not configured for tenant',
                ], 422);
            }

            $stripeAdapter = new \App\Services\Commerce\StripeSubscriptionAdapter(
                $tenantId,
                $stripeConfig['secret_key'],
                $stripeConfig['public_key'],
                $stripeConfig['webhook_secret']
            );

            $returnUrl = $request->input('return_url', url('/subscription/portal'));
            $portalUrl = $stripeAdapter->createBillingPortalSession(
                $subscription->stripe_customer_id,
                $returnUrl
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'portal_url' => $portalUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create billing portal session: ' . $e->getMessage(),
            ], 500);
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
     * Get subscription activity/events.
     */
    public function activity(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        // Verify subscription exists and belongs to tenant
        $subscription = Subscription::where('tenant_id', $tenantId)->findOrFail($id);
        
        // Get subscription events with pagination
        $perPage = $request->input('per_page', 20);
        $userId = $request->user()->id;
        
        // Create cache key for subscription activity
        $cacheKey = "commerce_subscription_activity_{$tenantId}_{$userId}_{$id}_" . md5(serialize(['per_page' => $perPage]));
        
        // Cache subscription activity for 5 minutes (300 seconds)
        $cachedData = Cache::remember($cacheKey, 300, function () use ($subscription, $perPage) {
            $events = $subscription->events()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Transform events for better frontend consumption
            $transformedEvents = $events->getCollection()->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'description' => $this->getEventDescription($event->event_type),
                    'timestamp' => $event->created_at,
                    'processed_at' => $event->processed_at,
                    'is_processed' => $event->isProcessed(),
                    'payload' => $event->payload,
                    'stripe_event_id' => $event->stripe_event_id,
                ];
            });
            
            return [
                'subscription_id' => $subscription->id,
                'subscription_status' => $subscription->status,
                'activity' => $transformedEvents,
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                    'from' => $events->firstItem(),
                    'to' => $events->lastItem(),
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Subscription activity retrieved successfully',
            'data' => $cachedData
        ]);
    }

    /**
     * Get human-readable description for event type.
     */
    private function getEventDescription(string $eventType): string
    {
        $descriptions = [
            'customer.subscription.created' => 'Subscription created',
            'customer.subscription.updated' => 'Subscription updated',
            'customer.subscription.deleted' => 'Subscription cancelled',
            'invoice.payment_succeeded' => 'Payment received',
            'invoice.payment_failed' => 'Payment failed',
            'invoice.finalized' => 'Invoice generated',
            'checkout.session.completed' => 'Checkout completed',
            'payment_intent.succeeded' => 'Payment successful',
            'payment_intent.payment_failed' => 'Payment failed',
            'charge.succeeded' => 'Charge successful',
            'charge.failed' => 'Charge failed',
            'charge.refunded' => 'Payment refunded',
        ];

        return $descriptions[$eventType] ?? ucwords(str_replace(['.', '_'], [' ', ' '], $eventType));
    }

    /**
     * Clear commerce subscriptions cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearCommerceSubscriptionsCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for subscriptions list
            $commonParams = [
                '',
                md5(serialize(['status' => 'active', 'per_page' => 15])),
                md5(serialize(['status' => 'cancelled', 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("commerce_subscriptions_list_{$tenantId}_{$userId}_{$params}");
            }

            Log::info('Commerce subscriptions cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear commerce subscriptions cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
