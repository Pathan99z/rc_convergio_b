<?php

namespace App\Services;

use App\Models\IntentActionScore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ScoringEngineService
{
    /**
     * Calculate intent score for an event using config-driven scoring.
     */
    public function scoreFor(array $eventData, int $tenantId): int
    {
        try {
            $action = $eventData['action'] ?? 'unknown';
            
            // Get base score for the action
            $baseScore = $this->getActionScore($action, $tenantId);
            
            // Apply context boosts
            $contextBoost = $this->calculateContextBoost($eventData);
            
            // Calculate final score
            $finalScore = $baseScore + $contextBoost;
            
            // Ensure score is within valid range (0-100)
            $finalScore = max(0, min(100, $finalScore));
            
            Log::debug('Intent score calculated', [
                'tenant_id' => $tenantId,
                'action' => $action,
                'base_score' => $baseScore,
                'context_boost' => $contextBoost,
                'final_score' => $finalScore,
                'event_data' => $eventData,
            ]);
            
            return $finalScore;
            
        } catch (\Exception $e) {
            Log::error('Scoring engine failed', [
                'tenant_id' => $tenantId,
                'event_data' => $eventData,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to default score
            return $this->getDefaultActionScore($eventData['action'] ?? 'unknown');
        }
    }

    /**
     * Get action score for a specific action and tenant.
     */
    private function getActionScore(string $action, int $tenantId): int
    {
        $cacheKey = "action_score_{$tenantId}_{$action}";
        
        return Cache::remember($cacheKey, 3600, function () use ($action, $tenantId) {
            $config = IntentActionScore::forTenant($tenantId)
                ->forAction($action)
                ->first();
            
            if ($config) {
                return $config->score_default;
            }
            
            // Fallback to default scores
            return $this->getDefaultActionScore($action);
        });
    }

    /**
     * Get default action score for an action.
     */
    private function getDefaultActionScore(string $action): int
    {
        $defaultScores = IntentActionScore::getDefaultActionScores();
        return $defaultScores[$action] ?? 10; // Default fallback
    }

    /**
     * Calculate context boost based on event data.
     */
    private function calculateContextBoost(array $eventData): int
    {
        $boost = 0;
        
        // Page URL boosts
        if (isset($eventData['page_url'])) {
            $boost += $this->getPageUrlBoost($eventData['page_url']);
        }
        
        // Referrer boosts
        if (isset($eventData['referrer'])) {
            $boost += $this->getReferrerBoost($eventData['referrer']);
        }
        
        // User agent boosts
        if (isset($eventData['user_agent'])) {
            $boost += $this->getUserAgentBoost($eventData['user_agent']);
        }
        
        // Duration boosts
        if (isset($eventData['duration_seconds'])) {
            $boost += $this->getDurationBoost($eventData['duration_seconds']);
        }
        
        // Metadata boosts
        if (isset($eventData['metadata']) && is_array($eventData['metadata'])) {
            $boost += $this->getMetadataBoost($eventData['metadata']);
        }
        
        return $boost;
    }

    /**
     * Get page URL boost.
     */
    private function getPageUrlBoost(string $pageUrl): int
    {
        $urlBoosts = IntentActionScore::getDefaultContextBoosts()['page_url_contains'];
        
        foreach ($urlBoosts as $pattern => $boost) {
            if (strpos($pageUrl, $pattern) !== false) {
                return $boost;
            }
        }
        
        return 0;
    }

    /**
     * Get referrer boost.
     */
    private function getReferrerBoost(string $referrer): int
    {
        $referrerBoosts = IntentActionScore::getDefaultContextBoosts()['referrer_contains'];
        
        foreach ($referrerBoosts as $pattern => $boost) {
            if (strpos(strtolower($referrer), strtolower($pattern)) !== false) {
                return $boost;
            }
        }
        
        return 0;
    }

    /**
     * Get user agent boost.
     */
    private function getUserAgentBoost(string $userAgent): int
    {
        $uaBoosts = IntentActionScore::getDefaultContextBoosts()['user_agent_contains'];
        
        foreach ($uaBoosts as $pattern => $boost) {
            if (strpos(strtolower($userAgent), strtolower($pattern)) !== false) {
                return $boost;
            }
        }
        
        return 0;
    }

    /**
     * Get duration boost.
     */
    private function getDurationBoost(int $durationSeconds): int
    {
        $durationBoosts = IntentActionScore::getDefaultContextBoosts()['duration_seconds'];
        
        if ($durationSeconds >= 300) return $durationBoosts['min_300']; // 5+ minutes
        if ($durationSeconds >= 120) return $durationBoosts['min_120']; // 2+ minutes
        if ($durationSeconds >= 60) return $durationBoosts['min_60'];  // 1+ minute
        if ($durationSeconds >= 30) return $durationBoosts['min_30'];  // 30+ seconds
        
        return 0;
    }

    /**
     * Get metadata boost.
     */
    private function getMetadataBoost(array $metadata): int
    {
        $boost = 0;
        $contextBoosts = IntentActionScore::getDefaultContextBoosts();
        
        // Return visitor boost
        if (isset($metadata['return_visitor']) && $metadata['return_visitor']) {
            $boost += $contextBoosts['return_visitor'];
        }
        
        // High value page boost
        if (isset($metadata['high_value_page']) && $metadata['high_value_page']) {
            $boost += $contextBoosts['high_value_page'];
        }
        
        // Multiple pages boost
        if (isset($metadata['pages_viewed']) && $metadata['pages_viewed'] > 1) {
            $boost += $contextBoosts['multiple_pages'];
        }
        
        return $boost;
    }

    /**
     * Get intent level based on score (maintains backward compatibility).
     */
    public function getIntentLevel(int $score): string
    {
        if ($score >= 80) return 'very_high';
        if ($score >= 60) return 'high';
        if ($score >= 40) return 'medium';
        if ($score >= 20) return 'low';
        return 'very_low';
    }

    /**
     * Get intent level label.
     */
    public function getIntentLevelLabel(string $level): string
    {
        $labels = [
            'very_high' => 'Very High Intent',
            'high' => 'High Intent',
            'medium' => 'Medium Intent',
            'low' => 'Low Intent',
            'very_low' => 'Very Low Intent',
        ];

        return $labels[$level] ?? 'Unknown';
    }

    /**
     * Get scoring configuration for a tenant.
     */
    public function getScoringConfig(int $tenantId): array
    {
        $cacheKey = "scoring_config_{$tenantId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($tenantId) {
            return IntentActionScore::getConfigForTenant($tenantId);
        });
    }

    /**
     * Update scoring configuration for a tenant.
     */
    public function updateScoringConfig(int $tenantId, array $config): void
    {
        IntentActionScore::updateConfigForTenant($tenantId, $config);
        
        // Clear cache
        Cache::forget("scoring_config_{$tenantId}");
        
        // Clear individual action score caches
        foreach (array_keys($config) as $action) {
            Cache::forget("action_score_{$tenantId}_{$action}");
        }
    }

    /**
     * Initialize scoring configuration for a tenant.
     */
    public function initializeScoringConfig(int $tenantId): void
    {
        IntentActionScore::initializeForTenant($tenantId);
        
        // Clear cache
        Cache::forget("scoring_config_{$tenantId}");
    }

    /**
     * Get scoring statistics for a tenant.
     */
    public function getScoringStats(int $tenantId): array
    {
        $config = $this->getScoringConfig($tenantId);
        
        $stats = [
            'total_actions' => count($config),
            'customized_actions' => 0,
            'default_actions' => 0,
            'score_ranges' => [
                'very_high' => 0, // 80-100
                'high' => 0,      // 60-79
                'medium' => 0,    // 40-59
                'low' => 0,       // 20-39
                'very_low' => 0,  // 0-19
            ],
        ];
        
        $defaultScores = IntentActionScore::getDefaultActionScores();
        
        foreach ($config as $action => $actionConfig) {
            $score = $actionConfig['default'];
            
            // Check if customized
            if (isset($defaultScores[$action]) && $defaultScores[$action] !== $score) {
                $stats['customized_actions']++;
            } else {
                $stats['default_actions']++;
            }
            
            // Categorize by score range
            if ($score >= 80) $stats['score_ranges']['very_high']++;
            elseif ($score >= 60) $stats['score_ranges']['high']++;
            elseif ($score >= 40) $stats['score_ranges']['medium']++;
            elseif ($score >= 20) $stats['score_ranges']['low']++;
            else $stats['score_ranges']['very_low']++;
        }
        
        return $stats;
    }

    /**
     * Test scoring configuration with sample data.
     */
    public function testScoring(int $tenantId, array $testData): array
    {
        $results = [];
        
        foreach ($testData as $testCase) {
            $score = $this->scoreFor($testCase, $tenantId);
            $level = $this->getIntentLevel($score);
            
            $results[] = [
                'test_case' => $testCase,
                'score' => $score,
                'level' => $level,
                'level_label' => $this->getIntentLevelLabel($level),
            ];
        }
        
        return $results;
    }
}
