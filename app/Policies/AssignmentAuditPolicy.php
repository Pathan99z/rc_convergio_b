<?php

namespace App\Policies;

use App\Models\AssignmentAudit;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;
use Illuminate\Auth\Access\Response;

class AssignmentAuditPolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only allow admins to view any assignment audits
        // Audit logs are typically sensitive and should be restricted to admin users
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssignmentAudit $assignmentAudit): bool
    {
        // Users can view a specific audit if they are an admin
        // or if the audit belongs to their tenant and potentially their team
        if ($user->hasRole('admin')) {
            return true;
        }

        // For non-admins, check if the audit belongs to their tenant and team
        return $user->tenant_id === $assignmentAudit->tenant_id &&
               $this->tenantAndTeamCheck($user, $assignmentAudit);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Assignment audits are created automatically by the system, not manually by users
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssignmentAudit $assignmentAudit): bool
    {
        // Assignment audits should not be updated after creation
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssignmentAudit $assignmentAudit): bool
    {
        // Assignment audits should not be deleted
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AssignmentAudit $assignmentAudit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AssignmentAudit $assignmentAudit): bool
    {
        return false;
    }
}


