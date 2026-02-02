<?php

namespace App\Policies\Hr;

use App\Models\Hr\Department;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;

class DepartmentPolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine if the user can view any departments.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_admin') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can view the department.
     */
    public function view(User $user, Department $department): bool
    {
        if (!$this->tenantAndTeamCheck($user, $department)) {
            return false;
        }

        return $this->viewAny($user);
    }

    /**
     * Determine if the user can create departments.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('hr_admin') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the department.
     */
    public function update(User $user, Department $department): bool
    {
        if (!$this->tenantAndTeamCheck($user, $department)) {
            return false;
        }

        return $this->create($user);
    }

    /**
     * Determine if the user can delete the department.
     */
    public function delete(User $user, Department $department): bool
    {
        if (!$this->tenantAndTeamCheck($user, $department)) {
            return false;
        }

        return $this->create($user);
    }
}

