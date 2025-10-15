<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ListeningMention extends Model
{
    use HasFactory;

    protected $fillable = [
        'listening_keyword_id',
        'user_id',
        'platform',
        'external_id',
        'content',
        'author_name',
        'author_handle',
        'author_url',
        'post_url',
        'sentiment',
        'engagement',
        'mentioned_at',
        'is_read',
    ];

    protected $casts = [
        'engagement' => 'array',
        'mentioned_at' => 'datetime',
        'is_read' => 'boolean',
    ];

    /**
     * Get the keyword this mention belongs to.
     */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(ListeningKeyword::class, 'listening_keyword_id');
    }

    /**
     * Get the user that owns the mention.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include unread mentions.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to only include mentions for a specific platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Mark mention as read
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }
}


