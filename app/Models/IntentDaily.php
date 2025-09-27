<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntentDaily extends Model
{
    use HasFactory;

    protected $table = 'intent_daily';

    protected $fillable = [
        'tenant_id',
        'day',
        'total_events',
        'unique_contacts',
        'unique_companies',
        'avg_score',
        'high_events',
        'medium_events',
        'low_events',
        'very_high_events',
        'very_low_events',
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
     * Get or create daily rollup for a specific tenant and day.
     */
    public static function getOrCreateForDay(int $tenantId, string $day): self
    {
        return self::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'day' => $day,
            ],
            [
                'total_events' => 0,
                'unique_contacts' => 0,
                'unique_companies' => 0,
                'avg_score' => 0,
                'high_events' => 0,
                'medium_events' => 0,
                'low_events' => 0,
                'very_high_events' => 0,
                'very_low_events' => 0,
                'max_score' => 0,
                'min_score' => 0,
            ]
        );
    }

    /**
     * Increment counters for a new event.
     */
    public function incrementForEvent(array $eventData): void
    {
        $this->increment('total_events');

        // Update score statistics
        $score = $eventData['intent_score'] ?? 0;
        
        // Update average score (simplified - in production you might want more sophisticated averaging)
        $totalScore = $this->avg_score * ($this->total_events - 1) + $score;
        $this->avg_score = $this->total_events > 0 ? $totalScore / $this->total_events : 0;

        // Update min/max scores
        if ($this->max_score < $score) {
            $this->max_score = $score;
        }
        if ($this->min_score == 0 || $this->min_score > $score) {
            $this->min_score = $score;
        }

        // Update intent level counters
        if ($score >= 80) {
            $this->increment('very_high_events');
        } elseif ($score >= 60) {
            $this->increment('high_events');
        } elseif ($score >= 40) {
            $this->increment('medium_events');
        } elseif ($score >= 20) {
            $this->increment('low_events');
        } else {
            $this->increment('very_low_events');
        }

        // Update unique counters if contact/company is provided
        if (!empty($eventData['contact_id'])) {
            // This is simplified - in production you'd track unique contact IDs
            $this->increment('unique_contacts');
        }
        if (!empty($eventData['company_id'])) {
            // This is simplified - in production you'd track unique company IDs
            $this->increment('unique_companies');
        }

        $this->save();
    }

    /**
     * Get aggregated statistics for a date range.
     */
    public static function getAggregatedStats(int $tenantId, string $startDate, string $endDate): array
    {
        $rollups = self::forTenant($tenantId)
            ->between($startDate, $endDate)
            ->get();

        if ($rollups->isEmpty()) {
            return [
                'total_events' => 0,
                'unique_contacts' => 0,
                'unique_companies' => 0,
                'average_score' => 0,
                'high_intent_events' => 0,
                'high_intent_percentage' => 0,
                'intent_distribution' => [
                    'very_high' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                    'very_low' => 0,
                ],
            ];
        }

        $totalEvents = $rollups->sum('total_events');
        $highIntentEvents = $rollups->sum('high_events') + $rollups->sum('very_high_events');
        $avgScore = $rollups->avg('avg_score');

        return [
            'total_events' => $totalEvents,
            'unique_contacts' => $rollups->sum('unique_contacts'),
            'unique_companies' => $rollups->sum('unique_companies'),
            'average_score' => round($avgScore, 2),
            'high_intent_events' => $highIntentEvents,
            'high_intent_percentage' => $totalEvents > 0 ? round(($highIntentEvents / $totalEvents) * 100, 2) : 0,
            'intent_distribution' => [
                'very_high' => $rollups->sum('very_high_events'),
                'high' => $rollups->sum('high_events'),
                'medium' => $rollups->sum('medium_events'),
                'low' => $rollups->sum('low_events'),
                'very_low' => $rollups->sum('very_low_events'),
            ],
        ];
    }
}
