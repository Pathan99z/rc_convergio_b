<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntentActionScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'action',
        'score_default',
        'score_overrides',
    ];

    protected $casts = [
        'score_overrides' => 'array',
    ];

    /**
     * Get the tenant that owns this scoring configuration.
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include scores for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include scores for a specific action.
     */
    public function scopeForAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Get default action scores for all actions.
     */
    public static function getDefaultActionScores(): array
    {
        return [
            'form_submit' => 40,
            'form_fill' => 30,
            'page_view' => 8,
            'visit' => 5,
            'download' => 40,
            'pricing_view' => 20,
            'demo_request' => 60,
            'trial_signup' => 70,
            'contact_form' => 45,
            'email_open' => 10,
            'email_click' => 25,
            'campaign_view' => 15,
            'video_watch' => 20,
            'whitepaper_download' => 35,
            'case_study_view' => 25,
            'product_tour' => 40,
            'purchase_intent' => 70,
            'chat_start' => 30,
            'webinar_register' => 50,
            'newsletter_signup' => 15,
            'social_share' => 10,
            'search' => 5,
            'cart_add' => 50,
            'checkout_start' => 80,
            'purchase' => 100,
        ];
    }

    /**
     * Get default context boost rules.
     */
    public static function getDefaultContextBoosts(): array
    {
        return [
            'page_url_contains' => [
                '/pricing' => 10,
                '/contact' => 15,
                '/demo' => 20,
                '/trial' => 25,
                '/buy' => 30,
                '/purchase' => 35,
                '/checkout' => 40,
                '/signup' => 20,
                '/register' => 20,
                '/download' => 15,
                '/whitepaper' => 15,
                '/case-study' => 10,
                '/product' => 10,
                '/features' => 8,
                '/about' => 5,
            ],
            'referrer_contains' => [
                'google' => 5,
                'facebook' => 3,
                'linkedin' => 8,
                'twitter' => 3,
                'email' => 10,
                'direct' => 0,
            ],
            'user_agent_contains' => [
                'mobile' => -5,
                'tablet' => -3,
                'bot' => -20,
            ],
            'duration_seconds' => [
                'min_30' => 5,    // 30+ seconds
                'min_60' => 10,   // 1+ minute
                'min_120' => 15,  // 2+ minutes
                'min_300' => 20,  // 5+ minutes
            ],
            'return_visitor' => 15,
            'high_value_page' => 20,
            'multiple_pages' => 10,
        ];
    }

    /**
     * Initialize default scoring configuration for a tenant.
     */
    public static function initializeForTenant(int $tenantId): void
    {
        $defaultScores = self::getDefaultActionScores();
        
        foreach ($defaultScores as $action => $score) {
            self::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'action' => $action,
                ],
                [
                    'score_default' => $score,
                    'score_overrides' => [],
                ]
            );
        }
    }

    /**
     * Get scoring configuration for a tenant.
     */
    public static function getConfigForTenant(int $tenantId): array
    {
        $scores = self::forTenant($tenantId)->get()->keyBy('action');
        $defaultScores = self::getDefaultActionScores();
        
        $config = [];
        foreach ($defaultScores as $action => $defaultScore) {
            $config[$action] = [
                'default' => $scores->get($action)?->score_default ?? $defaultScore,
                'overrides' => $scores->get($action)?->score_overrides ?? [],
            ];
        }
        
        return $config;
    }

    /**
     * Update scoring configuration for a tenant.
     */
    public static function updateConfigForTenant(int $tenantId, array $config): void
    {
        foreach ($config as $action => $actionConfig) {
            self::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'action' => $action,
                ],
                [
                    'score_default' => $actionConfig['default'] ?? self::getDefaultActionScores()[$action] ?? 10,
                    'score_overrides' => $actionConfig['overrides'] ?? [],
                ]
            );
        }
    }
}
