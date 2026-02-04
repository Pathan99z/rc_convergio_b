<?php

namespace App\Policies\Hr;

use App\Models\Hr\KpiTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;

class KpiTemplatePolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine if the user can view any KPI templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_admin') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can view the KPI template.
     */
    public function view(User $user, KpiTemplate $kpiTemplate): bool
    {
        if (!$this->tenantAndTeamCheck($user, $kpiTemplate)) {
            return false;
        }

        return $this->viewAny($user);
    }

    /**
     * Determine if the user can create KPI templates.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('hr_admin') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the KPI template.
     */
    public function update(User $user, KpiTemplate $kpiTemplate): bool
    {
        if (!$this->tenantAndTeamCheck($user, $kpiTemplate)) {
            return false;
        }

        return $this->create($user);
    }

    /**
     * Determine if the user can delete the KPI template.
     */
    public function delete(User $user, KpiTemplate $kpiTemplate): bool
    {
        if (!$this->tenantAndTeamCheck($user, $kpiTemplate)) {
            return false;
        }

        return $this->create($user);
    }
}
