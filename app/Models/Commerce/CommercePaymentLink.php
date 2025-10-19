<?php

namespace App\Models\Commerce;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercePaymentLink extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'quote_id',
        'order_id',
        'stripe_session_id',
        'public_url',
        'status',
        'expires_at',
        'metadata',
        'tenant_id',
        'team_id',
        'owner_id',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the quote that owns the payment link.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Quote::class);
    }

    /**
     * Get the order that owns the payment link.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'order_id');
    }

    /**
     * Get the tenant that owns the payment link.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'tenant_id');
    }

    /**
     * Get the team that owns the payment link.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    /**
     * Get the owner of the payment link.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    /**
     * Get the user who created the payment link.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the transactions for the payment link.
     */
    public function transactions()
    {
        return $this->hasMany(\App\Models\Commerce\CommerceTransaction::class, 'payment_link_id');
    }

    /**
     * Scope a query to only include payment links for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include payment links for a specific team.
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to only include payment links with specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include active payment links.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Check if the payment link is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the payment link is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    /**
     * Generate a test payment URL.
     */
    public function generateTestUrl(): string
    {
        return url("/commerce/checkout/{$this->id}");
    }

    /**
     * Get signed public URL for secure access.
     */
    public function getPublicUrlSignedAttribute(): string
    {
        if ($this->public_url) {
            return $this->public_url;
        }
        
        return url("/commerce/checkout/{$this->id}");
    }

    /**
     * Check if payment link is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment link can be activated.
     */
    public function canBeActivated(): bool
    {
        return in_array($this->status, ['draft', 'expired']);
    }
}
