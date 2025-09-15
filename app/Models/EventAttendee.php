<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventAttendee extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_id',
        'contact_id',
        'rsvp_status',
        'attended',
        'rsvp_at',
        'attended_at',
        'metadata',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attended' => 'boolean',
        'rsvp_at' => 'datetime',
        'attended_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the event that the attendee is registered for.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the contact who is attending the event.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the tenant that owns the attendee record.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include attendees for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include attendees with a specific RSVP status.
     */
    public function scopeWithRsvpStatus($query, $status)
    {
        return $query->where('rsvp_status', $status);
    }

    /**
     * Scope a query to only include attendees who attended.
     */
    public function scopeAttended($query)
    {
        return $query->where('attended', true);
    }

    /**
     * Get available RSVP statuses.
     */
    public static function getAvailableRsvpStatuses(): array
    {
        return [
            'going' => 'Going',
            'interested' => 'Interested',
            'declined' => 'Declined',
        ];
    }

    /**
     * Mark the attendee as attended.
     */
    public function markAsAttended(): void
    {
        $this->update([
            'attended' => true,
            'attended_at' => now(),
        ]);
    }

    /**
     * Update RSVP status.
     */
    public function updateRsvpStatus(string $status): void
    {
        $this->update([
            'rsvp_status' => $status,
            'rsvp_at' => now(),
        ]);
    }
}
