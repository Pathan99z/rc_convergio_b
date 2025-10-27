<?php

namespace App\Policies\Commerce;

use App\Models\Commerce\CommercePaymentLink;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommercePaymentLinkPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all payment links
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can view payment links from their tenant
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CommercePaymentLink $paymentLink): bool
    {
        // Admin can view any payment link
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can view payment links from their tenant
        if ($user->tenant_id !== $paymentLink->tenant_id) {
            return false;
        }

        // Check team access if enabled
        if (config('features.team_access_enabled', false)) {
            // If user has a team, they can view payment links from their team
            if ($user->team_id && $paymentLink->team_id) {
                return $user->team_id === $paymentLink->team_id;
            }
            
            // If user has no team, they can only view payment links with no team or owned by them
            if (!$user->team_id) {
                return !$paymentLink->team_id || $paymentLink->owner_id === $user->id;
            }
        }

        // Owner can always view their payment links
        if ($paymentLink->owner_id === $user->id) {
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
        // Admin can create payment links
        if ($user->hasRole('admin')) {
            return true;
        }

        // Any authenticated user can create payment links
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CommercePaymentLink $paymentLink): bool
    {
        // Admin can update any payment link
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can only update payment links from their tenant
        if ($user->tenant_id !== $paymentLink->tenant_id) {
            return false;
        }

        // Check team access if enabled
        if (config('features.team_access_enabled', false)) {
            // If user has a team, they can update payment links from their team
            if ($user->team_id && $paymentLink->team_id) {
                return $user->team_id === $paymentLink->team_id;
            }
            
            // If user has no team, they can only update payment links with no team or owned by them
            if (!$user->team_id) {
                return !$paymentLink->team_id || $paymentLink->owner_id === $user->id;
            }
        }

        // Owner can always update their payment links
        if ($paymentLink->owner_id === $user->id) {
            return true;
        }

        // Default: allow if same tenant
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CommercePaymentLink $paymentLink): bool
    {
        // Admin can delete any payment link
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can only delete payment links from their tenant
        if ($user->tenant_id !== $paymentLink->tenant_id) {
            return false;
        }

        // Check team access if enabled
        if (config('features.team_access_enabled', false)) {
            // If user has a team, they can delete payment links from their team
            if ($user->team_id && $paymentLink->team_id) {
                return $user->team_id === $paymentLink->team_id;
            }
            
            // If user has no team, they can only delete payment links with no team or owned by them
            if (!$user->team_id) {
                return !$paymentLink->team_id || $paymentLink->owner_id === $user->id;
            }
        }

        // Owner can always delete their payment links
        if ($paymentLink->owner_id === $user->id) {
            return true;
        }

        // Default: allow if same tenant
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CommercePaymentLink $paymentLink): bool
    {
        return $this->update($user, $paymentLink);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CommercePaymentLink $paymentLink): bool
    {
        return $this->delete($user, $paymentLink);
    }
}
