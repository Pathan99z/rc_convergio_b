<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ListeningKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'keyword',
        'platforms',
        'last_checked_at',
        'is_active',
        'mention_count',
        'settings',
    ];

    protected $casts = [
        'platforms' => 'array',
        'settings' => 'array',
        'last_checked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the keyword.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all mentions for this keyword.
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(ListeningMention::class);
    }

    /**
     * Scope a query to only include active keywords.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Increment mention count
     */
    public function incrementMentionCount(int $count = 1): void
    {
        $this->increment('mention_count', $count);
        $this->update(['last_checked_at' => now()]);
    }
}


