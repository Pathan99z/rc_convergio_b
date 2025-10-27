<?php

namespace App\Models\AI;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CopilotConversation extends Model
{
    use HasFactory, HasTenantScope;

    /**
     * The table associated with the model.
     */
    protected $table = 'copilot_conversations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'query',
        'response',
        'confidence',
        'feature',
        'action',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'response' => 'array',
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
     * Scope to get conversations by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get conversations by feature.
     */
    public function scopeByFeature($query, string $feature)
    {
        return $query->where('feature', $feature);
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
     * Check if the conversation has a specific feature.
     */
    public function hasFeature(): bool
    {
        return !empty($this->feature);
    }

    /**
     * Get the action type.
     */
    public function getActionTypeAttribute(): string
    {
        return $this->action ?? 'help';
    }

    /**
     * Get the response message.
     */
    public function getResponseMessageAttribute(): string
    {
        return $this->response['message'] ?? '';
    }

    /**
     * Get the response steps.
     */
    public function getResponseStepsAttribute(): array
    {
        return $this->response['steps'] ?? [];
    }

    /**
     * Get the response navigation.
     */
    public function getResponseNavigationAttribute(): string
    {
        return $this->response['navigation'] ?? '';
    }

    /**
     * Get the response related features.
     */
    public function getResponseRelatedFeaturesAttribute(): array
    {
        return $this->response['related_features'] ?? [];
    }

    /**
     * Get the response help articles.
     */
    public function getResponseHelpArticlesAttribute(): array
    {
        return $this->response['help_articles'] ?? [];
    }

    /**
     * Check if the response has steps.
     */
    public function hasSteps(): bool
    {
        return !empty($this->getResponseStepsAttribute());
    }

    /**
     * Check if the response has help articles.
     */
    public function hasHelpArticles(): bool
    {
        return !empty($this->getResponseHelpArticlesAttribute());
    }

    /**
     * Get the number of steps.
     */
    public function getStepsCountAttribute(): int
    {
        return count($this->getResponseStepsAttribute());
    }

    /**
     * Get the number of help articles.
     */
    public function getHelpArticlesCountAttribute(): int
    {
        return count($this->getResponseHelpArticlesAttribute());
    }

    /**
     * Get the number of related features.
     */
    public function getRelatedFeaturesCountAttribute(): int
    {
        return count($this->getResponseRelatedFeaturesAttribute());
    }

    /**
     * Get a summary of the conversation.
     */
    public function getSummaryAttribute(): string
    {
        $summary = "Query: {$this->query}";
        
        if ($this->hasFeature()) {
            $summary .= " | Feature: {$this->feature}";
        }
        
        if ($this->action) {
            $summary .= " | Action: {$this->action}";
        }
        
        $summary .= " | Confidence: {$this->confidence}%";
        
        return $summary;
    }
}

