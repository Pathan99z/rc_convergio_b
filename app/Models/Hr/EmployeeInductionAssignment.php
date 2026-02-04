<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeInductionAssignment extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $table = 'hr_employee_induction_assignments';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'induction_content_id',
        'status',
        'assigned_at',
        'started_at',
        'completed_at',
        'acknowledged_by',
        'due_date',
        'is_overdue',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_date' => 'date',
        'is_overdue' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();

        // Auto-update is_overdue flag
        static::saving(function ($assignment) {
            if ($assignment->due_date && $assignment->status !== 'completed') {
                $assignment->is_overdue = now()->greaterThan($assignment->due_date);
            } else {
                $assignment->is_overdue = false;
            }
        });
    }

    /**
     * Get the tenant that owns the assignment.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the employee this assignment belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the induction content for this assignment.
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(InductionContent::class, 'induction_content_id');
    }

    /**
     * Get the user who acknowledged this assignment.
     */
    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Scope a query to only include pending assignments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include completed assignments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include overdue assignments.
     */
    public function scopeOverdue($query)
    {
        return $query->where('is_overdue', true)
            ->where('status', '!=', 'completed');
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if assignment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if assignment is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->is_overdue && !$this->isCompleted();
    }

    /**
     * Mark assignment as started.
     */
    public function markAsStarted(): void
    {
        if ($this->status === 'pending') {
            $this->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }
    }

    /**
     * Mark assignment as completed.
     */
    public function markAsCompleted(?int $acknowledgedBy = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'acknowledged_by' => $acknowledgedBy ?? auth()->id(),
            'notes' => $notes,
            'is_overdue' => false,
        ]);
    }
}

