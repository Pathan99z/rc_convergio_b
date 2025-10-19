<?php

namespace App\Models\Commerce;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceTransaction extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'order_id',
        'payment_link_id',
        'payment_provider',
        'provider_event_id',
        'amount',
        'currency',
        'status',
        'raw_payload',
        'event_type',
        'tenant_id',
        'team_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the order that owns the transaction.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'order_id');
    }

    /**
     * Get the payment link that owns the transaction.
     */
    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(CommercePaymentLink::class, 'payment_link_id');
    }

    /**
     * Get the tenant that owns the transaction.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'tenant_id');
    }

    /**
     * Get the team that owns the transaction.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    /**
     * Scope a query to only include transactions for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include transactions for a specific team.
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to only include transactions with specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include transactions for a specific payment provider.
     */
    public function scopeForProvider($query, $provider)
    {
        return $query->where('payment_provider', $provider);
    }

    /**
     * Scope a query to only include transactions for a specific event type.
     */
    public function scopeForEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Check if transaction is successful.
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, ['succeeded', 'completed', 'paid']);
    }

    /**
     * Check if transaction is failed.
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled', 'declined']);
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }
}
