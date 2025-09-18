<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Automation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'trigger_event',
        'delay_minutes',
        'action',
        'metadata',
        'is_active',
        'tenant_id',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'delay_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns the automation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the automation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the automation logs for this automation.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class);
    }

    /**
     * Scope a query to only include automations for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include active automations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
            'contact_created' => 'Contact Created',
            'form_submitted' => 'Form Submitted',
            'email_opened' => 'Email Opened',
            'link_clicked' => 'Link Clicked',
        ];
    }

    /**
     * Get available actions.
     */
    public static function getAvailableActions(): array
    {
        return [
            'send_email' => 'Send Email',
            'add_tag' => 'Add Tag',
            'update_field' => 'Update Field',
            'create_task' => 'Create Task',
        ];
    }

    /**
     * Check if the automation is valid for execution.
     */
    public function canExecute(): bool
    {
        return $this->is_active && !$this->trashed();
    }
}
