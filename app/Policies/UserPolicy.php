<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Super admin and tenant admins can view all users
        return $user->isSuperAdmin() || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Super admin can view any user
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Users can view their own profile, admins can view any profile in their tenant
        return $user->id === $model->id || ($user->hasRole('admin') && $user->tenant_id === $model->tenant_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Super admin and tenant admins can create new users
        return $user->isSuperAdmin() || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Super admin can update any user
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Users can update their own profile, admins can update any profile in their tenant
        return $user->id === $model->id || ($user->hasRole('admin') && $user->tenant_id === $model->tenant_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Super admin can delete any user except themselves
        if ($user->isSuperAdmin()) {
            return $user->id !== $model->id;
        }
        
        // Tenant admins can delete users in their tenant, and users cannot delete themselves
        return $user->hasRole('admin') && $user->tenant_id === $model->tenant_id && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // Super admin can restore any user
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Tenant admins can restore deleted users in their tenant
        return $user->hasRole('admin') && $user->tenant_id === $model->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Super admin can permanently delete any user except themselves
        if ($user->isSuperAdmin()) {
            return $user->id !== $model->id;
        }
        
        // Tenant admins can permanently delete users in their tenant, and users cannot delete themselves
        return $user->hasRole('admin') && $user->tenant_id === $model->tenant_id && $user->id !== $model->id;
    }
}
