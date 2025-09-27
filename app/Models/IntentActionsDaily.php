<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntentActionsDaily extends Model
{
    use HasFactory;

    protected $table = 'intent_actions_daily';

    protected $fillable = [
        'tenant_id',
        'day',
        'action',
        'count',
        'avg_score',
        'max_score',
        'min_score',
    ];

    protected $casts = [
        'day' => 'date',
        'avg_score' => 'decimal:2',
    ];

    /**
     * Get the tenant that owns this daily rollup.
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include rollups for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include rollups within a date range.
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('day', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include recent rollups.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('day', '>=', now()->subDays($days));
    }

    /**
     * Get or create daily action rollup for a specific tenant, day, and action.
     */
    public static function getOrCreateForAction(int $tenantId, string $day, string $action): self
    {
        return self::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'day' => $day,
                'action' => $action,
            ],
            [
                'count' => 0,
                'avg_score' => 0,
                'max_score' => 0,
                'min_score' => 0,
            ]
        );
    }

    /**
     * Increment counters for a new action.
     */
    public function incrementForAction(int $score): void
    {
        $this->increment('count');

        // Update score statistics
        $totalScore = $this->avg_score * ($this->count - 1) + $score;
        $this->avg_score = $this->count > 0 ? $totalScore / $this->count : 0;

        // Update min/max scores
        if ($this->max_score < $score) {
            $this->max_score = $score;
        }
        if ($this->min_score == 0 || $this->min_score > $score) {
            $this->min_score = $score;
        }

        $this->save();
    }

    /**
     * Get action breakdown for a date range.
     */
    public static function getActionBreakdown(int $tenantId, string $startDate, string $endDate): array
    {
        $rollups = self::forTenant($tenantId)
            ->between($startDate, $endDate)
            ->selectRaw('action, SUM(count) as count, AVG(avg_score) as avg_score')
            ->groupBy('action')
            ->orderByRaw('SUM(count) DESC')
            ->get();

        return $rollups->mapWithKeys(function ($rollup) {
            return [
                $rollup->action => [
                    'count' => $rollup->count,
                    'avg_score' => round($rollup->avg_score, 2),
                ]
            ];
        })->toArray();
    }
}
