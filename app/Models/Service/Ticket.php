<?php

namespace App\Models\Service;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use App\Models\Contact;
use App\Models\Company;
use App\Models\Team;
use App\Models\Service\SurveyResponse;
use App\Models\Service\EmailIntegration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'company_id',
        'assignee_id',
        'team_id',
        'email_integration_id',
        'subject',
        'description',
        'priority',
        'status',
        'sla_due_at',
        'resolved_at',
    ];

    protected $casts = [
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'priority' => 'string',
        'status' => 'string',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the ticket.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the contact associated with the ticket.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the company associated with the ticket.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user assigned to the ticket.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the team assigned to the ticket.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the messages for the ticket.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * Get the survey responses for the ticket.
     */
    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * Get the email integration that created this ticket.
     */
    public function emailIntegration(): BelongsTo
    {
        return $this->belongsTo(EmailIntegration::class);
    }

    /**
     * Scope a query to only include tickets for a specific tenant.
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
     * Scope a query to filter by priority.
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to filter by assignee.
     */
    public function scopeByAssignee($query, $assigneeId)
    {
        return $query->where('assignee_id', $assigneeId);
    }

    /**
     * Scope a query to filter by team.
     */
    public function scopeByTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to filter by SLA status.
     */
    public function scopeBySlaStatus($query, $status)
    {
        $now = now();
        
        return match ($status) {
            'on_track' => $query->where('sla_due_at', '>', $now),
            'breached' => $query->where('sla_due_at', '<=', $now),
            default => $query,
        };
    }

    /**
     * Check if ticket is on track for SLA.
     */
    public function isOnTrack(): bool
    {
        return $this->sla_due_at && $this->sla_due_at->isFuture();
    }

    /**
     * Check if ticket has breached SLA.
     */
    public function hasBreachedSla(): bool
    {
        return $this->sla_due_at && $this->sla_due_at->isPast();
    }

    /**
     * Get SLA status.
     */
    public function getSlaStatusAttribute(): string
    {
        if (!$this->sla_due_at) {
            return 'no_sla';
        }

        return $this->isOnTrack() ? 'on_track' : 'breached';
    }

    /**
     * Mark ticket as resolved.
     */
    public function markAsResolved(): bool
    {
        return $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    /**
     * Mark ticket as closed.
     */
    public function markAsClosed(): bool
    {
        return $this->update([
            'status' => 'closed',
            'resolved_at' => $this->resolved_at ?? now(),
        ]);
    }
}
