<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Deal extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'title',
        'description',
        'value',
        'currency',
        'status',
        'expected_close_date',
        'closed_date',
        'close_reason',
        'probability',
        'tags',
        'pipeline_id',
        'stage_id',
        'owner_id',
        'contact_id',
        'company_id',
        'tenant_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expected_close_date' => 'date',
        'closed_date' => 'date',
        'tags' => 'array',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the pipeline that owns the deal.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Get the stage that owns the deal.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Get the owner of the deal.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the contact associated with the deal.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the company associated with the deal.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the tenant that owns the deal.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the activities for the deal.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'related');
    }

    /**
     * Get the tasks for the deal.
     */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'related');
    }

    /**
     * Scope a query to only include deals for a specific tenant.
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
     * Scope a query to filter by pipeline.
     */
    public function scopeByPipeline($query, $pipelineId)
    {
        return $query->where('pipeline_id', $pipelineId);
    }

    /**
     * Scope a query to filter by stage.
     */
    public function scopeByStage($query, $stageId)
    {
        return $query->where('stage_id', $stageId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
