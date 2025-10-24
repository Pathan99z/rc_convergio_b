<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

class Contact extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'owner_id',
        'company_id',
        'lifecycle_stage',
        'source',
        'tags',
        'lead_score',
        'lead_score_updated_at',
        'tenant_id',
        'team_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'lead_score' => 'integer',
        'lead_score_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the company that owns the contact.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the owner of the contact.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the full name of the contact.
     */
    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the contact's subscription status
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ContactSubscription::class, 'id', 'contact_id');
    }

    /**
     * Check if contact is unsubscribed
     */
    public function isUnsubscribed(): bool
    {
        return \App\Models\ContactSubscription::isUnsubscribed($this->id);
    }

    /**
     * Get the team that owns the contact.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the interactions for the contact.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(ContactInteraction::class);
    }

    /**
     * Get the deals for the contact.
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * Get the meetings for the contact.
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * Get the tasks for the contact (polymorphic relationship).
     */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'related');
    }

    /**
     * Get the activities for the contact (polymorphic relationship).
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'related');
    }

    /**
     * Get the form submissions for the contact.
     */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Get the campaign recipients for the contact.
     */
    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * Get the event attendees for the contact.
     */
    public function eventAttendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    /**
     * Get the journey executions for the contact.
     */
    public function journeyExecutions(): HasMany
    {
        return $this->hasMany(JourneyExecution::class);
    }

    /**
     * Scope a query to only include contacts for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get cached contact with relationships
     */
    public static function getCached($id)
    {
        return Cache::remember("contact_{$id}", 3600, function() use ($id) {
            return static::with(['company', 'owner', 'tags'])->find($id);
        });
    }

    /**
     * Get cached contacts for tenant
     */
    public static function getCachedForTenant($tenantId, $limit = 50)
    {
        return Cache::remember("contacts_tenant_{$tenantId}_{$limit}", 1800, function() use ($tenantId, $limit) {
            return static::where('tenant_id', $tenantId)
                ->with(['company', 'owner'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }
}


