<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait HasTenantScope
{
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (auth()->check()) {
                $builder->where('tenant_id', auth()->user()->tenant_id ?? auth()->id());
            }
        });

        static::creating(function ($model): void {
            if (auth()->check()) {
                // Set owner_id only if the model expects it (fillable/column present)
                $shouldSetOwner = (method_exists($model, 'isFillable') && $model->isFillable('owner_id'))
                    || (method_exists($model, 'getTable') && Schema::hasColumn($model->getTable(), 'owner_id'));
                if ($shouldSetOwner && empty($model->owner_id)) {
                    $model->owner_id = auth()->id();
                }

                if (empty($model->tenant_id)) {
                    $model->tenant_id = auth()->user()->tenant_id ?? auth()->id();
                }
            }
        });
    }
}


