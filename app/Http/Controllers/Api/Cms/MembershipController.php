<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\PageAccess;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MembershipController extends Controller
{
    /**
     * Display a listing of memberships.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $users = User::with(['roles'])
                        ->when($request->filled('role'), function($q) use ($request) {
                            $q->whereHas('roles', function($roleQuery) use ($request) {
                                $roleQuery->where('name', $request->role);
                            });
                        })
                        ->orderBy('name')
                        ->get();

            return response()->json([
                'success' => true,
                'data' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $user->roles->pluck('name'),
                        'created_at' => $user->created_at->toIso8601String(),
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch memberships', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch memberships',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified user's membership details.
     */
    public function show(int $userId): JsonResponse
    {
        try {
            $user = User::with(['roles'])->findOrFail($userId);

            // Get pages this user has access to
            $accessiblePages = PageAccess::whereJsonContains('allowed_users', $userId)
                                       ->orWhereHas('page', function($q) {
                                           $q->where('status', 'published');
                                       })
                                       ->with('page:id,title,slug,status')
                                       ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $user->roles->pluck('name'),
                    ],
                    'accessible_pages' => $accessiblePages->map(function ($access) {
                        return [
                            'page_id' => $access->page->id,
                            'title' => $access->page->title,
                            'slug' => $access->page->slug,
                            'access_type' => $access->access_type,
                        ];
                    }),
                    'total_accessible_pages' => $accessiblePages->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => config('app.debug') ? $e->getMessage() : 'User not found'
            ], 404);
        }
    }

    /**
     * Set page access rules.
     */
    public function setPageAccess(Request $request, int $pageId): JsonResponse
    {
        $request->validate([
            'access_type' => 'required|in:public,members,role,custom',
            'require_login' => 'nullable|boolean',
            'allowed_roles' => 'nullable|array',
            'allowed_roles.*' => 'string',
            'allowed_users' => 'nullable|array',
            'allowed_users.*' => 'integer|exists:users,id',
            'access_from' => 'nullable|date',
            'access_until' => 'nullable|date|after:access_from',
        ]);

        try {
            // Remove existing access rules for this page
            PageAccess::where('page_id', $pageId)->delete();

            // Create new access rule
            $accessRule = PageAccess::create([
                'page_id' => $pageId,
                'access_type' => $request->access_type,
                'require_login' => $request->boolean('require_login'),
                'allowed_roles' => $request->allowed_roles,
                'allowed_users' => $request->allowed_users,
                'access_from' => $request->access_from,
                'access_until' => $request->access_until,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Page access updated successfully',
                'data' => [
                    'page_id' => $pageId,
                    'access_type' => $accessRule->access_type,
                    'require_login' => $accessRule->require_login,
                    'allowed_roles' => $accessRule->allowed_roles,
                    'allowed_users' => $accessRule->allowed_users,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to set page access', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to set page access',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get page access rules.
     */
    public function getPageAccess(int $pageId): JsonResponse
    {
        try {
            $accessRules = PageAccess::where('page_id', $pageId)->active()->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'page_id' => $pageId,
                    'access_rules' => $accessRules->map(function ($rule) {
                        return [
                            'id' => $rule->id,
                            'access_type' => $rule->access_type,
                            'require_login' => $rule->require_login,
                            'allowed_roles' => $rule->allowed_roles,
                            'allowed_users' => $rule->allowed_users,
                            'access_from' => $rule->access_from?->toIso8601String(),
                            'access_until' => $rule->access_until?->toIso8601String(),
                        ];
                    }),
                    'has_restrictions' => $accessRules->isNotEmpty()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get page access', [
                'page_id' => $pageId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get page access',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}



