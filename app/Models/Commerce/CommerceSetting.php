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
        'payment_gateway',
        'stripe_public_key',
        'stripe_secret_key',
        'payfast_merchant_id',
        'payfast_merchant_key',
        'payfast_passphrase',
        'payfast_webhook_secret',
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
                'payment_gateway' => 'stripe',
                'mode' => 'test',
                'stripe_public_key' => null,
                'stripe_secret_key' => null,
                'payfast_merchant_id' => null,
                'payfast_merchant_key' => null,
                'payfast_passphrase' => null,
                'payfast_webhook_secret' => null,
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

    /**
     * Get the payment gateway.
     */
    public function getPaymentGateway(): string
    {
        return $this->payment_gateway ?? 'stripe';
    }

    /**
     * Check if PayFast keys are configured.
     */
    public function hasPayFastKeys(): bool
    {
        return !empty($this->payfast_merchant_id) && !empty($this->payfast_merchant_key);
    }

    /**
     * Get PayFast merchant ID.
     */
    public function getPayFastMerchantId(): ?string
    {
        return $this->payfast_merchant_id;
    }

    /**
     * Get PayFast merchant key.
     */
    public function getPayFastMerchantKey(): ?string
    {
        return $this->payfast_merchant_key;
    }

    /**
     * Get PayFast passphrase.
     */
    public function getPayFastPassphrase(): ?string
    {
        return $this->payfast_passphrase;
    }

    /**
     * Get PayFast webhook secret.
     */
    public function getPayFastWebhookSecret(): ?string
    {
        return $this->payfast_webhook_secret;
    }

    /**
     * Check if payment gateway is configured.
     */
    public function isPaymentGatewayConfigured(): bool
    {
        $gateway = $this->getPaymentGateway();
        
        if ($gateway === 'payfast') {
            return $this->hasPayFastKeys();
        }
        
        return $this->hasStripeKeys();
    }
}
