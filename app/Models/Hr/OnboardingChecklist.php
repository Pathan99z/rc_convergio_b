<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingChecklist extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $table = 'hr_onboarding_checklists';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'checklist_template_id',
        'status',
        'assigned_to',
        'completed_by',
        'completed_at',
        'due_date',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'due_date' => 'date',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the checklist.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the employee this checklist belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the template this checklist is based on.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingChecklistTemplate::class, 'checklist_template_id');
    }

    /**
     * Get the user assigned to complete this checklist item.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who completed this checklist item.
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Scope a query to only include pending items.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include completed items.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if the checklist item is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark the checklist item as completed.
     */
    public function markAsCompleted(?int $userId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_by' => $userId ?? auth()->id(),
            'completed_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);
    }
}

