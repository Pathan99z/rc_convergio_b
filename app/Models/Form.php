<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasTenantScope;

class Form extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'name',
        'status',
        'fields',
        'field_mapping',
        'consent_required',
        'settings',
        'archived_at',
        'cancelled_at',
        'created_by',
        'tenant_id',
        'team_id',
    ];

    protected $casts = [
        'fields' => 'array',
        'field_mapping' => 'array',
        'consent_required' => 'boolean',
        'settings' => 'array',
        'archived_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the user who created the form.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the tenant that owns the form.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the submissions for the form.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Scope a query to only include forms for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by creator.
     */
    public function scopeByCreator($query, $creatorId)
    {
        return $query->where('created_by', $creatorId);
    }

    /**
     * Scope a query to search by name.
     */
    public function scopeSearchByName($query, $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }
}
