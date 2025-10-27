<?php

namespace App\Policies\Commerce;

use App\Models\Commerce\CommerceOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommerceOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all orders
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can view orders from their tenant
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CommerceOrder $order): bool
    {
        // Admin can view any order
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can view orders from their tenant
        if ($user->tenant_id !== $order->tenant_id) {
            return false;
        }

        // Check team access if enabled
        if (config('features.team_access_enabled', false)) {
            // If user has a team, they can view orders from their team
            if ($user->team_id && $order->team_id) {
                return $user->team_id === $order->team_id;
            }
            
            // If user has no team, they can only view orders with no team or owned by them
            if (!$user->team_id) {
                return !$order->team_id || $order->owner_id === $user->id;
            }
        }

        // Owner can always view their orders
        if ($order->owner_id === $user->id) {
            return true;
        }

        // Default: allow if same tenant
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admin can create orders
        if ($user->hasRole('admin')) {
            return true;
        }

        // Any authenticated user can create orders
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CommerceOrder $order): bool
    {
        // Admin can update any order
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can only update orders from their tenant
        if ($user->tenant_id !== $order->tenant_id) {
            return false;
        }

        // Check team access if enabled
        if (config('features.team_access_enabled', false)) {
            // If user has a team, they can update orders from their team
            if ($user->team_id && $order->team_id) {
                return $user->team_id === $order->team_id;
            }
            
            // If user has no team, they can only update orders with no team or owned by them
            if (!$user->team_id) {
                return !$order->team_id || $order->owner_id === $user->id;
            }
        }

        // Owner can always update their orders
        if ($order->owner_id === $user->id) {
            return true;
        }

        // Default: allow if same tenant
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CommerceOrder $order): bool
    {
        // Admin can delete any order
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can only delete orders from their tenant
        if ($user->tenant_id !== $order->tenant_id) {
            return false;
        }

        // Check team access if enabled
        if (config('features.team_access_enabled', false)) {
            // If user has a team, they can delete orders from their team
            if ($user->team_id && $order->team_id) {
                return $user->team_id === $order->team_id;
            }
            
            // If user has no team, they can only delete orders with no team or owned by them
            if (!$user->team_id) {
                return !$order->team_id || $order->owner_id === $user->id;
            }
        }

        // Owner can always delete their orders
        if ($order->owner_id === $user->id) {
            return true;
        }

        // Default: allow if same tenant
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CommerceOrder $order): bool
    {
        return $this->update($user, $order);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CommerceOrder $order): bool
    {
        return $this->delete($user, $order);
    }
}
