<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    protected $fillable = [
        'name',
        'description',
        'subject',
        'content',
        'status',
        'type',
        'scheduled_at',
        'sent_at',
        'archived_at',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'bounced_count',
        'test_sent_count',
        'settings',
        'is_template',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'archived_at' => 'datetime',
        'settings' => 'array',
        'is_template' => 'boolean',
    ];

    /**
     * Get the tenant that owns the campaign.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the campaign.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the recipients for the campaign.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * Scope a query to only include campaigns for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to get draft campaigns.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to get sent campaigns.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope a query to get email campaigns.
     */
    public function scopeEmail($query)
    {
        return $query->where('type', 'email');
    }

    /**
     * Scope a query to get ad campaigns.
     */
    public function scopeAd($query)
    {
        return $query->where('type', 'ad');
    }

    /**
     * Scope a query to get event campaigns.
     */
    public function scopeEvent($query)
    {
        return $query->where('type', 'event');
    }

    /**
     * Get available campaign types.
     */
    public static function getAvailableTypes(): array
    {
        return [
            'email' => 'Email Campaign',
            'ad' => 'Ad Campaign',
            'event' => 'Event Campaign',
        ];
    }

    /**
     * Check if this is an email campaign.
     */
    public function isEmail(): bool
    {
        return $this->type === 'email';
    }

    /**
     * Check if this is an ad campaign.
     */
    public function isAd(): bool
    {
        return $this->type === 'ad';
    }

    /**
     * Check if this is an event campaign.
     */
    public function isEvent(): bool
    {
        return $this->type === 'event';
    }

    /**
     * Check if campaign is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Check if campaign is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_at !== null;
    }

    /**
     * Scope a query to get archived campaigns.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope a query to get non-archived campaigns.
     */
    public function scopeNotArchived($query)
    {
        return $query->where('status', '!=', 'archived');
    }
}
