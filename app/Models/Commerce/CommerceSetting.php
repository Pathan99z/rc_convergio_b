<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'stripe_public_key',
        'stripe_secret_key',
        'mode',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the setting.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include settings for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get or create settings for a tenant.
     */
    public static function getForTenant(int $tenantId): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'mode' => 'test',
                'stripe_public_key' => null,
                'stripe_secret_key' => null,
            ]
        );
    }

    /**
     * Check if the settings are in test mode.
     */
    public function isTestMode(): bool
    {
        return $this->mode === 'test';
    }

    /**
     * Check if the settings are in live mode.
     */
    public function isLiveMode(): bool
    {
        return $this->mode === 'live';
    }

    /**
     * Check if Stripe keys are configured.
     */
    public function hasStripeKeys(): bool
    {
        return !empty($this->stripe_public_key) && !empty($this->stripe_secret_key);
    }

    /**
     * Get the appropriate Stripe public key based on mode.
     */
    public function getStripePublicKey(): ?string
    {
        return $this->stripe_public_key;
    }

    /**
     * Get the appropriate Stripe secret key based on mode.
     */
    public function getStripeSecretKey(): ?string
    {
        return $this->stripe_secret_key;
    }
}
