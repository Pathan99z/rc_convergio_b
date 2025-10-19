<?php

namespace App\Models\Commerce;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'commerce_subscription_events';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'event_type',
        'stripe_event_id',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the subscription that owns the event.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * Check if event is processed.
     */
    public function isProcessed(): bool
    {
        return !is_null($this->processed_at);
    }

    /**
     * Mark event as processed.
     */
    public function markAsProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }

    /**
     * Scope to get processed events.
     */
    public function scopeProcessed($query)
    {
        return $query->whereNotNull('processed_at');
    }

    /**
     * Scope to get unprocessed events.
     */
    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    /**
     * Scope to get events by type.
     */
    public function scopeByType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
