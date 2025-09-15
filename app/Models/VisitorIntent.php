<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VisitorIntent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'contact_id',
        'page_url',
        'duration_seconds',
        'action',
        'score',
        'metadata',
        'session_id',
        'ip_address',
        'user_agent',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'duration_seconds' => 'integer',
        'score' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the company associated with the visitor intent.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the contact associated with the visitor intent.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the tenant that owns the visitor intent.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include visitor intents for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include visitor intents for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to only include visitor intents for a specific contact.
     */
    public function scopeForContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Scope a query to only include visitor intents with a specific action.
     */
    public function scopeWithAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include visitor intents above a certain score.
     */
    public function scopeHighIntent($query, $minScore = 50)
    {
        return $query->where('score', '>=', $minScore);
    }

    /**
     * Scope a query to only include visitor intents for a specific page.
     */
    public function scopeForPage($query, $pageUrl)
    {
        return $query->where('page_url', $pageUrl);
    }

    /**
     * Scope a query to only include visitor intents for a specific session.
     */
    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Get available visitor actions.
     */
    public static function getAvailableActions(): array
    {
        return [
            'visit' => 'Page Visit',
            'download' => 'File Download',
            'form_fill' => 'Form Submission',
            'click' => 'Button/Link Click',
            'scroll' => 'Page Scroll',
            'hover' => 'Element Hover',
        ];
    }

    /**
     * Calculate intent score based on action and duration.
     */
    public static function calculateScore(string $action, int $durationSeconds = 0, array $metadata = []): int
    {
        $baseScores = [
            'visit' => 5,
            'scroll' => 10,
            'hover' => 15,
            'click' => 20,
            'download' => 30,
            'form_fill' => 50,
        ];

        $score = $baseScores[$action] ?? 5;

        // Add duration bonus (up to 20 points)
        if ($durationSeconds > 0) {
            $durationBonus = min(20, intval($durationSeconds / 10));
            $score += $durationBonus;
        }

        // Add metadata bonuses
        if (isset($metadata['page_depth']) && $metadata['page_depth'] > 3) {
            $score += 10; // Deep page engagement
        }

        if (isset($metadata['return_visitor']) && $metadata['return_visitor']) {
            $score += 15; // Return visitor bonus
        }

        if (isset($metadata['high_value_page']) && $metadata['high_value_page']) {
            $score += 25; // High-value page bonus
        }

        return min(100, $score); // Cap at 100
    }

    /**
     * Get intent level based on score.
     */
    public function getIntentLevel(): string
    {
        if ($this->score >= 80) {
            return 'very_high';
        } elseif ($this->score >= 60) {
            return 'high';
        } elseif ($this->score >= 40) {
            return 'medium';
        } elseif ($this->score >= 20) {
            return 'low';
        } else {
            return 'very_low';
        }
    }

    /**
     * Get intent level label.
     */
    public function getIntentLevelLabel(): string
    {
        $levels = [
            'very_high' => 'Very High Intent',
            'high' => 'High Intent',
            'medium' => 'Medium Intent',
            'low' => 'Low Intent',
            'very_low' => 'Very Low Intent',
        ];

        return $levels[$this->getIntentLevel()] ?? 'Unknown';
    }
}
