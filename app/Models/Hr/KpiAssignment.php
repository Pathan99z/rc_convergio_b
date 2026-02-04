<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class KpiAssignment extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $table = 'hr_kpi_assignments';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'kpi_template_id',
        'review_period_value',
        'review_period_start',
        'review_period_end',
        'status',
        'assigned_by',
    ];

    protected $casts = [
        'review_period_start' => 'date',
        'review_period_end' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the assignment.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the employee this assignment is for.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the KPI template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    /**
     * Get the user who assigned this.
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the self review for this assignment.
     */
    public function selfReview(): HasOne
    {
        return $this->hasOne(KpiReview::class, 'kpi_assignment_id')
            ->where('review_type', 'self_review');
    }

    /**
     * Get the manager review for this assignment.
     */
    public function managerReview(): HasOne
    {
        return $this->hasOne(KpiReview::class, 'kpi_assignment_id')
            ->where('review_type', 'manager_review');
    }

    /**
     * Get all reviews for this assignment.
     */
    public function reviews()
    {
        return $this->hasMany(KpiReview::class, 'kpi_assignment_id');
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
     * Check if self review is submitted.
     */
    public function hasSelfReview(): bool
    {
        return $this->selfReview()->exists();
    }

    /**
     * Check if manager review is submitted.
     */
    public function hasManagerReview(): bool
    {
        return $this->managerReview()->exists();
    }
}

