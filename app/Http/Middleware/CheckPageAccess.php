<?php

namespace App\Http\Middleware;

use App\Models\Cms\Page;
use App\Models\Cms\PageAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPageAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $pageId = $request->route('id') ?? $request->route('page_id');
        
        if (!$pageId) {
            return $next($request);
        }

        try {
            $page = Page::find($pageId);
            
            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found'
                ], 404);
            }

            // Check if page has access restrictions
            $accessRules = PageAccess::where('page_id', $pageId)->active()->get();
            
            if ($accessRules->isEmpty()) {
                // No restrictions, allow access
                return $next($request);
            }

            $user = Auth::user();
            $hasAccess = false;

            // Check each access rule
            foreach ($accessRules as $rule) {
                if ($rule->userHasAccess($user)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                // Check if login is required
                $requiresLogin = $accessRules->where('require_login', true)->isNotEmpty();
                
                if ($requiresLogin && !$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Authentication required',
                        'error_code' => 'LOGIN_REQUIRED'
                    ], 401);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You do not have permission to view this page.',
                    'error_code' => 'ACCESS_DENIED'
                ], 403);
            }

            return $next($request);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Access check failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}



