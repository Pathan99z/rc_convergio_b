<?php

namespace App\Http\Middleware;

use App\Services\FeatureRestrictionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class FeatureRestrictionMiddleware
{
    public function __construct(
        private FeatureRestrictionService $featureRestrictionService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
                'error' => 'unauthenticated'
            ], 401);
        }

        // Check if user can access the feature
        if (!$this->featureRestrictionService->canAccessFeature($user, $feature)) {
            $message = $this->featureRestrictionService->getRestrictionMessage($feature);
            
            return response()->json([
                'message' => $message,
                'error' => 'feature_restricted',
                'feature' => $feature,
                'plan_type' => $this->featureRestrictionService->getUserPlanType($user),
                'user_domain' => $this->featureRestrictionService->getUserDomain($user)
            ], 403);
        }

        return $next($request);
    }
}
