<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clicks',
        'impressions',
        'ctr',
        'position'
    ];

    protected $casts = [
        'date' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'decimal:2',
        'position' => 'decimal:2'
    ];

    /**
     * Get the user that owns the metric
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get metrics for a date range
     */
    public static function getForDateRange($userId, $startDate, $endDate)
    {
        return static::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Get aggregated metrics for a period
     */
    public static function getAggregated($userId, $days = 30)
    {
        $startDate = now()->subDays($days)->toDateString();
        $endDate = now()->toDateString();

        return static::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                SUM(clicks) as total_clicks,
                SUM(impressions) as total_impressions,
                AVG(ctr) as avg_ctr,
                AVG(position) as avg_position
            ')
            ->first();
    }
}
