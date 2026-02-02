<?php

namespace App\Policies\Hr;

use App\Models\Hr\Payslip;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;

class PayslipPolicy
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
               $user->hasRole('employee') ||
               $user->hasRole('finance');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payslip $payslip): bool
    {
        if (!$this->tenantAndTeamCheck($user, $payslip)) {
            return false;
        }

        // HR Admin, System Admin, and Tenant Admin can view all
        if ($user->hasRole('hr_admin') || 
            $user->hasRole('system_admin') || 
            $user->hasRole('admin')) {
            return true;
        }

        // Finance can view metadata (read-only)
        if ($user->hasRole('finance')) {
            return true;
        }

        // Employee can view only their own
        if ($user->hasRole('employee')) {
            return $payslip->employee->user_id === $user->id;
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
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payslip $payslip): bool
    {
        if (!$this->tenantAndTeamCheck($user, $payslip)) {
            return false;
        }

        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }
}

