<?php

namespace App\Models\Commerce;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $table = 'commerce_plans';

    protected $fillable = [
        'tenant_id',
        'team_id',
        'name',
        'slug',
        'stripe_product_id',
        'stripe_price_id',
        'interval',
        'amount_cents',
        'currency',
        'active',
        'trial_days',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'metadata' => 'array',
        'amount_cents' => 'integer',
        'trial_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Get the formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount_cents / 100, 2);
    }

    /**
     * Get the amount in dollars.
     */
    public function getAmountDollarsAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    /**
     * Scope to get active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get plans by interval.
     */
    public function scopeByInterval($query, string $interval)
    {
        return $query->where('interval', $interval);
    }

    /**
     * Check if plan has trial period.
     */
    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    /**
     * Get the display name with price.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} - {$this->currency} {$this->formatted_amount}/{$this->interval}";
    }
}
