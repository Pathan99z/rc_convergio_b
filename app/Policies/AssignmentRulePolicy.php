<?php

namespace App\Policies;

use App\Models\AssignmentRule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssignmentRulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('manage_assignments');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssignmentRule $assignmentRule): bool
    {
        return $user->tenant_id === $assignmentRule->tenant_id && 
               ($user->hasRole('admin') || $user->can('manage_assignments'));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('manage_assignments');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssignmentRule $assignmentRule): bool
    {
        return $user->tenant_id === $assignmentRule->tenant_id && 
               ($user->hasRole('admin') || $user->can('manage_assignments'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssignmentRule $assignmentRule): bool
    {
        return $user->tenant_id === $assignmentRule->tenant_id && 
               ($user->hasRole('admin') || $user->can('manage_assignments'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AssignmentRule $assignmentRule): bool
    {
        return $user->tenant_id === $assignmentRule->tenant_id && 
               ($user->hasRole('admin') || $user->can('manage_assignments'));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AssignmentRule $assignmentRule): bool
    {
        return $user->tenant_id === $assignmentRule->tenant_id && 
               ($user->hasRole('admin') || $user->can('manage_assignments'));
    }
}
