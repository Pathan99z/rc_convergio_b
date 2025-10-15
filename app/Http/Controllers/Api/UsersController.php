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
        $user = $this->userService->getCurrentUser($request->user());

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

        $users = $this->userService->getUsers($filters, $request->get('per_page', 15));

        return UserResource::collection($users);
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

        $this->userService->deleteUser($user);

        return response()->json(['message' => 'User deleted successfully']);
    }
}
