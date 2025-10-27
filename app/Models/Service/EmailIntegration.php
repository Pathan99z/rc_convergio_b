<?php

namespace App\Models\Service;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class EmailIntegration extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'email_address',
        'provider',
        'credentials',
        'is_active',
        'auto_create_tickets',
        'default_priority',
        'default_assignee_id',
        'default_team_id',
        'last_sync_at',
        'tickets_created_count',
        'notes',
    ];

    protected $casts = [
        'credentials' => 'array',
        'is_active' => 'boolean',
        'auto_create_tickets' => 'boolean',
        'last_sync_at' => 'datetime',
        'tickets_created_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
        
        // Encrypt credentials when saving
        static::saving(function ($model) {
            if ($model->credentials) {
                $model->credentials = Crypt::encrypt($model->credentials);
            }
        });
        
        // Decrypt credentials when retrieving
        static::retrieved(function ($model) {
            if ($model->credentials) {
                try {
                    $model->credentials = Crypt::decrypt($model->credentials);
                } catch (\Exception $e) {
                    $model->credentials = null;
                }
            }
        });
    }

    /**
     * Get the tenant that owns the email integration.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the default assignee for tickets created from this email.
     */
    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    /**
     * Get the default team for tickets created from this email.
     */
    public function defaultTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'default_team_id');
    }

    /**
     * Get tickets created from this email integration.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'email_integration_id');
    }

    /**
     * Scope a query to only include active integrations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include integrations that auto-create tickets.
     */
    public function scopeAutoCreateTickets($query)
    {
        return $query->where('auto_create_tickets', true);
    }

    /**
     * Scope a query to filter by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Check if the integration is connected and ready.
     */
    public function isConnected(): bool
    {
        return $this->is_active && !empty($this->credentials);
    }

    /**
     * Get the display name for the integration.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->email_address} ({$this->provider})";
    }

    /**
     * Get the status of the integration.
     */
    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        
        if (!$this->credentials) {
            return 'not_connected';
        }
        
        if ($this->last_sync_at && $this->last_sync_at->diffInMinutes(now()) > 30) {
            return 'sync_issue';
        }
        
        return 'active';
    }

    /**
     * Increment the tickets created count.
     */
    public function incrementTicketsCreated(): void
    {
        $this->increment('tickets_created_count');
    }

    /**
     * Update the last sync timestamp.
     */
    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    /**
     * Get integration statistics.
     */
    public function getStatsAttribute(): array
    {
        return [
            'tickets_created' => $this->tickets_created_count,
            'last_sync' => $this->last_sync_at?->diffForHumans(),
            'status' => $this->status,
            'provider' => $this->provider,
        ];
    }
}