<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_id',
        'first_seen_at',
        'last_seen_at',
        'last_contact_id',
        'tenant_id',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the visitor.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the last contact associated with this visitor.
     */
    public function lastContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'last_contact_id');
    }

    /**
     * Get all sessions for this visitor.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(VisitorSession::class);
    }

    /**
     * Get all visitor-contact links.
     */
    public function links(): HasMany
    {
        return $this->hasMany(VisitorLink::class);
    }

    /**
     * Get all contacts linked to this visitor.
     */
    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'visitor_links', 'visitor_id', 'contact_id')
            ->withPivot('linked_at')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include visitors for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include linked visitors.
     */
    public function scopeLinked($query)
    {
        return $query->whereNotNull('last_contact_id');
    }

    /**
     * Scope a query to only include unlinked visitors.
     */
    public function scopeUnlinked($query)
    {
        return $query->whereNull('last_contact_id');
    }

    /**
     * Scope a query to only include visitors seen within a date range.
     */
    public function scopeSeenBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('last_seen_at', [$startDate, $endDate]);
    }

    /**
     * Get the visitor's current active session.
     */
    public function getActiveSession()
    {
        return $this->sessions()
            ->whereNull('ended_at')
            ->orderBy('started_at', 'desc')
            ->first();
    }

    /**
     * Get the visitor's total session count.
     */
    public function getSessionCount(): int
    {
        return $this->sessions()->count();
    }

    /**
     * Get the visitor's total session duration.
     */
    public function getTotalSessionDuration(): int
    {
        return $this->sessions()
            ->whereNotNull('ended_at')
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as total_duration')
            ->value('total_duration') ?? 0;
    }

    /**
     * Check if visitor is currently active (has active session).
     */
    public function isActive(): bool
    {
        return $this->sessions()
            ->whereNull('ended_at')
            ->exists();
    }

    /**
     * Get visitor's engagement score based on sessions and duration.
     */
    public function getEngagementScore(): int
    {
        $sessionCount = $this->getSessionCount();
        $totalDuration = $this->getTotalSessionDuration();
        $isLinked = $this->last_contact_id !== null;

        $score = 0;
        
        // Base score from session count
        $score += min($sessionCount * 10, 50);
        
        // Duration bonus (in minutes)
        $durationMinutes = $totalDuration / 60;
        $score += min($durationMinutes * 2, 30);
        
        // Link bonus
        if ($isLinked) {
            $score += 20;
        }

        return min($score, 100);
    }
}
