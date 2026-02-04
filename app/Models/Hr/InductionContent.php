<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InductionContent extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $table = 'hr_induction_contents';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'content_type',
        'category',
        'file_url',
        'video_url',
        'support_documents',
        'target_audience_type',
        'target_departments',
        'is_mandatory',
        'due_date',
        'estimated_time',
        'status',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'support_documents' => 'array',
        'target_departments' => 'array',
        'is_mandatory' => 'boolean',
        'due_date' => 'date',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the content.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created this content.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all employee assignments for this content.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeInductionAssignment::class, 'induction_content_id');
    }

    /**
     * Scope a query to only include published content.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope a query to only include active content (published and not archived).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'published')
            ->whereNull('deleted_at');
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if content is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if content is active.
     */
    public function isActive(): bool
    {
        return $this->isPublished() && $this->deleted_at === null;
    }
}

