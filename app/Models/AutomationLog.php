<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutomationLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'automation_id',
        'contact_id',
        'executed_at',
        'status',
        'metadata',
        'error_message',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'executed_at' => 'datetime',
    ];

    /**
     * Get the automation that owns the log.
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /**
     * Get the contact that triggered the automation.
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
     * Scope a query to only include logs with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include logs for a specific automation.
     */
    public function scopeForAutomation($query, $automationId)
    {
        return $query->where('automation_id', $automationId);
    }

    /**
     * Scope a query to only include logs for a specific contact.
     */
    public function scopeForContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Mark the log as executed.
     */
    public function markAsExecuted(array $metadata = []): void
    {
        $this->update([
            'status' => 'executed',
            'executed_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    /**
     * Mark the log as failed.
     */
    public function markAsFailed(string $errorMessage, array $metadata = []): void
    {
        $this->update([
            'status' => 'failed',
            'executed_at' => now(),
            'error_message' => $errorMessage,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }
}
