<?php

namespace App\Models\Commerce;

use App\Models\User;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $table = 'commerce_subscriptions';

    protected $fillable = [
        'tenant_id',
        'team_id',
        'user_id',
        'customer_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'plan_id',
        'status',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'cancel_at',
        'trial_ends_at',
        'metadata',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the plan that owns the subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the invoices for the subscription.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'subscription_id');
    }

    /**
     * Get the subscription events.
     */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class, 'subscription_id');
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription is in trial.
     */
    public function isTrialing(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription will cancel at period end.
     */
    public function willCancelAtPeriodEnd(): bool
    {
        return $this->cancel_at_period_end;
    }

    /**
     * Get the next billing date.
     */
    public function getNextBillingDateAttribute(): ?\Carbon\Carbon
    {
        if ($this->isTrialing()) {
            return $this->trial_ends_at;
        }

        return $this->current_period_end;
    }

    /**
     * Scope to get active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'trialing']);
    }

    /**
     * Scope to get cancelled subscriptions.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to get past due subscriptions.
     */
    public function scopePastDue($query)
    {
        return $query->where('status', 'past_due');
    }
}
