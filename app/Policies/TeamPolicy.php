<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization, ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any teams.
     */
    public function viewAny(User $user): bool
    {
        // Super admin can view all teams
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        return $user->tenant_id != null;
    }

    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        return $this->tenantAndTeamCheck($user, $team);
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user): bool
    {
        // Super admin and tenant admins can create teams
        return $user->isSuperAdmin() || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $this->canModifyTeam($user, $team);
    }

    /**
     * Determine whether the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        return $this->canModifyTeam($user, $team);
    }

    /**
     * Determine whether the user can manage team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $this->canManageTeamMembers($user, $team);
    }

    /**
     * Determine whether the user can add members to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        return $this->canManageTeamMembers($user, $team);
    }

    /**
     * Determine whether the user can remove members from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $this->canManageTeamMembers($user, $team);
    }

    /**
     * Check if user can modify (update/delete) a team.
     * Super admin can modify any team, otherwise user must pass tenant/team check and have admin role.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    private function canModifyTeam(User $user, Team $team): bool
    {
        // Super admin can modify any team
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        return $this->tenantAndTeamCheck($user, $team) && $user->hasRole('admin');
    }

    /**
     * Check if user can manage team members (add/remove/manage).
     * Super admin can manage any team, otherwise user must be in same tenant and have admin role.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    private function canManageTeamMembers(User $user, Team $team): bool
    {
        // Super admin can manage any team
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        return $user->tenant_id === $team->tenant_id && $user->hasRole('admin');
    }
}
