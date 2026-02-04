<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingTask extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $table = 'hr_onboarding_tasks';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'title',
        'description',
        'task_type',
        'assigned_to',
        'status',
        'priority',
        'due_date',
        'completed_by',
        'completed_at',
        'notes',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the task.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the employee this task belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the user assigned to complete this task.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who completed this task.
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the user who created this task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include pending tasks.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include completed tasks.
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
     * Scope a query to filter by task type.
     */
    public function scopeByTaskType($query, string $taskType)
    {
        return $query->where('task_type', $taskType);
    }

    /**
     * Scope a query to filter by priority.
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Check if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark the task as completed.
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

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && 
               $this->due_date->isPast() && 
               !$this->isCompleted();
    }
}


