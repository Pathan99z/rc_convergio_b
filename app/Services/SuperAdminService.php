<?php

namespace App\Services;

use App\Constants\LinkedAccountConstants;
use App\Exceptions\InvalidTenantException;
use App\Exceptions\RoleNotFoundException;
use App\Exceptions\TenantNotFoundException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class SuperAdminService
{
    protected GenericLinkedAccountService $linkedAccountService;

    public function __construct(GenericLinkedAccountService $linkedAccountService)
    {
        $this->linkedAccountService = $linkedAccountService;
    }

    /**
     * Get all tenants (users who are tenant owners)
     */
    public function getAllTenants(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::query()
            ->select('id', 'name', 'email', 'organization_name', 'status', 'created_at')
            ->where(function ($q) {
                // Users where tenant_id equals their own id (tenant owners)
                $q->whereColumn('id', 'tenant_id')
                  ->orWhere('tenant_id', 0); // Also include users with tenant_id = 0 (legacy)
            })
            ->withCount([
                'teams as teams_count',
                'teamMemberships as team_memberships_count'
            ])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = trim($filters['search']);
            // Prevent SQL injection by using parameter binding
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('organization_name', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Get tenant statistics
     */
    public function getTenantStats(int $tenantId): array
    {
        $tenant = User::find($tenantId);
        
        if (!$tenant) {
            return [];
        }

        return [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant->organization_name ?? $tenant->name,
            'tenant_email' => $tenant->email,
            'status' => $tenant->status,
            'created_at' => $tenant->created_at,
            'users_count' => User::where('tenant_id', $tenantId)->count(),
            'contacts_count' => DB::table('contacts')->where('tenant_id', $tenantId)->count(),
            'companies_count' => DB::table('companies')->where('tenant_id', $tenantId)->count(),
            'deals_count' => DB::table('deals')->where('tenant_id', $tenantId)->count(),
            'teams_count' => DB::table('teams')->where('tenant_id', $tenantId)->count(),
        ];
    }

    /**
     * Get system-wide statistics
     */
    public function getSystemStats(): array
    {
        return [
            'total_tenants' => User::where(function ($q) {
                $q->whereColumn('id', 'tenant_id')
                  ->orWhere('tenant_id', 0);
            })->count(),
            'total_users' => User::count(),
            'total_contacts' => DB::table('contacts')->count(),
            'total_companies' => DB::table('companies')->count(),
            'total_deals' => DB::table('deals')->count(),
            'total_teams' => DB::table('teams')->count(),
            'active_tenants' => User::where(function ($q) {
                $q->whereColumn('id', 'tenant_id')
                  ->orWhere('tenant_id', 0);
            })->where('status', 'active')->count(),
        ];
    }

    /**
     * Get all users across all tenants (with optional tenant filter)
     */
    public function getAllUsers(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::query()
            ->with(['roles', 'team'])
            ->orderBy('created_at', 'desc');

        // Apply tenant filter if provided
        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        // Apply status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply search filter
        if (isset($filters['search'])) {
            $search = trim($filters['search']);
            // Prevent SQL injection by using parameter binding
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create a new tenant
     */
    public function createTenant(array $data): User
    {
        DB::beginTransaction();
        try {
            // Sanitize and validate input
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'], // Will be hashed by User model
                'organization_name' => isset($data['organization_name']) ? trim($data['organization_name']) : null,
                'status' => $data['status'] ?? 'active',
            ]);

            // Set tenant_id to user's own ID (making them a tenant owner)
            $user->update(['tenant_id' => $user->id]);

            // Assign admin role if specified
            if (isset($data['assign_admin_role']) && $data['assign_admin_role']) {
                $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
                if ($adminRole) {
                    $user->assignRole($adminRole);
                }
            }

            DB::commit();

            Log::info('Super Admin created new tenant', [
                'super_admin_id' => auth()->id(),
                'super_admin_email' => auth()->user()->email ?? null,
                'tenant_id' => $user->id,
                'tenant_email' => $user->email,
                'tenant_name' => $user->name,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $user->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create tenant', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Update tenant
     */
    public function updateTenant(int $tenantId, array $data): User
    {
        $tenant = User::findOrFail($tenantId);

        // Ensure this is a tenant owner
        if ($tenant->tenant_id !== $tenant->id && $tenant->tenant_id !== 0) {
            throw new InvalidTenantException('User is not a tenant owner');
        }

        DB::beginTransaction();
        try {
            $updateData = [];
            
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            
            if (isset($data['email'])) {
                $updateData['email'] = strtolower(trim($data['email']));
            }
            
            if (isset($data['organization_name'])) {
                $updateData['organization_name'] = trim($data['organization_name']);
            }
            
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            
            // Only update password if provided and not empty
            if (isset($data['password']) && !empty(trim($data['password']))) {
                $updateData['password'] = $data['password']; // Will be hashed by User model
            }

            $tenant->update($updateData);

            DB::commit();

            Log::info('Super Admin updated tenant', [
                'super_admin_id' => auth()->id(),
                'super_admin_email' => auth()->user()->email ?? null,
                'tenant_id' => $tenantId,
                'updated_fields' => array_keys($updateData),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $tenant->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update tenant', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            throw $e;
        }
    }

    /**
     * Create user in a specific tenant
     */
    public function createUserInTenant(int $tenantId, array $data): User
    {
        // Validate that tenant_id is a valid tenant owner
        $tenant = User::find($tenantId);
        if (!$tenant || ($tenant->tenant_id !== $tenant->id && $tenant->tenant_id !== 0)) {
            throw new InvalidTenantException('Invalid tenant_id: User is not a tenant owner');
        }

        DB::beginTransaction();
        try {
            // Sanitize and validate input
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'], // Will be hashed by User model
                'tenant_id' => $tenantId,
                'status' => $data['status'] ?? 'active',
            ]);

            // Assign role if specified
            if (isset($data['role'])) {
                $role = \Spatie\Permission\Models\Role::where('name', $data['role'])->first();
                if ($role) {
                    $user->assignRole($role);
                }
            }

            DB::commit();

            $user = $user->fresh();

            if (isset($data['role']) && ($data['role'] === 'admin' || $data['role'] === 'super_admin')) {
                $this->linkedAccountService->syncUserToProduct(
                    $user,
                    LinkedAccountConstants::PRODUCT_CONSOLE,
                    LinkedAccountConstants::TYPE_SSO_ONLY
                );
            }

            Log::info('Super Admin created user in tenant', [
                'super_admin_id' => auth()->id(),
                'super_admin_email' => auth()->user()->email ?? null,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'tenant_id' => $tenantId,
                'assigned_role' => $data['role'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create user in tenant', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            throw $e;
        }
    }

    /**
     * Update user (can be in any tenant)
     */
    public function updateUser(int $userId, array $data): User
    {
        $user = User::findOrFail($userId);

        DB::beginTransaction();
        try {
            $updateData = $this->prepareUserUpdateData($data);

            $wasAdmin = $user->hasRole('admin') || $user->hasRole('super_admin');

            $user->update($updateData);

            if (isset($data['role'])) {
                $this->updateUserRole($user, $data['role']);
            }

            DB::commit();

            $user = $user->fresh()->load('roles');
            $this->syncLinkedAccountIfNeeded($user, $wasAdmin, $data);

            Log::info('Super Admin updated user', [
                'super_admin_id' => auth()->id(),
                'super_admin_email' => auth()->user()->email ?? null,
                'user_id' => $userId,
                'user_email' => $user->email,
                'updated_fields' => array_keys($updateData),
                'role_changed' => isset($data['role']),
                'new_role' => $data['role'] ?? null,
                'tenant_changed' => isset($data['tenant_id']),
                'new_tenant_id' => $data['tenant_id'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Prepare user update data from input array
     *
     * @param array $data
     * @return array
     * @throws TenantNotFoundException
     * @throws InvalidTenantException
     */
    private function prepareUserUpdateData(array $data): array
    {
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        
        if (isset($data['email'])) {
            $updateData['email'] = strtolower(trim($data['email']));
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        
        // Only update password if provided and not empty
        if (isset($data['password']) && !empty(trim($data['password']))) {
            $updateData['password'] = $data['password']; // Will be hashed by User model
        }
        
        if (isset($data['tenant_id'])) {
            $this->validateTenantId($data['tenant_id']);
            $updateData['tenant_id'] = $data['tenant_id'];
        }

        return $updateData;
    }

    /**
     * Validate tenant ID exists and is a valid tenant owner
     *
     * @param int $tenantId
     * @return void
     * @throws TenantNotFoundException
     * @throws InvalidTenantException
     */
    private function validateTenantId(int $tenantId): void
    {
        $newTenant = User::find($tenantId);
        if (!$newTenant) {
            throw new TenantNotFoundException('Invalid tenant_id: Tenant not found');
        }
        // Ensure it's a tenant owner
        if ($newTenant->tenant_id !== $newTenant->id && $newTenant->tenant_id !== 0) {
            throw new InvalidTenantException('Invalid tenant_id: User is not a tenant owner');
        }
    }

    /**
     * Update user role
     *
     * @param User $user
     * @param string $roleName
     * @return void
     * @throws RoleNotFoundException
     */
    private function updateUserRole(User $user, string $roleName): void
    {
        $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
        if (!$role) {
            throw new RoleNotFoundException('Invalid role: Role not found');
        }
        $user->syncRoles([$roleName]);
    }

    /**
     * Sync linked account if user is admin
     *
     * @param User $user
     * @param bool $wasAdmin
     * @param array $data
     * @return void
     */
    private function syncLinkedAccountIfNeeded(User $user, bool $wasAdmin, array $data): void
    {
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super_admin');
        if ($isAdmin && (!$wasAdmin || isset($data['role']))) {
            $this->linkedAccountService->syncUserToProduct(
                $user,
                LinkedAccountConstants::PRODUCT_CONSOLE,
                LinkedAccountConstants::TYPE_SSO_ONLY
            );
        }
    }
}

