<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SuperAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SuperAdminController extends Controller
{
    protected SuperAdminService $superAdminService;

    // Define constant for validation failed message
    private const VALIDATION_FAILED_MESSAGE = 'Validation failed';

    public function __construct(SuperAdminService $superAdminService)
    {
        $this->superAdminService = $superAdminService;
    }

    /**
     * List all tenants
     */
    public function listTenants(Request $request): JsonResponse
    {
        // Validate and sanitize input
        $filters = [
            'status' => $request->query('status') ? strtolower(trim($request->query('status'))) : null,
            'search' => $request->query('search') ? trim($request->query('search')) : null,
        ];
        
        // Validate status if provided
        if ($filters['status'] && !in_array($filters['status'], ['active', 'inactive'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status value',
            ], 422);
        }

        $tenants = $this->superAdminService->getAllTenants($filters);

        return response()->json([
            'success' => true,
            'data' => $tenants->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'email' => $tenant->email,
                    'organization_name' => $tenant->organization_name,
                    'status' => $tenant->status,
                    'created_at' => $tenant->created_at,
                    'teams_count' => $tenant->teams_count ?? 0,
                    'team_memberships_count' => $tenant->team_memberships_count ?? 0,
                ];
            }),
        ]);
    }

    /**
     * Get tenant details
     */
    public function getTenant(int $id): JsonResponse
    {
        $tenant = User::find($id);
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        // Ensure this is a tenant owner
        if ($tenant->tenant_id !== $tenant->id && $tenant->tenant_id !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a tenant owner',
            ], 400);
        }

        $stats = $this->superAdminService->getTenantStats($id);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => new UserResource($tenant),
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Create new tenant
     */
    public function createTenant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|min:2',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'organization_name' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'assign_admin_role' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => self::VALIDATION_FAILED_MESSAGE,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = $this->superAdminService->createTenant($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => new UserResource($tenant),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tenant
     */
    public function updateTenant(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|max:255',
            'organization_name' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => self::VALIDATION_FAILED_MESSAGE,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenant = $this->superAdminService->updateTenant($id, $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => new UserResource($tenant),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all users (with optional tenant filter)
     */
    public function listAllUsers(Request $request): JsonResponse
    {
        // Validate and sanitize input
        $filters = [
            'tenant_id' => $request->query('tenant_id') ? (int) $request->query('tenant_id') : null,
            'status' => $request->query('status') ? strtolower(trim($request->query('status'))) : null,
            'search' => $request->query('search') ? trim($request->query('search')) : null,
        ];
        
        // Validate status if provided
        if ($filters['status'] && !in_array($filters['status'], ['active', 'inactive'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status value',
            ], 422);
        }
        
        // Validate tenant_id if provided
        if ($filters['tenant_id'] && !User::where('id', $filters['tenant_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tenant_id',
            ], 422);
        }

        $users = $this->superAdminService->getAllUsers($filters);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
        ]);
    }

    /**
     * Get user details
     */
    public function getUser(int $id): JsonResponse
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Create user in specific tenant
     */
    public function createUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|min:2',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'tenant_id' => 'required|integer|exists:users,id',
            'status' => 'nullable|in:active,inactive',
            'role' => 'nullable|string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => self::VALIDATION_FAILED_MESSAGE,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $this->superAdminService->createUserInTenant(
                $validator->validated()['tenant_id'],
                $validator->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => new UserResource($user),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|min:2',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|max:255',
            'status' => 'nullable|in:active,inactive',
            'tenant_id' => 'sometimes|integer|exists:users,id',
            'role' => 'nullable|string|exists:roles,name',
        ]);
        
        // Combined validation: form validation + self-protection rules
        $validationError = $this->validateUserUpdate($validator, $id);
        if ($validationError) {
            return $validationError;
        }

        try {
            $user = $this->superAdminService->updateUser($id, $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => new UserResource($user),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate user update request (form validation + self-protection rules).
     * Returns JsonResponse if validation fails, null if validation passes.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @param int $userId
     * @return JsonResponse|null
     */
    private function validateUserUpdate($validator, int $userId): ?JsonResponse
    {
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => self::VALIDATION_FAILED_MESSAGE,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        return $this->validateSelfProtectionRules($userId, $data);
    }

    /**
     * Validate self-protection rules for user updates.
     * Returns JsonResponse if validation fails, null if validation passes.
     *
     * @param int $userId
     * @param array $data
     * @return JsonResponse|null
     */
    private function validateSelfProtectionRules(int $userId, array $data): ?JsonResponse
    {
        // Prevent super admin from removing their own super_admin role
        if (isset($data['role']) && $userId === auth()->id()) {
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->isSuperAdmin() && $data['role'] !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot remove your own super_admin role',
                ], 403);
            }
        }
        
        // Prevent super admin from deactivating themselves
        if (isset($data['status']) && $data['status'] === 'inactive' && $userId === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own account',
            ], 403);
        }

        return null;
    }

    /**
     * Get system-wide statistics
     */
    public function systemStats(): JsonResponse
    {
        $stats = $this->superAdminService->getSystemStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get tenant statistics
     */
    public function tenantStats(int $id): JsonResponse
    {
        $stats = $this->superAdminService->getTenantStats($id);

        if (empty($stats)) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}

