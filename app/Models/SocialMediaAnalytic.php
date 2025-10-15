<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SocialMediaAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'metric_name',
        'metric_value',
        'metric_date',
        'post_id',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
        'metric_date' => 'date',
    ];

    /**
     * Get the user that owns the analytic record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include analytics for a specific platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to only include analytics for a specific metric.
     */
    public function scopeForMetric($query, string $metricName)
    {
        return $query->where('metric_name', $metricName);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('metric_date', [$startDate, $endDate]);
    }
}


