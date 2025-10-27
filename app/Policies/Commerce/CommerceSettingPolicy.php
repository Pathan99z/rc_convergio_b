<?php

namespace App\Policies\Commerce;

use App\Models\Commerce\CommerceSetting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommerceSettingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all settings
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can view settings from their tenant
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CommerceSetting $setting): bool
    {
        // Admin can view any setting
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can only view settings from their tenant
        return $user->tenant_id === $setting->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admin can create settings
        if ($user->hasRole('admin')) {
            return true;
        }

        // Any authenticated user can create settings for their tenant
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CommerceSetting $setting): bool
    {
        // Admin can update any setting
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can only update settings from their tenant
        return $user->tenant_id === $setting->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CommerceSetting $setting): bool
    {
        // Admin can delete any setting
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can only delete settings from their tenant
        return $user->tenant_id === $setting->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CommerceSetting $setting): bool
    {
        return $this->update($user, $setting);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CommerceSetting $setting): bool
    {
        return $this->delete($user, $setting);
    }
}
