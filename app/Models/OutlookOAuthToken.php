<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class OutlookOAuthToken extends Model
{
    use HasFactory;

    protected $table = 'outlook_oauth_tokens';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
        'email',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant that owns the token.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get a valid token for a user and tenant.
     */
    public static function getValidTokenForUser(int $userId, int $tenantId): ?self
    {
        return self::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('expires_at', '>', now())
            ->whereNotNull('access_token')
            ->first();
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    /**
     * Scope a query to only include tokens for a specific tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
