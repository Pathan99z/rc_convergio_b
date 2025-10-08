<?php

namespace App\Policies;

use App\Models\SequenceEnrollment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SequenceEnrollmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // HasTenantScope automatically filters by tenant
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SequenceEnrollment $sequenceEnrollment): bool
    {
        // HasTenantScope ensures only tenant records are accessible
        // Additional check for extra security
        return $user->tenant_id === $sequenceEnrollment->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // HasTenantScope automatically sets tenant_id
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SequenceEnrollment $sequenceEnrollment): bool
    {
        // HasTenantScope ensures only tenant records are accessible
        // Additional check for extra security
        return $user->tenant_id === $sequenceEnrollment->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SequenceEnrollment $sequenceEnrollment): bool
    {
        // HasTenantScope ensures only tenant records are accessible
        // Additional check for extra security
        return $user->tenant_id === $sequenceEnrollment->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SequenceEnrollment $sequenceEnrollment): bool
    {
        // HasTenantScope ensures only tenant records are accessible
        // Additional check for extra security
        return $user->tenant_id === $sequenceEnrollment->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SequenceEnrollment $sequenceEnrollment): bool
    {
        // HasTenantScope ensures only tenant records are accessible
        // Additional check for extra security
        return $user->tenant_id === $sequenceEnrollment->tenant_id;
    }
}
