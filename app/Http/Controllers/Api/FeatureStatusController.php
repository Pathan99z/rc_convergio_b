<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeatureRestrictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureStatusController extends Controller
{
    public function __construct(
        private FeatureRestrictionService $featureRestrictionService
    ) {}

    /**
     * Get current user's feature access status
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
                'error' => 'unauthenticated'
            ], 401);
        }

        $featureAccess = $this->featureRestrictionService->getUserFeatureAccess($user);
        $planType = $this->featureRestrictionService->getUserPlanType($user);
        $userDomain = $this->featureRestrictionService->getUserDomain($user);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'email' => $user->email,
                'domain' => $userDomain,
                'plan_type' => $planType,
                'features' => $featureAccess,
                'restrictions' => [
                    'is_restricted_domain' => $this->featureRestrictionService->isRestrictedDomain($userDomain),
                    'is_business_domain' => $this->featureRestrictionService->isBusinessDomain($userDomain)
                ]
            ],
            'message' => 'Feature access status retrieved successfully'
        ]);
    }

    /**
     * Check if user can access a specific feature
     */
    public function checkFeature(Request $request, string $feature): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
                'error' => 'unauthenticated'
            ], 401);
        }

        $canAccess = $this->featureRestrictionService->canAccessFeature($user, $feature);
        $message = $this->featureRestrictionService->getRestrictionMessage($feature);
        $planType = $this->featureRestrictionService->getUserPlanType($user);

        return response()->json([
            'data' => [
                'feature' => $feature,
                'can_access' => $canAccess,
                'message' => $message,
                'plan_type' => $planType,
                'user_domain' => $this->featureRestrictionService->getUserDomain($user)
            ],
            'message' => $canAccess ? 'Feature access granted' : 'Feature access restricted'
        ]);
    }
}
