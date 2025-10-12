<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentDefault extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'default_user_id',
        'default_team_id',
        'round_robin_enabled',
        'enable_automatic_assignment',
    ];

    protected $casts = [
        'round_robin_enabled' => 'boolean',
        'enable_automatic_assignment' => 'boolean',
    ];

    /**
     * Get the tenant that owns the defaults.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the default user for assignments.
     */
    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_user_id');
    }

    /**
     * Scope a query to only include defaults for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get or create default settings for a tenant.
     */
    public static function getForTenant(int $tenantId): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'enable_automatic_assignment' => true,
                'round_robin_enabled' => false,
            ]
        );
    }
}
