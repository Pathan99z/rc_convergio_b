<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ChecksTenantAndTeam Trait
 * 
 * This trait provides a centralized method for checking tenant and team access
 * in policies. It implements the team access logic consistently across all policies.
 * 
 * The method checks:
 * 1. Basic tenant validation (user and model must have same tenant_id)
 * 2. If team access is enabled and model has team_id:
 *    - Allow if model.team_id is null (backward compatibility)
 *    - Allow if model.team_id matches user.team_id
 *    - Allow if user has admin role (admin override)
 * 3. If team access is disabled, only check tenant
 */
trait ChecksTenantAndTeam
{
    /**
     * Check if a user can access a model based on tenant and team rules.
     * 
     * @param User $user
     * @param Model $model
     * @return bool
     */
    protected function tenantAndTeamCheck(User $user, Model $model): bool
    {
        // Basic tenant validation - user and model must have same tenant_id
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        // If team access is disabled, only check tenant
        if (!config('features.team_access_enabled', false)) {
            return true;
        }

        // If model doesn't have team_id, allow access (backward compatibility)
        if (!isset($model->team_id) || is_null($model->team_id)) {
            return true;
        }

        // Allow access if user is admin (admin override)
        if ($user->hasRole('admin')) {
            return true;
        }

        // Allow access if user and model have same team_id
        return $model->team_id === $user->team_id;
    }
}


