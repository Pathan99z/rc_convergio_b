<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\SubscriptionPlan;
use App\Services\Commerce\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublicSubscriptionController extends Controller
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Create subscription checkout session (public endpoint).
     */
    public function createSubscriptionSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|integer|exists:commerce_plans,id',
            'customer_email' => 'required|email',
            'customer_name' => 'string|max:255',
            'return_url' => 'required|url',
            'cancel_url' => 'required|url',
            'trial_days' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $planId = $request->input('plan_id');
            $plan = SubscriptionPlan::findOrFail($planId);

            // Check if plan is active
            if (!$plan->active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription plan is not available',
                ], 422);
            }

            // Create a temporary user object for the service
            $user = (object) [
                'id' => 'temp_' . time(),
                'email' => $request->input('customer_email'),
                'name' => $request->input('customer_name', ''),
                'success_url' => $request->input('return_url'),
                'cancel_url' => $request->input('cancel_url'),
            ];

            $result = $this->subscriptionService->createSubscriptionFromPlan(
                $plan->tenant_id,
                $user,
                $planId,
                $request->input('trial_days')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'session_url' => $result['session_url'],
                    'plan' => $result['plan'],
                    'customer_id' => $result['customer_id'] ?? null,
                    'demo_mode' => $result['demo_mode'] ?? false,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription plan details (public endpoint).
     */
    public function getPlanDetails(Request $request, int $planId): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::where('active', true)->findOrFail($planId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'interval' => $plan->interval,
                    'amount_cents' => $plan->amount_cents,
                    'currency' => $plan->currency,
                    'formatted_amount' => $plan->formatted_amount,
                    'trial_days' => $plan->trial_days,
                    'display_name' => $plan->display_name,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found',
            ], 404);
        }
    }

    /**
     * List available subscription plans (public endpoint).
     */
    public function listPlans(Request $request): JsonResponse
    {
        try {
            $plans = SubscriptionPlan::where('active', true)
                ->when($request->has('interval'), function ($query) use ($request) {
                    return $query->where('interval', $request->input('interval'));
                })
                ->orderBy('amount_cents', 'asc')
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                        'interval' => $plan->interval,
                        'amount_cents' => $plan->amount_cents,
                        'currency' => $plan->currency,
                        'formatted_amount' => $plan->formatted_amount,
                        'trial_days' => $plan->trial_days,
                        'display_name' => $plan->display_name,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $plans,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plans: ' . $e->getMessage(),
            ], 500);
        }
    }
}
