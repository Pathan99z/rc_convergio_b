<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntentTopPagesDaily extends Model
{
    use HasFactory;

    protected $table = 'intent_top_pages_daily';

    protected $fillable = [
        'tenant_id',
        'day',
        'page_url',
        'visits',
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
     * Get or create daily page rollup for a specific tenant, day, and page.
     */
    public static function getOrCreateForPage(int $tenantId, string $day, string $pageUrl): self
    {
        return self::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'day' => $day,
                'page_url' => $pageUrl,
            ],
            [
                'visits' => 0,
                'avg_score' => 0,
                'max_score' => 0,
                'min_score' => 0,
            ]
        );
    }

    /**
     * Increment counters for a new page visit.
     */
    public function incrementForVisit(int $score): void
    {
        $this->increment('visits');

        // Update score statistics
        $totalScore = $this->avg_score * ($this->visits - 1) + $score;
        $this->avg_score = $this->visits > 0 ? $totalScore / $this->visits : 0;

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
     * Get top pages for a date range.
     */
    public static function getTopPages(int $tenantId, string $startDate, string $endDate, int $limit = 10): array
    {
        $rollups = self::forTenant($tenantId)
            ->between($startDate, $endDate)
            ->selectRaw('page_url, SUM(visits) as visits, AVG(avg_score) as avg_score, MAX(max_score) as max_score')
            ->groupBy('page_url')
            ->orderByRaw('SUM(visits) DESC')
            ->limit($limit)
            ->get();

        return $rollups->map(function ($rollup) {
            return [
                'page_url' => $rollup->page_url,
                'visits' => $rollup->visits,
                'avg_score' => round($rollup->avg_score, 2),
                'max_score' => $rollup->max_score,
            ];
        })->toArray();
    }
}
