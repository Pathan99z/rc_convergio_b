<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class UserService
{
    /**
     * Get paginated users with filters
     */
    public function getUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();
        
        $query = User::query()
            ->with(['roles:id,name']);
        
        // Super admin can see all users, optionally filter by tenant_id
        if (!$currentUser->isSuperAdmin()) {
            $query->where('tenant_id', $currentUser->tenant_id); // ✅ CRITICAL: Filter by tenant_id
        } elseif (isset($filters['tenant_id'])) {
            // Super admin can filter by tenant_id if provided
            $query->where('tenant_id', $filters['tenant_id']);
        }

        // Apply filters
        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['email'])) {
            $query->where('email', 'like', "%{$filters['email']}%");
        }

        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        // Handle search query
        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('organization_name', 'like', "%{$searchTerm}%");
            });
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        $currentAdmin = Auth::user();
        
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'organization_name' => $currentAdmin->organization_name, // Auto-assign from current admin
            'status' => $data['status'] ?? 'active', // Default to active
            'tenant_id' => $currentAdmin->tenant_id, // Assign admin's tenant_id for team access
        ];

        $user = User::create($userData);

        // Assign roles using Spatie's assignRole method
        if (!empty($data['roles'])) {
            if (is_array($data['roles'])) {
                // If roles array contains IDs, convert to role names first
                $roleIds = $data['roles'];
                $roles = Role::whereIn('id', $roleIds)->pluck('name')->toArray();
                $user->assignRole($roles);
            } else {
                $user->assignRole($data['roles']);
            }
        }

        // Trigger email verification process
        event(new Registered($user));

        return $user->load('roles');
    }

    /**
     * Update a user
     */
    public function updateUser(User $user, array $data): bool
    {
        $userData = [];

        if (isset($data['name'])) {
            $userData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $userData['email'] = $data['email'];
        }

        if (isset($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        if (isset($data['status'])) {
            $userData['status'] = $data['status'];
        }

        // Organization name is preserved and cannot be changed via update
        // Only admins can change organization through user creation

        $updated = $user->update($userData);

        // Update roles using Spatie's syncRoles method
        if (isset($data['roles'])) {
            if (is_array($data['roles'])) {
                // If roles array contains IDs, convert to role names first
                $roleIds = $data['roles'];
                $roles = Role::whereIn('id', $roleIds)->pluck('name')->toArray();
                $user->syncRoles($roles);
            } else {
                $user->syncRoles($data['roles']);
            }
        }

        return $updated;
    }

    /**
     * Delete a user
     */
    public function deleteUser(User $user): bool
    {
        try {
            // Remove all roles first to avoid foreign key constraint issues
            $user->syncRoles([]);
            
            // Delete the user
            return $user->delete();
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error deleting user: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to delete user: ' . $e->getMessage());
        }
    }

    /**
     * Get current user profile
     */
    public function getCurrentUser(User $user): User
    {
        return $user->load(['roles:id,name']);
    }

    /**
     * Get available roles
     */
    public function getRoles(): \Illuminate\Database\Eloquent\Collection
    {
        return Role::all(['id', 'name']);
    }

    /**
     * Check if user has admin role
     */
    public function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
