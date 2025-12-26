<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Services\FeatureRestrictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{
    public function __construct(
        private UserService $userService,
        private FeatureRestrictionService $featureRestrictionService
    ) {}

    /**
     * Get current user profile.
     */
    public function me(Request $request): JsonResource
    {
        $user = $request->user();
        $userId = $user->id;
        
        // Create cache key for current user
        $cacheKey = "user_me_{$userId}";
        
        // Cache current user for 15 minutes (900 seconds)
        $user = Cache::remember($cacheKey, 900, function () use ($request) {
            return $this->userService->getCurrentUser($request->user());
        });

        return new UserResource($user);
    }

    /**
     * Update current user profile.
     */
    public function updateProfile(Request $request): JsonResource
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $request->user()->id,
            'organization_name' => 'sometimes|nullable|string|max:255',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        $user = $request->user();
        $data = $request->only(['name', 'email', 'organization_name']);

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        // Clear cache after updating profile
        Cache::forget("user_me_{$user->id}");

        return new UserResource($user->fresh());
    }

    /**
     * Display a listing of users (Admin only).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        // Check feature restriction
        if (!$this->featureRestrictionService->canCreateUsers($request->user())) {
            abort(403, $this->featureRestrictionService->getRestrictionMessage('user_management'));
        }

        $filters = [
            'q' => $request->get('q'),
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'role' => $request->get('role'),
            'sortBy' => $request->get('sortBy', 'created_at'),
            'sortOrder' => $request->get('sortOrder', 'desc'),
        ];

        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $userId = $request->user()->id;
        
        // Create cache key with tenant and user isolation
        $cacheKey = "users_list_{$tenantId}_{$userId}_" . md5(serialize($filters + ['per_page' => $request->get('per_page', 15)]));
        
        // Cache users list for 5 minutes (300 seconds)
        $users = Cache::remember($cacheKey, 300, function () use ($filters, $request) {
            $users = $this->userService->getUsers($filters, $request->get('per_page', 15));
            
            // Load team relationship for each user
            $users->getCollection()->load('team');
            
            return $users;
        });

        return UserResource::collection($users);
    }

    /**
     * Get users available for assignment (Team-aware).
     * This endpoint provides users that can be assigned as owners for deals, tasks, etc.
     */
    public function forAssignment(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $tenantId = $currentUser->tenant_id;
        
        $userId = $currentUser->id;
        
        // Create cache key for assignment users
        $cacheKey = "users_assignment_{$tenantId}_{$userId}";
        
        // Cache assignment users for 5 minutes (300 seconds)
        $users = Cache::remember($cacheKey, 300, function () use ($tenantId, $currentUser) {
            $query = User::query()
                ->where('tenant_id', $tenantId)
                ->select('id', 'name', 'email', 'team_id')
                ->with('team:id,name')
                ->orderBy('name');
            
            // Apply team filtering based on user role and team access
            if (config('features.team_access_enabled')) {
                if ($currentUser->hasRole('admin')) {
                    // Admin can see all users in tenant
                    // No additional filtering needed
                } else {
                    // Team members can only see:
                    // 1. Themselves
                    // 2. Other members of their team
                    // 3. Users with no team assignment
                    $query->where(function ($q) use ($currentUser) {
                        $q->where('id', $currentUser->id)  // Themselves
                          ->orWhere('team_id', $currentUser->team_id)  // Same team
                          ->orWhereNull('team_id');  // No team assignment
                    });
                }
            }
            
            return $query->get();
        });
        
        return response()->json([
            'success' => true,
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'team_id' => $user->team_id,
                    'team' => $user->team ? [
                        'id' => $user->team->id,
                        'name' => $user->team->name,
                    ] : null,
                ];
            })
        ]);
    }

    /**
     * Store a newly created user (Admin only).
     */
    public function store(StoreUserRequest $request): JsonResource
    {
        $this->authorize('create', User::class);

        // Check feature restriction
        if (!$this->featureRestrictionService->canCreateUsers($request->user())) {
            abort(403, $this->featureRestrictionService->getRestrictionMessage('user_management'));
        }

        $user = $this->userService->createUser($request->validated());

        // Clear cache after creating user
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $this->clearUsersCache($tenantId, $request->user()->id);

        return new UserResource($user);
    }

    /**
     * Display the specified user.
     */
    public function show($id): JsonResource
    {
        // Ensure ID is valid
        if (!$id || !is_numeric($id)) {
            abort(400, 'Invalid user ID provided');
        }

        $user = User::with('roles')->findOrFail($id);
        
        // Double-check user exists before proceeding
        if (!$user) {
            abort(404, 'User not found');
        }

        $this->authorize('view', $user);

        $tenantId = $user->tenant_id ?? $user->id;
        $currentUserId = auth()->id();
        
        // Create cache key with tenant, current user, and target user ID isolation
        $cacheKey = "user_show_{$tenantId}_{$currentUserId}_{$id}";
        
        // Cache user detail for 15 minutes (900 seconds)
        $user = Cache::remember($cacheKey, 900, function () use ($id) {
            return User::with('roles')->findOrFail($id);
        });

        return new UserResource($user);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, $id): JsonResource
    {
        // Ensure ID is valid and user exists
        if (!$id || !is_numeric($id)) {
            abort(400, 'Invalid user ID provided');
        }

        $user = User::with('roles')->findOrFail($id);
        
        // Double-check user exists before proceeding
        if (!$user) {
            abort(404, 'User not found');
        }

        $this->authorize('update', $user);

        $data = $request->validated();
        
        // Ensure we have data to update
        if (empty($data)) {
            abort(400, 'No data provided for update');
        }

        $this->userService->updateUser($user, $data);

        // Clear cache after updating user
        $tenantId = $user->tenant_id ?? $user->id;
        $this->clearUsersCache($tenantId, auth()->id());
        Cache::forget("user_show_{$tenantId}_{$request->user()->id}_{$id}");
        Cache::forget("user_me_{$id}");

        $user->refresh()->load('roles');
        return new UserResource($user);
    }

    /**
     * Remove the specified user (Admin only).
     */
    public function destroy($id): JsonResponse
    {
        // Ensure ID is valid
        if (!$id || !is_numeric($id)) {
            abort(400, 'Invalid user ID provided');
        }

        $user = User::findOrFail($id);
        
        // Double-check user exists before proceeding
        if (!$user) {
            abort(404, 'User not found');
        }

        $this->authorize('delete', $user);

        $tenantId = $user->tenant_id ?? $user->id;
        $currentUserId = auth()->id();
        $userId = $user->id;

        $this->userService->deleteUser($user);

        // Clear cache after deleting user
        $this->clearUsersCache($tenantId, $currentUserId);
        Cache::forget("user_show_{$tenantId}_{$currentUserId}_{$userId}");
        Cache::forget("user_me_{$userId}");

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Clear users cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearUsersCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for users list
            $commonParams = [
                '',
                md5(serialize(['sortBy' => 'created_at', 'sortOrder' => 'desc', 'per_page' => 15])),
                md5(serialize(['sortBy' => 'name', 'sortOrder' => 'asc', 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("users_list_{$tenantId}_{$userId}_{$params}");
            }

            // Clear assignment users cache
            Cache::forget("users_assignment_{$tenantId}_{$userId}");

            Log::info('Users cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams) + 1
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear users cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
