<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceLog extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'enrollment_id',
        'step_id',
        'action_performed',
        'performed_at',
        'status',
        'notes',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the enrollment that owns the log.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(SequenceEnrollment::class);
    }

    /**
     * Get the step that owns the log.
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(SequenceStep::class);
    }

    /**
     * Get the tenant that owns the log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the log.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include logs for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include successful logs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed logs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include skipped logs.
     */
    public function scopeSkipped($query)
    {
        return $query->where('status', 'skipped');
    }
}
