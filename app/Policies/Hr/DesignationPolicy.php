<?php

namespace App\Policies\Hr;

use App\Models\Hr\Designation;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;

class DesignationPolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine if the user can view any designations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_admin') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can view the designation.
     */
    public function view(User $user, Designation $designation): bool
    {
        if (!$this->tenantAndTeamCheck($user, $designation)) {
            return false;
        }

        return $this->viewAny($user);
    }

    /**
     * Determine if the user can create designations.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('hr_admin') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the designation.
     */
    public function update(User $user, Designation $designation): bool
    {
        if (!$this->tenantAndTeamCheck($user, $designation)) {
            return false;
        }

        return $this->create($user);
    }

    /**
     * Determine if the user can delete the designation.
     */
    public function delete(User $user, Designation $designation): bool
    {
        if (!$this->tenantAndTeamCheck($user, $designation)) {
            return false;
        }

        return $this->create($user);
    }
}

