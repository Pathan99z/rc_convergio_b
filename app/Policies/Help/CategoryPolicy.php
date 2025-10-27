<?php

namespace App\Policies\Help;

use App\Models\Help\Category;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;

class CategoryPolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'service_manager', 'service_agent']);
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, Category $category): bool
    {
        if (!$this->tenantAndTeamCheck($user, $category)) {
            return false;
        }

        return $user->hasRole(['admin', 'service_manager', 'service_agent']);
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'service_manager']);
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        if (!$this->tenantAndTeamCheck($user, $category)) {
            return false;
        }

        return $user->hasRole(['admin', 'service_manager']);
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        if (!$this->tenantAndTeamCheck($user, $category)) {
            return false;
        }

        // Prevent deletion if category has articles
        if ($category->articles()->count() > 0) {
            return false;
        }

        return $user->hasRole(['admin', 'service_manager']);
    }
}
