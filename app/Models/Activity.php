<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected static function booted(): void
    {
        static::bootHasTenantScope();
        
        // Ensure related_type values are properly formatted
        static::saving(function ($activity) {
            if ($activity->related_type) {
                $activity->related_type = static::normalizeRelatedType($activity->related_type);
            }
        });
    }

    protected $fillable = [
        'type',
        'subject',
        'description',
        'metadata',
        'scheduled_at',
        'completed_at',
        'status',
        'owner_id',
        'tenant_id',
        'team_id',
        'related_type',
        'related_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime:Y-m-d H:i:s',
        'completed_at' => 'datetime:Y-m-d H:i:s',
        'related_id' => 'integer',
    ];

    /**
     * Get the owner of the activity.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the tenant that owns the activity.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the team that owns the activity.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the parent related model.
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include activities for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by owner.
     */
    public function scopeByOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by related model.
     */
    public function scopeByRelated($query, $type, $id)
    {
        return $query->where('related_type', $type)->where('related_id', $id);
    }
    
    /**
     * Normalize related_type values to proper class names.
     */
    public static function normalizeRelatedType($type)
    {
        $mappings = [
            'document' => 'App\\Models\\Document',
            'deal' => 'App\\Models\\Deal',
            'contact' => 'App\\Models\\Contact',
            'company' => 'App\\Models\\Company',
            'quote' => 'App\\Models\\Quote',
        ];
        
        return $mappings[$type] ?? $type;
    }
}
