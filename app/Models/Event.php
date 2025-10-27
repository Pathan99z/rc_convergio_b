<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\HasTenantScope;

class Event extends Model
{
    use HasFactory, HasTenantScope;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'scheduled_at',
        'location',
        'settings',
        'is_active',
        'archived_at',
        'cancelled_at',
        'tenant_id',
        'created_by',
        'team_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'settings' => 'array',
        'is_active' => 'boolean',
        'archived_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the event.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the event.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the attendees for the event.
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    /**
     * Get the contacts attending the event.
     */
    public function contacts(): HasManyThrough
    {
        return $this->hasManyThrough(Contact::class, EventAttendee::class, 'event_id', 'id', 'id', 'contact_id');
    }

    /**
     * Scope a query to only include events for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include active events.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include events of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include upcoming events.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>', now());
    }

    /**
     * Scope a query to only include past events.
     */
    public function scopePast($query)
    {
        return $query->where('scheduled_at', '<', now());
    }

    /**
     * Get available event types.
     */
    public static function getAvailableTypes(): array
    {
        return [
            'webinar' => 'Webinar',
            'demo' => 'Product Demo',
            'workshop' => 'Workshop',
            'conference' => 'Conference',
            'meeting' => 'Meeting',
        ];
    }

    /**
     * Get RSVP statistics for the event.
     */
    public function getRsvpStats(): array
    {
        $attendees = $this->attendees;
        
        return [
            'total_invited' => $attendees->count(),
            'going' => $attendees->where('rsvp_status', 'going')->count(),
            'interested' => $attendees->where('rsvp_status', 'interested')->count(),
            'declined' => $attendees->where('rsvp_status', 'declined')->count(),
            'attended' => $attendees->where('attended', true)->count(),
        ];
    }

    /**
     * Check if the event is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->scheduled_at > now();
    }

    /**
     * Check if the event is past.
     */
    public function isPast(): bool
    {
        return $this->scheduled_at < now();
    }
}
