<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntentEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'event_name',
        'event_data',
        'intent_score',
        'source',
        'session_id',
        'ip_address',
        'user_agent',
        'metadata',
        'company_id',
        'contact_id',
        'tenant_id',
    ];

    protected $casts = [
        'event_data' => 'array',
        'metadata' => 'array',
        'intent_score' => 'integer',
    ];

    /**
     * Get the company associated with the event.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact associated with the event.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the tenant that owns the event.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the visitor sessions associated with the event.
     */
    public function visitorSessions()
    {
        return $this->belongsToMany(VisitorSession::class, 'session_id', 'session_id');
    }

    /**
     * Scope a query to only include events for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include events of a specific type.
     */
    public function scopeOfType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to only include events from a specific source.
     */
    public function scopeFromSource($query, $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope a query to only include events with high intent.
     */
    public function scopeHighIntent($query, $threshold = 70)
    {
        return $query->where('intent_score', '>=', $threshold);
    }

    /**
     * Scope a query to only include events within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get available event types.
     */
    public static function getAvailableEventTypes(): array
    {
        return [
            'page_view' => 'Page View',
            'download' => 'Download',
            'form_submit' => 'Form Submit',
            'email_open' => 'Email Open',
            'email_click' => 'Email Click',
            'social_share' => 'Social Share',
            'video_play' => 'Video Play',
            'document_view' => 'Document View',
            'search' => 'Search',
            'cart_add' => 'Cart Add',
            'checkout_start' => 'Checkout Start',
            'purchase' => 'Purchase',
        ];
    }

    /**
     * Get available sources.
     */
    public static function getAvailableSources(): array
    {
        return [
            'website' => 'Website',
            'email' => 'Email',
            'social' => 'Social Media',
            'search' => 'Search Engine',
            'referral' => 'Referral',
            'direct' => 'Direct',
            'mobile_app' => 'Mobile App',
        ];
    }

    /**
     * Calculate intent score based on event data.
     */
    public function calculateIntentScore(): int
    {
        $baseScore = match ($this->event_type) {
            'purchase' => 100,
            'checkout_start' => 90,
            'cart_add' => 80,
            'form_submit' => 70,
            'download' => 60,
            'email_click' => 50,
            'email_open' => 40,
            'video_play' => 30,
            'document_view' => 25,
            'page_view' => 10,
            default => 5,
        };

        // Adjust based on metadata
        if ($this->metadata) {
            if (isset($this->metadata['duration']) && $this->metadata['duration'] > 300) {
                $baseScore += 10; // +10 for long engagement
            }
            if (isset($this->metadata['pages_viewed']) && $this->metadata['pages_viewed'] > 5) {
                $baseScore += 15; // +15 for multiple page views
            }
            if (isset($this->metadata['return_visitor']) && $this->metadata['return_visitor']) {
                $baseScore += 20; // +20 for return visitors
            }
        }

        return min(100, max(0, $baseScore));
    }
}

