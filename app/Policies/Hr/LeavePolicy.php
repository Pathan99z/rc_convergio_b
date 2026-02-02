<?php

namespace App\Policies\Hr;

use App\Models\Hr\LeaveRequest;
use App\Models\User;

class LeavePolicy
{
    /**
     * Determine whether the user can adjust leave balance.
     */
    public function adjustBalance(User $user): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view leave requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin') ||
               $user->hasRole('line_manager') ||
               $user->hasRole('employee');
    }

    /**
     * Determine whether the user can create leave requests.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('employee') || 
               $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }
}

