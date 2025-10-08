<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceStep extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'sequence_id',
        'step_order',
        'action_type',
        'delay_hours',
        'email_template_id',
        'task_title',
        'task_description',
        'tenant_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delay_hours' => 'integer',
        'step_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the sequence that owns the step.
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    /**
     * Get the email template for the step.
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /**
     * Get the tenant that owns the step.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the step.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the step.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include steps for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by action type.
     */
    public function scopeByActionType($query, $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Check if this is an email step.
     */
    public function isEmailStep(): bool
    {
        return $this->action_type === 'email';
    }

    /**
     * Check if this is a task step.
     */
    public function isTaskStep(): bool
    {
        return $this->action_type === 'task';
    }

    /**
     * Check if this is a wait step.
     */
    public function isWaitStep(): bool
    {
        return $this->action_type === 'wait';
    }
}
