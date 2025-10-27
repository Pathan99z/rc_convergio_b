<?php

namespace App\Policies;

use App\Models\Sequence;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SequencePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view sequences in their tenant
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sequence $sequence): bool
    {
        return $user->tenant_id === $sequence->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Users can create sequences in their tenant
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sequence $sequence): bool
    {
        return $user->tenant_id === $sequence->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sequence $sequence): bool
    {
        return $user->tenant_id === $sequence->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sequence $sequence): bool
    {
        return $user->tenant_id === $sequence->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sequence $sequence): bool
    {
        return $user->tenant_id === $sequence->tenant_id;
    }

    /**
     * Determine whether the user can enroll targets in the sequence.
     */
    public function enroll(User $user, Sequence $sequence): bool
    {
        return $user->tenant_id === $sequence->tenant_id && $sequence->is_active;
    }
}
