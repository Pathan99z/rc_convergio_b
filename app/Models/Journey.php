<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Journey extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'status',
        'settings',
        'is_active',
        'tenant_id',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns the journey.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the journey.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the steps for the journey.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(JourneyStep::class)->orderBy('order_no');
    }

    /**
     * Get the executions for the journey.
     */
    public function executions(): HasMany
    {
        return $this->hasMany(JourneyExecution::class);
    }

    /**
     * Scope a query to only include journeys for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include active journeys.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include journeys with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get available journey statuses.
     */
    public static function getAvailableStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'active' => 'Active',
            'paused' => 'Paused',
            'archived' => 'Archived',
        ];
    }

    /**
     * Get journey statistics.
     */
    public function getStats(): array
    {
        $executions = $this->executions;
        
        return [
            'total_executions' => $executions->count(),
            'running_executions' => $executions->where('status', 'running')->count(),
            'completed_executions' => $executions->where('status', 'completed')->count(),
            'failed_executions' => $executions->where('status', 'failed')->count(),
            'paused_executions' => $executions->where('status', 'paused')->count(),
            'cancelled_executions' => $executions->where('status', 'cancelled')->count(),
            'completion_rate' => $executions->count() > 0 ? 
                round(($executions->where('status', 'completed')->count() / $executions->count()) * 100, 2) : 0,
        ];
    }

    /**
     * Check if the journey is active and can be executed.
     */
    public function canBeExecuted(): bool
    {
        return $this->is_active && $this->status === 'active' && $this->steps()->active()->count() > 0;
    }

    /**
     * Get the first step of the journey.
     */
    public function getFirstStep(): ?JourneyStep
    {
        return $this->steps()->active()->orderBy('order_no')->first();
    }

    /**
     * Get the next step after a given step.
     */
    public function getNextStep(JourneyStep $currentStep): ?JourneyStep
    {
        return $this->steps()
            ->active()
            ->where('order_no', '>', $currentStep->order_no)
            ->orderBy('order_no')
            ->first();
    }
}
