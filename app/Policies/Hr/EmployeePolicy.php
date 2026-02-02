<?php

namespace App\Policies\Hr;

use App\Models\Hr\Employee;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;

class EmployeePolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin') ||
               $user->hasRole('line_manager') ||
               $user->hasRole('employee') ||
               $user->hasRole('finance');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Employee $employee): bool
    {
        if (!$this->tenantAndTeamCheck($user, $employee)) {
            return false;
        }

        // HR Admin, System Admin, and Tenant Admin can view all
        if ($user->hasRole('hr_admin') || 
            $user->hasRole('system_admin') || 
            $user->hasRole('admin')) {
            return true;
        }

        // Finance can view (read-only)
        if ($user->hasRole('finance')) {
            return true;
        }

        // Line Manager can view team members
        if ($user->hasRole('line_manager')) {
            // If employee has no team_id, line manager cannot view (unless they also have no team)
            if (is_null($employee->team_id)) {
                return is_null($user->team_id);
            }
            return $employee->team_id === $user->team_id;
        }

        // Employee can view only themselves
        if ($user->hasRole('employee')) {
            return $employee->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Employee $employee): bool
    {
        if (!$this->tenantAndTeamCheck($user, $employee)) {
            return false;
        }

        // HR Admin, System Admin, and Tenant Admin can update all
        if ($user->hasRole('hr_admin') || 
            $user->hasRole('system_admin') || 
            $user->hasRole('admin')) {
            return true;
        }

        // Line Manager can update team members (limited fields)
        if ($user->hasRole('line_manager')) {
            return $employee->team_id === $user->team_id;
        }

        // Employee can update only their own contact details
        if ($user->hasRole('employee')) {
            return $employee->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Employee $employee): bool
    {
        if (!$this->tenantAndTeamCheck($user, $employee)) {
            return false;
        }

        // Only HR Admin, System Admin, and Tenant Admin can archive
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view admin dashboard.
     */
    public function viewAdminDashboard(User $user): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view manager dashboard.
     */
    public function viewManagerDashboard(User $user): bool
    {
        return $user->hasRole('line_manager') || 
               $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }
}

