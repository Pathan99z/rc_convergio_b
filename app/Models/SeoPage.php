<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'page_url',
        'title',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'last_fetched_at'
    ];

    protected $casts = [
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'decimal:2',
        'position' => 'decimal:2',
        'last_fetched_at' => 'datetime'
    ];

    /**
     * Get the user that owns the page
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get recommendations for this page
     */
    public function recommendations()
    {
        return $this->hasMany(SeoRecommendation::class, 'page_url', 'page_url')
            ->where('user_id', $this->user_id);
    }

    /**
     * Get top performing pages
     */
    public static function getTopPages($userId, $limit = 50, $orderBy = 'clicks')
    {
        return static::where('user_id', $userId)
            ->orderBy($orderBy, 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find or create a page
     */
    public static function findOrCreateByUrl($userId, $url)
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'page_url' => $url],
            [
                'clicks' => 0,
                'impressions' => 0,
                'ctr' => 0,
                'position' => 0
            ]
        );
    }
}
