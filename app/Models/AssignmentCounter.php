<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AssignmentCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'team_id',
        'user_id',
        'counter',
    ];

    protected $casts = [
        'counter' => 'integer',
    ];

    /**
     * Get the tenant that owns the counter.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user for this counter.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope a query to only include counters for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include counters for a specific team.
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Get or create a counter for the given tenant and team/user combination.
     */
    public static function getOrCreate(int $tenantId, ?int $teamId = null, ?int $userId = null): self
    {
        return static::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'team_id' => $teamId,
                'user_id' => $userId,
            ],
            ['counter' => 0]
        );
    }

    /**
     * Increment the counter atomically and return the new value.
     */
    public function incrementAndGet(): int
    {
        return DB::transaction(function () {
            $this->increment('counter');
            return $this->fresh()->counter;
        });
    }

    /**
     * Reset the counter to 0.
     */
    public function reset(): void
    {
        $this->update(['counter' => 0]);
    }

    /**
     * Get the next user in round-robin for a team.
     */
    public static function getNextUserForTeam(int $tenantId, ?int $teamId, array $teamUserIds): ?int
    {
        if (empty($teamUserIds)) {
            return null;
        }

        // Get or create counter for this team
        $counter = static::getOrCreate($tenantId, $teamId);
        
        // Get the next index in round-robin
        $nextIndex = $counter->incrementAndGet() % count($teamUserIds);
        
        return $teamUserIds[$nextIndex];
    }
}
