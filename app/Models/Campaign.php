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
        'total_recipients',
        'sent_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'bounced_count',
        'settings',
        'is_template',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
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
}
