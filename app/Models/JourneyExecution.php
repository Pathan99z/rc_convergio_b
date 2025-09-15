<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JourneyExecution extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'journey_id',
        'contact_id',
        'current_step_id',
        'status',
        'execution_data',
        'started_at',
        'completed_at',
        'next_step_at',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'execution_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_step_at' => 'datetime',
    ];

    /**
     * Get the journey that owns the execution.
     */
    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    /**
     * Get the contact that is executing the journey.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the current step of the execution.
     */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(JourneyStep::class, 'current_step_id');
    }

    /**
     * Get the tenant that owns the execution.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include executions for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include executions with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include running executions.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope a query to only include executions ready for next step.
     */
    public function scopeReadyForNextStep($query)
    {
        return $query->where('status', 'running')
            ->where('next_step_at', '<=', now());
    }

    /**
     * Get available execution statuses.
     */
    public static function getAvailableStatuses(): array
    {
        return [
            'running' => 'Running',
            'paused' => 'Paused',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ];
    }

    /**
     * Check if the execution is ready for the next step.
     */
    public function isReadyForNextStep(): bool
    {
        return $this->status === 'running' && 
               $this->next_step_at && 
               $this->next_step_at <= now();
    }

    /**
     * Move to the next step.
     */
    public function moveToNextStep(JourneyStep $nextStep): void
    {
        $this->update([
            'current_step_id' => $nextStep->id,
            'next_step_at' => $this->calculateNextStepTime($nextStep),
        ]);
    }

    /**
     * Complete the execution.
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'next_step_at' => null,
        ]);
    }

    /**
     * Pause the execution.
     */
    public function pause(): void
    {
        $this->update([
            'status' => 'paused',
        ]);
    }

    /**
     * Resume the execution.
     */
    public function resume(): void
    {
        $this->update([
            'status' => 'running',
        ]);
    }

    /**
     * Cancel the execution.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'next_step_at' => null,
        ]);
    }

    /**
     * Mark the execution as failed.
     */
    public function markAsFailed(string $reason = null): void
    {
        $executionData = $this->execution_data ?? [];
        if ($reason) {
            $executionData['failure_reason'] = $reason;
        }

        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'execution_data' => $executionData,
            'next_step_at' => null,
        ]);
    }

    /**
     * Calculate when the next step should execute.
     */
    private function calculateNextStepTime(JourneyStep $step): ?\DateTime
    {
        if ($step->step_type === 'wait') {
            $config = $step->config;
            $days = $config['days'] ?? 0;
            $hours = $config['hours'] ?? 0;
            $minutes = $config['minutes'] ?? 0;
            
            return now()->addDays($days)->addHours($hours)->addMinutes($minutes);
        }

        // For immediate steps, execute now
        return now();
    }

    /**
     * Get execution progress percentage.
     */
    public function getProgressPercentage(): float
    {
        if (!$this->currentStep) {
            return 0;
        }

        $totalSteps = $this->journey->steps()->active()->count();
        $currentStepOrder = $this->currentStep->order_no;
        
        return $totalSteps > 0 ? round(($currentStepOrder / $totalSteps) * 100, 2) : 0;
    }

    /**
     * Get execution duration.
     */
    public function getDuration(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $endTime->diffInMinutes($this->started_at);
    }
}
