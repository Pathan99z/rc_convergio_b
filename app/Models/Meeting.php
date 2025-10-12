<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasTenantScope;

class Meeting extends Model
{
    use HasFactory, HasTenantScope;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'contact_id',
        'user_id',
        'scheduled_at',
        'end_time',
        'duration_minutes',
        'location',
        'status',
        'integration_provider',
        'integration_data',
        'attendees',
        'notes',
        'completed_at',
        'cancelled_at',
        'tenant_id',
        'team_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'end_time' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'integration_data' => 'array',
        'attendees' => 'array',
    ];

    /**
     * Get the contact associated with the meeting.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user (organizer) of the meeting.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant that owns the meeting.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the activities for the meeting.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'related');
    }

    /**
     * Scope a query to only include meetings for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include meetings for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include meetings for a specific contact.
     */
    public function scopeForContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Scope a query to only include meetings with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include meetings from a specific integration provider.
     */
    public function scopeFromProvider($query, $provider)
    {
        return $query->where('integration_provider', $provider);
    }

    /**
     * Scope a query to only include upcoming meetings.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>', now())
            ->where('status', 'scheduled');
    }

    /**
     * Scope a query to only include meetings in a date range.
     */
    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('scheduled_at', [$start, $end]);
    }

    /**
     * Get available meeting statuses.
     */
    public static function getAvailableStatuses(): array
    {
        return [
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
        ];
    }

    /**
     * Get available integration providers.
     */
    public static function getAvailableProviders(): array
    {
        return [
            'google' => 'Google Calendar',
            'outlook' => 'Outlook Calendar',
            'zoom' => 'Zoom',
            'teams' => 'Microsoft Teams',
            'manual' => 'Manual Entry',
        ];
    }

    /**
     * Check if the meeting is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->scheduled_at > now() && $this->status === 'scheduled';
    }

    /**
     * Check if the meeting is in progress.
     */
    public function isInProgress(): bool
    {
        $startTime = $this->scheduled_at;
        $endTime = $this->scheduled_at->addMinutes($this->duration_minutes);
        
        return now()->between($startTime, $endTime) && $this->status === 'scheduled';
    }

    /**
     * Check if the meeting is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get the meeting end time.
     */
    public function getEndTime(): \DateTime
    {
        // Use stored end_time if available, otherwise calculate from scheduled_at + duration
        if ($this->end_time) {
            return $this->end_time;
        }
        
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }

    /**
     * Get the meeting link from integration data.
     */
    public function getMeetingLink(): ?string
    {
        return $this->integration_data['join_url'] ?? $this->integration_data['link'] ?? null;
    }

    /**
     * Get the meeting ID from integration data.
     */
    public function getMeetingId(): ?string
    {
        return $this->integration_data['meeting_id'] ?? null;
    }

    /**
     * Mark the meeting as completed.
     */
    public function markAsCompleted(string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $notes ? $this->notes . "\n\n" . $notes : $this->notes,
        ]);
    }

    /**
     * Cancel the meeting.
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $reason ? $this->notes . "\n\nCancelled: " . $reason : $this->notes,
        ]);
    }

    /**
     * Mark as no show.
     */
    public function markAsNoShow(string $notes = null): void
    {
        $this->update([
            'status' => 'no_show',
            'notes' => $notes ? $this->notes . "\n\nNo Show: " . $notes : $this->notes,
        ]);
    }

    /**
     * Get meeting duration in hours and minutes.
     */
    public function getDurationFormatted(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        return "{$minutes}m";
    }

    /**
     * Get meeting summary for display.
     */
    public function getSummary(): string
    {
        $contactName = $this->contact ? $this->contact->first_name . ' ' . $this->contact->last_name : 'Unknown Contact';
        $time = $this->scheduled_at->format('M j, Y g:i A');
        $duration = $this->getDurationFormatted();

        return "{$this->title} with {$contactName} at {$time} ({$duration})";
    }
}
