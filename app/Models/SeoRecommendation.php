<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'page_url',
        'recommendation_type',
        'message',
        'severity',
        'is_resolved',
        'resolved_at'
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime'
    ];

    /**
     * Get the user that owns the recommendation
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the page this recommendation belongs to
     */
    public function page()
    {
        return $this->belongsTo(SeoPage::class, 'page_url', 'page_url')
            ->where('user_id', $this->user_id);
    }

    /**
     * Mark recommendation as resolved
     */
    public function markResolved()
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now()
        ]);
    }

    /**
     * Get unresolved recommendations
     */
    public static function getUnresolved($userId, $severity = null)
    {
        $query = static::where('user_id', $userId)
            ->where('is_resolved', false)
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc');

        if ($severity) {
            $query->where('severity', $severity);
        }

        return $query->get();
    }

    /**
     * Get recommendations by type
     */
    public static function getByType($userId, $type)
    {
        return static::where('user_id', $userId)
            ->where('recommendation_type', $type)
            ->where('is_resolved', false)
            ->get();
    }
}
