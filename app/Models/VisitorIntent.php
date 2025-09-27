<?php

namespace App\Models;

use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorIntent extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'contact_id',
        'company_id',
        'page_url',
        'score',
        'intent_level',
        'action',
        'metadata',
        'session_id',
        'ip_address',
        'user_agent',
        'tenant_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'score' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the contact that owns the visitor intent.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the company that owns the visitor intent.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get available tracking actions.
     */
    public static function getAvailableActions(): array
    {
        return [
            'visit' => 'Page Visit',
            'page_view' => 'Page View',
            'form_fill' => 'Form Fill',
            'download' => 'Download',
            'email_open' => 'Email Open',
            'email_click' => 'Email Click',
            'video_watch' => 'Video Watch',
            'demo_request' => 'Demo Request',
            'pricing_view' => 'Pricing View',
            'contact_form' => 'Contact Form',
            'chat_start' => 'Chat Start',
            'whitepaper_download' => 'Whitepaper Download',
            'case_study_view' => 'Case Study View',
            'product_tour' => 'Product Tour',
            'trial_signup' => 'Trial Signup',
            'purchase_intent' => 'Purchase Intent',
        ];
    }

    /**
     * Get intent level based on score.
     */
    public function getIntentLevelAttribute(): string
    {
        $score = $this->score;
        
        if ($score >= 80) return 'very_high';
        if ($score >= 60) return 'high';
        if ($score >= 40) return 'medium';
        if ($score >= 20) return 'low';
        return 'very_low';
    }

    /**
     * Get intent level label.
     */
    public function getIntentLevelLabelAttribute(): string
    {
        $labels = [
            'very_high' => 'Very High Intent',
            'high' => 'High Intent',
            'medium' => 'Medium Intent',
            'low' => 'Low Intent',
            'very_low' => 'Very Low Intent',
        ];

        return $labels[$this->intent_level] ?? 'Unknown';
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
     * Scope a query to only include visitor intents for a specific action.
     */
    public function scopeForAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include visitor intents for a specific contact.
     */
    public function scopeForContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Scope a query to only include visitor intents for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to only include visitor intents within a date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include visitor intents with medium intent.
     */
    public function scopeMediumIntent($query)
    {
        return $query->whereBetween('score', [40, 69]);
    }

    /**
     * Scope a query to only include visitor intents with low intent.
     */
    public function scopeLowIntent($query)
    {
        return $query->where('score', '<', 40);
    }
}