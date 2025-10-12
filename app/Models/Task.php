<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'tags',
        'owner_id',
        'assigned_to',
        'tenant_id',
        'team_id',
        'related_type',
        'related_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * Get the owner of the task.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the user assigned to the task.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the tenant that owns the task.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the team that owns the task.
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
     * Scope a query to only include tasks for a specific tenant.
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
     * Scope a query to filter by assignee.
     */
    public function scopeByAssignee($query, $assigneeId)
    {
        return $query->where('assigned_to', $assigneeId);
    }

    /**
     * Scope a query to filter by priority.
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
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
     * Scope a query to get overdue tasks.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->where('status', '!=', 'completed');
    }

    /**
     * Scope a query to get tasks due today.
     */
    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', today())
                    ->where('status', '!=', 'completed');
    }
}
