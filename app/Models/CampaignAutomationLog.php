<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CampaignAutomationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'automation_id',
        'contact_id',
        'executed_at',
        'status',
        'error_message',
        'metadata',
        'tenant_id',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the automation that owns the log.
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(CampaignAutomation::class);
    }

    /**
     * Get the contact associated with the log.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the tenant that owns the log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include logs for a specific tenant.
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
     * Scope a query to filter by automation.
     */
    public function scopeByAutomation($query, $automationId)
    {
        return $query->where('automation_id', $automationId);
    }
}