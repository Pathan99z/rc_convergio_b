<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * TeamAccessService
 * 
 * Central service for managing team-based access control.
 * This service provides methods to check if team access is enabled,
 * get the current user's team ID, apply team filters to queries,
 * and set team IDs on models during creation.
 * 
 * All team access logic is gated behind the TEAM_ACCESS_ENABLED feature flag.
 * When disabled, all methods return safe defaults that maintain existing behavior.
 */
class TeamAccessService
{
    /**
     * Check if team access is enabled via feature flag.
     * 
     * @return bool
     */
    public function enabled(): bool
    {
        return config('features.team_access_enabled', false);
    }

    /**
     * Get the current authenticated user's team ID.
     * 
     * @return int|null
     */
    public function currentTeamId(): ?int
    {
        if (!Auth::check()) {
            return null;
        }

        $user = Auth::user();
        return $user->team_id ?? null;
    }

    /**
     * Apply team filter to a query builder.
     * 
     * Only applies the filter if:
     * - Team access is enabled
     * - Current user has a team_id
     * - The specified column exists in the table
     * 
     * @param Builder $query
     * @param string $column Column name to filter by (default: 'team_id')
     * @return Builder
     */
    public function applyTeamFilter(Builder $query, string $column = 'team_id'): Builder
    {
        if (!$this->enabled()) {
            return $query;
        }

        // Check if the column exists in the table
        $table = $query->getModel()->getTable();
        if (!Schema::hasColumn($table, $column)) {
            return $query;
        }

        $teamId = $this->currentTeamId();
        
        // If user has no team assigned, they can only see items with team_id = null
        // This ensures users without teams can see public/global items
        if (!$teamId) {
            return $query->whereNull($column);
        }

        // ✅ FIX: Show same team contacts + admin-created contacts (team_id = null)
        return $query->where(function ($q) use ($column, $teamId) {
            $q->where($column, $teamId)           // Same team contacts
              ->orWhereNull($column);             // Admin-created contacts (backward compatibility)
        });
    }

    /**
     * Set team_id on a model during creation if conditions are met.
     * 
     * Sets team_id if:
     * - Team access is enabled
     * - User is authenticated and has a team_id
     * - Model has team_id attribute and it's currently empty
     * 
     * @param Model $model
     * @return void
     */
    public function setModelTeamOnCreate(Model $model): void
    {
        if (!$this->enabled()) {
            return;
        }

        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();
        if (!$user->team_id) {
            return;
        }

        // Check if model has team_id column and it's empty
        $table = $model->getTable();
        if (Schema::hasColumn($table, 'team_id') && empty($model->team_id)) {
            $model->team_id = $user->team_id;
            
            Log::info('TeamAccessService: Set team_id on model creation', [
                'model_class' => get_class($model),
                'model_id' => $model->id ?? null,
                'team_id' => $user->team_id,
                'user_id' => $user->id,
                'tenant_id' => $model->tenant_id ?? null
            ]);
        }
    }

    /**
     * Check if a user can access a model based on team rules.
     * 
     * @param \App\Models\User $user
     * @param Model $model
     * @return bool
     */
    public function canAccessModel(\App\Models\User $user, Model $model): bool
    {
        // Basic tenant validation
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        // If team access is disabled, only check tenant
        if (!$this->enabled()) {
            return true;
        }

        // If model doesn't have team_id, allow access (backward compatibility)
        if (!isset($model->team_id) || is_null($model->team_id)) {
            return true;
        }

        // Allow access if user is admin or has same team_id
        return $user->hasRole('admin') || $model->team_id === $user->team_id;
    }

    /**
     * Get team filter for owner-based modules (deals, tasks).
     * 
     * Returns a closure that can be used in OR conditions with owner/permission checks.
     * 
     * @param \App\Models\User $user
     * @param string $column Column name to filter by (default: 'team_id')
     * @return \Closure|null
     */
    public function getTeamFilterForOwnerModules(\App\Models\User $user, string $column = 'team_id'): ?\Closure
    {
        if (!$this->enabled()) {
            return null;
        }

        if (!$user->team_id) {
            return null;
        }

        return function ($query) use ($user, $column) {
            $query->where($column, $user->team_id);
        };
    }
}
