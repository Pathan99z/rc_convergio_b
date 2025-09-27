<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VisitorSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'visitor_id',
        'started_at',
        'ended_at',
        'tenant_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the visitor that owns this session.
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Get the tenant that owns this session.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include sessions for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include active sessions.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope a query to only include ended sessions.
     */
    public function scopeEnded($query)
    {
        return $query->whereNotNull('ended_at');
    }

    /**
     * Scope a query to only include sessions within a date range.
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }

    /**
     * Get the session duration in seconds.
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->ended_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->ended_at);
    }

    /**
     * Get the session duration in minutes.
     */
    public function getDurationMinutesAttribute(): ?float
    {
        $duration = $this->getDurationAttribute();
        return $duration ? round($duration / 60, 2) : null;
    }

    /**
     * Check if the session is currently active.
     */
    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * End the session.
     */
    public function end(): void
    {
        if ($this->isActive()) {
            $this->update(['ended_at' => now()]);
        }
    }

    /**
     * Get session statistics for a tenant.
     */
    public static function getStatsForTenant(int $tenantId): array
    {
        $totalSessions = self::forTenant($tenantId)->count();
        $activeSessions = self::forTenant($tenantId)->active()->count();
        $endedSessions = self::forTenant($tenantId)->ended()->count();
        
        $avgDuration = self::forTenant($tenantId)
            ->ended()
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as avg_duration')
            ->value('avg_duration') ?? 0;

        return [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'ended_sessions' => $endedSessions,
            'average_duration_seconds' => round($avgDuration, 2),
            'average_duration_minutes' => round($avgDuration / 60, 2),
        ];
    }

    /**
     * Clean up old sessions (older than specified days).
     */
    public static function cleanupOldSessions(int $daysOld = 90): int
    {
        return self::where('ended_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}
