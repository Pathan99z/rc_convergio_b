<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'config',
        'schedule',
        'format',
        'status',
        'last_run_at',
        'next_run_at',
        'last_result',
        'error_message',
        'run_count',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'config' => 'array',
        'schedule' => 'array',
        'last_result' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'run_count' => 'integer',
    ];

    /**
     * Get the tenant that owns the report.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the report.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include reports for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include active reports.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include scheduled reports.
     */
    public function scopeScheduled($query)
    {
        return $query->where('type', 'scheduled');
    }

    /**
     * Check if report is due to run.
     */
    public function isDueToRun(): bool
    {
        return $this->status === 'active' && 
               $this->next_run_at && 
               $this->next_run_at->isPast();
    }

    /**
     * Mark report as running.
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'last_run_at' => now(),
        ]);
    }

    /**
     * Mark report as completed.
     */
    public function markAsCompleted(array $result = null): void
    {
        $this->update([
            'status' => 'active',
            'last_result' => $result,
            'error_message' => null,
            'run_count' => $this->run_count + 1,
        ]);

        $this->calculateNextRun();
    }

    /**
     * Mark report as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Calculate next run time based on schedule.
     */
    public function calculateNextRun(): void
    {
        if ($this->type !== 'scheduled' || !$this->schedule) {
            return;
        }

        $frequency = $this->schedule['frequency'] ?? 'daily';
        $time = $this->schedule['time'] ?? '09:00';

        $nextRun = match ($frequency) {
            'hourly' => now()->addHour(),
            'daily' => now()->addDay()->setTimeFromTimeString($time),
            'weekly' => now()->addWeek()->setTimeFromTimeString($time),
            'monthly' => now()->addMonth()->setTimeFromTimeString($time),
            default => now()->addDay()->setTimeFromTimeString($time),
        };

        $this->update(['next_run_at' => $nextRun]);
    }
}

