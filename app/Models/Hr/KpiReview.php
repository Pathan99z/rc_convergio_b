<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiReview extends Model
{
    use HasFactory;

    protected $table = 'hr_kpi_reviews';

    protected $fillable = [
        'kpi_assignment_id',
        'review_type',
        'reviewed_by',
        'final_score',
        'grade',
        'comments',
        'submitted_at',
    ];

    protected $casts = [
        'final_score' => 'decimal:2',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the KPI assignment this review belongs to.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(KpiAssignment::class, 'kpi_assignment_id');
    }

    /**
     * Get the user who reviewed (employee or manager).
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get all review items (individual KPI scores).
     */
    public function items(): HasMany
    {
        return $this->hasMany(KpiReviewItem::class, 'kpi_review_id');
    }

    /**
     * Check if review is submitted.
     */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Check if this is a self review.
     */
    public function isSelfReview(): bool
    {
        return $this->review_type === 'self_review';
    }

    /**
     * Check if this is a manager review.
     */
    public function isManagerReview(): bool
    {
        return $this->review_type === 'manager_review';
    }
}

