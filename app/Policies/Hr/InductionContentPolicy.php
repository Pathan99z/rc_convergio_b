<?php

namespace App\Policies\Hr;

use App\Models\Hr\InductionContent;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;

class InductionContentPolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InductionContent $content): bool
    {
        if (!$this->tenantAndTeamCheck($user, $content)) {
            return false;
        }

        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
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
    public function update(User $user, InductionContent $content): bool
    {
        if (!$this->tenantAndTeamCheck($user, $content)) {
            return false;
        }

        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InductionContent $content): bool
    {
        if (!$this->tenantAndTeamCheck($user, $content)) {
            return false;
        }

        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }
}

