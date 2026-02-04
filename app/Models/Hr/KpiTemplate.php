<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KpiTemplate extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $table = 'hr_kpi_templates';

    protected $fillable = [
        'tenant_id',
        'name',
        'department_id',
        'designation_id',
        'review_period',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the template.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the designation.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    /**
     * Get the user who created the template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all KPI items for this template.
     */
    public function items(): HasMany
    {
        return $this->hasMany(KpiTemplateItem::class, 'kpi_template_id')->orderBy('order');
    }

    /**
     * Get all assignments for this template.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(KpiAssignment::class, 'kpi_template_id');
    }

    /**
     * Scope a query to only include published templates.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope a query to only include draft templates.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Check if template is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Get total weight of all items.
     */
    public function getTotalWeightAttribute(): float
    {
        return $this->items()->sum('weight');
    }
}

