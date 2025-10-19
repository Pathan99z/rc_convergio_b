<?php

namespace App\Models\AI;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory, HasTenantScope;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_conversations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'user_email',
        'message',
        'suggestions',
        'confidence',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'suggestions' => 'array',
        'confidence' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [];

    /**
     * Scope to get conversations by confidence level.
     */
    public function scopeByConfidence($query, float $minConfidence = 0.0)
    {
        return $query->where('confidence', '>=', $minConfidence);
    }

    /**
     * Scope to get conversations by user email.
     */
    public function scopeByUserEmail($query, string $email)
    {
        return $query->where('user_email', $email);
    }

    /**
     * Scope to get recent conversations.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get the confidence percentage.
     */
    public function getConfidencePercentageAttribute(): float
    {
        return round($this->confidence, 2);
    }

    /**
     * Check if the conversation has suggestions.
     */
    public function hasSuggestions(): bool
    {
        return !empty($this->suggestions) && is_array($this->suggestions);
    }

    /**
     * Get the number of suggestions.
     */
    public function getSuggestionsCountAttribute(): int
    {
        return $this->hasSuggestions() ? count($this->suggestions) : 0;
    }
}
