<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SocialMediaPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'platform',
        'hashtags',
        'scheduled_at',
        'published_at',
        'media_urls',
        'status',
        'external_post_id',
        'engagement_metrics',
        'target_audience',
        'call_to_action',
        'location',
        'mentions'
    ];

    protected $casts = [
        'hashtags' => 'array',
        'media_urls' => 'array',
        'engagement_metrics' => 'array',
        'mentions' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /**
     * Get the user that owns the social media post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include posts for a specific platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to only include posts with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include scheduled posts.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
                    ->whereNotNull('scheduled_at');
    }

    /**
     * Scope a query to only include published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Get the character count of the content.
     */
    public function getCharacterCountAttribute(): int
    {
        return strlen($this->content);
    }

    /**
     * Check if the post is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_at !== null;
    }

    /**
     * Check if the post is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if the post is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
