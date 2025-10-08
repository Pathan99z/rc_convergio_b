<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

class SequenceEnrollment extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'sequence_id',
        'target_type',
        'target_id',
        'target_name',
        'notes',
        'status',
        'current_step',
        'started_at',
        'completed_at',
        'cancelled_at',
        'tenant_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the sequence that owns the enrollment.
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    /**
     * Get the target model (contact, deal, or company).
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the logs for the enrollment.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(SequenceLog::class, 'enrollment_id');
    }

    /**
     * Get the tenant that owns the enrollment.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the enrollment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the enrollment.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include enrollments for a specific tenant.
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
     * Scope a query to only include active enrollments.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include paused enrollments.
     */
    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    /**
     * Scope a query to only include completed enrollments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include cancelled enrollments.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Advance to the next step in the sequence.
     */
    public function advanceStep(): void
    {
        $this->increment('current_step');
        $this->save();
    }

    /**
     * Mark the enrollment as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the enrollment as cancelled.
     */
    public function markCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Pause the enrollment.
     */
    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    /**
     * Resume the enrollment.
     */
    public function resume(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Check if the enrollment is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the enrollment is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Check if the enrollment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the enrollment is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get the next step due time.
     */
    public function getNextStepDueTime(): ?Carbon
    {
        if (!$this->started_at) {
            return null;
        }

        $totalDelayHours = 0;
        $steps = $this->sequence->orderedSteps;
        
        for ($i = 0; $i <= $this->current_step; $i++) {
            if (isset($steps[$i])) {
                $totalDelayHours += $steps[$i]->delay_hours;
            }
        }

        return $this->started_at->addHours($totalDelayHours);
    }
}
