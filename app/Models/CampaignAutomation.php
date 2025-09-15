<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CampaignAutomation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'campaign_id',
        'trigger_event',
        'delay_minutes',
        'action',
        'metadata',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'delay_minutes' => 'integer',
    ];

    /**
     * Get the campaign that owns the automation.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the tenant that owns the automation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include automations for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include automations for a specific trigger event.
     */
    public function scopeForTrigger($query, $triggerEvent)
    {
        return $query->where('trigger_event', $triggerEvent);
    }

    /**
     * Get available trigger events.
     */
    public static function getAvailableTriggerEvents(): array
    {
        return [
            'form_submitted' => 'Form Submitted',
            'segment_joined' => 'Segment Joined',
            'contact_created' => 'Contact Created',
            'deal_created' => 'Deal Created',
            'deal_updated' => 'Deal Updated',
        ];
    }

    /**
     * Get available actions.
     */
    public static function getAvailableActions(): array
    {
        return [
            'send_email' => 'Send Email',
            'add_to_segment' => 'Add to Segment',
            'update_contact' => 'Update Contact',
        ];
    }
}
