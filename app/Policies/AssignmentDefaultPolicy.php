<?php

namespace App\Policies;

use App\Models\AssignmentDefault;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssignmentDefaultPolicy
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
    public function view(User $user, AssignmentDefault $assignmentDefault): bool
    {
        return $user->tenant_id === $assignmentDefault->tenant_id && 
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
    public function update(User $user, AssignmentDefault $assignmentDefault): bool
    {
        return $user->tenant_id === $assignmentDefault->tenant_id && 
               ($user->hasRole('admin') || $user->can('manage_assignments'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssignmentDefault $assignmentDefault): bool
    {
        return $user->tenant_id === $assignmentDefault->tenant_id && 
               ($user->hasRole('admin') || $user->can('manage_assignments'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AssignmentDefault $assignmentDefault): bool
    {
        return $user->tenant_id === $assignmentDefault->tenant_id && 
               ($user->hasRole('admin') || $user->can('manage_assignments'));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AssignmentDefault $assignmentDefault): bool
    {
        return $user->tenant_id === $assignmentDefault->tenant_id && 
               ($user->hasRole('admin') || $user->can('manage_assignments'));
    }
}
