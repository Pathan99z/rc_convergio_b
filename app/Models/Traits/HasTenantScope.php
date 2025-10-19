<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait HasTenantScope
{
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('tenant_id', Auth::user()->tenant_id ?? Auth::id());
            }
        });

        static::creating(function ($model): void {
            if (Auth::check()) {
                // Set owner_id only if the model expects it (fillable/column present)
                $shouldSetOwner = (method_exists($model, 'isFillable') && $model->isFillable('owner_id'))
                    || (method_exists($model, 'getTable') && Schema::hasColumn($model->getTable(), 'owner_id'));
                if ($shouldSetOwner && empty($model->owner_id)) {
                    $model->owner_id = Auth::id();
                }

                if (empty($model->tenant_id)) {
                    $model->tenant_id = Auth::user()->tenant_id ?? Auth::id();
                }

                // Use TeamAccessService for team_id assignment if feature is enabled
                if (app(\App\Services\TeamAccessService::class)->enabled()) {
                    app(\App\Services\TeamAccessService::class)->setModelTeamOnCreate($model);
                } else {
                    // Legacy team_id assignment (backward compatibility)
                    $shouldSetTeam = (method_exists($model, 'isFillable') && $model->isFillable('team_id'))
                        || (method_exists($model, 'getTable') && Schema::hasColumn($model->getTable(), 'team_id'));
                    if ($shouldSetTeam && empty($model->team_id) && Auth::user()->team_id) {
                        $model->team_id = Auth::user()->team_id;
                    }
                }
            }
        });
    }

    /**
     * Scope a query to only include records for a specific tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}


