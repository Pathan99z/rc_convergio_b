<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\SubscriptionPlan;
use App\Services\Commerce\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionPlanController extends Controller
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display a listing of subscription plans.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $plans = SubscriptionPlan::where('tenant_id', $tenantId)
            ->when($request->has('active'), function ($query) use ($request) {
                return $query->where('active', $request->boolean('active'));
            })
            ->when($request->has('interval'), function ($query) use ($request) {
                return $query->where('interval', $request->input('interval'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        // Get active count for easy frontend rendering
        $activeCount = SubscriptionPlan::where('tenant_id', $tenantId)
            ->where('active', true)
            ->count();

        // Add active_count to the response data
        $responseData = $plans->toArray();
        $responseData['active_count'] = $activeCount;

        return response()->json([
            'success' => true,
            'data' => $responseData,
        ]);
    }

    /**
     * Store a newly created subscription plan.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:commerce_plans,slug,NULL,id,tenant_id,' . $request->user()->tenant_id,
            'interval' => 'required|in:monthly,yearly,weekly',
            'amount_cents' => 'required|integer|min:1',
            'currency' => 'string|size:3',
            'active' => 'boolean',
            'trial_days' => 'integer|min:0',
            'metadata' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $planData = $request->only([
                'name', 'slug', 'interval', 'amount_cents', 'currency', 
                'active', 'trial_days', 'metadata'
            ]);
            $planData['team_id'] = $request->user()->team_id;

            $plan = $this->subscriptionService->createPlanFromAdmin(
                $request->user()->tenant_id,
                $planData
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan created successfully',
                'data' => $plan,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription plan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified subscription plan.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $plan = SubscriptionPlan::where('tenant_id', $tenantId)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    /**
     * Update the specified subscription plan.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $plan = SubscriptionPlan::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:commerce_plans,slug,' . $id . ',id,tenant_id,' . $tenantId,
            'interval' => 'sometimes|in:monthly,yearly,weekly',
            'amount_cents' => 'sometimes|integer|min:1',
            'currency' => 'sometimes|string|size:3',
            'active' => 'sometimes|boolean',
            'trial_days' => 'sometimes|integer|min:0',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $plan->update($request->only([
                'name', 'slug', 'interval', 'amount_cents', 'currency', 
                'active', 'trial_days', 'metadata'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan updated successfully',
                'data' => $plan->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscription plan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified subscription plan.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $plan = SubscriptionPlan::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            // Check if plan has active subscriptions
            $activeSubscriptions = $plan->subscriptions()->active()->count();
            if ($activeSubscriptions > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete plan with active subscriptions',
                ], 422);
            }

            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subscription plan: ' . $e->getMessage(),
            ], 500);
        }
    }
}
