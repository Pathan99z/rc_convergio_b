<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Scope a query to only include contacts for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}


