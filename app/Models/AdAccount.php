<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'account_name',
        'account_id',
        'credentials',
        'settings',
        'is_active',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'credentials' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns the ad account.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include ad accounts for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include active ad accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include ad accounts for a specific provider.
     */
    public function scopeForProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Get available ad providers.
     */
    public static function getAvailableProviders(): array
    {
        return [
            'google' => 'Google Ads',
            'facebook' => 'Facebook Ads',
            'linkedin' => 'LinkedIn Ads',
            'twitter' => 'Twitter Ads',
            'instagram' => 'Instagram Ads',
        ];
    }

    /**
     * Encrypt credentials before saving.
     */
    public function setCredentialsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['credentials'] = encrypt(json_encode($value));
        } else {
            $this->attributes['credentials'] = $value;
        }
    }

    /**
     * Decrypt credentials when retrieving.
     */
    public function getCredentialsAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        
        try {
            return json_decode(decrypt($value), true) ?: [];
        } catch (\Exception $e) {
            // If decryption fails, return empty array
            return [];
        }
    }

    /**
     * Get Facebook access token from credentials.
     */
    public function getFacebookAccessToken(): ?string
    {
        if ($this->provider !== 'facebook') {
            return null;
        }

        $credentials = $this->credentials;
        return $credentials['access_token'] ?? null;
    }

    /**
     * Check if this is a Facebook ad account.
     */
    public function isFacebookAccount(): bool
    {
        return $this->provider === 'facebook';
    }

    /**
     * Get Facebook user information from credentials.
     */
    public function getFacebookUserInfo(): ?array
    {
        if (!$this->isFacebookAccount()) {
            return null;
        }

        $credentials = $this->credentials;
        return [
            'user_id' => $credentials['user_id'] ?? null,
            'user_name' => $credentials['user_name'] ?? null,
            'user_email' => $credentials['user_email'] ?? null,
        ];
    }
}
