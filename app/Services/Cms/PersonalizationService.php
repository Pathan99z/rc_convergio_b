<?php

namespace App\Services\Cms;

use App\Models\Cms\PersonalizationRule;
use Illuminate\Support\Facades\Log;

class PersonalizationService
{
    /**
     * Evaluate personalization rules for a page and return personalized content.
     */
    public function evaluateRules(int $pageId, array $context): array
    {
        try {
            $rules = PersonalizationRule::forPage($pageId)
                                       ->active()
                                       ->byPriority()
                                       ->get();

            $personalizedContent = [];

            foreach ($rules as $rule) {
                if ($rule->evaluateConditions($context)) {
                    $personalizedContent[$rule->section_id] = [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'variant_data' => $rule->variant_data,
                        'priority' => $rule->priority
                    ];

                    // Track performance
                    $this->trackRuleApplication($rule, $context);
                }
            }

            return $personalizedContent;

        } catch (\Exception $e) {
            Log::error('Failed to evaluate personalization rules', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
                'context' => $context
            ]);

            return [];
        }
    }

    /**
     * Create a personalization rule.
     */
    public function createRule(array $data): PersonalizationRule
    {
        return PersonalizationRule::create($data);
    }

    /**
     * Test if conditions would match given context.
     */
    public function testConditions(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get available personalization fields.
     */
    public function getAvailableFields(): array
    {
        return [
            'user' => [
                'user_id' => 'User ID',
                'user_role' => 'User Role',
                'user_email' => 'User Email',
            ],
            'location' => [
                'country' => 'Country',
                'region' => 'Region/State',
                'city' => 'City',
                'ip_address' => 'IP Address',
            ],
            'device' => [
                'device' => 'Device Type',
                'user_agent' => 'User Agent',
                'browser' => 'Browser',
                'os' => 'Operating System',
            ],
            'behavior' => [
                'referrer' => 'Referrer URL',
                'utm_source' => 'UTM Source',
                'utm_medium' => 'UTM Medium',
                'utm_campaign' => 'UTM Campaign',
                'visit_count' => 'Visit Count',
                'page_views' => 'Page Views',
            ],
            'time' => [
                'hour' => 'Hour of Day',
                'day_of_week' => 'Day of Week',
                'date' => 'Date',
                'timezone' => 'Timezone',
            ]
        ];
    }

    /**
     * Get condition operators.
     */
    public function getOperators(): array
    {
        return [
            'equals' => 'Equals',
            'not_equals' => 'Not Equals',
            'contains' => 'Contains',
            'not_contains' => 'Does Not Contain',
            'starts_with' => 'Starts With',
            'ends_with' => 'Ends With',
            'in' => 'In List',
            'not_in' => 'Not In List',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
            'between' => 'Between',
        ];
    }

    /**
     * Track rule application for performance analysis.
     */
    protected function trackRuleApplication(PersonalizationRule $rule, array $context): void
    {
        try {
            $performanceData = $rule->performance_data ?? [];
            $performanceData['applications'] = ($performanceData['applications'] ?? 0) + 1;
            $performanceData['last_applied'] = now()->toIso8601String();
            
            $rule->update(['performance_data' => $performanceData]);

        } catch (\Exception $e) {
            Log::warning('Failed to track rule application', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? '';
        $contextValue = data_get($context, $field);

        switch ($operator) {
            case 'equals':
                return $contextValue == $value;
            case 'not_equals':
                return $contextValue != $value;
            case 'contains':
                return str_contains(strtolower((string)$contextValue), strtolower((string)$value));
            case 'not_contains':
                return !str_contains(strtolower((string)$contextValue), strtolower((string)$value));
            case 'starts_with':
                return str_starts_with(strtolower((string)$contextValue), strtolower((string)$value));
            case 'ends_with':
                return str_ends_with(strtolower((string)$contextValue), strtolower((string)$value));
            case 'in':
                return in_array($contextValue, (array) $value);
            case 'not_in':
                return !in_array($contextValue, (array) $value);
            case 'greater_than':
                return is_numeric($contextValue) && is_numeric($value) && $contextValue > $value;
            case 'less_than':
                return is_numeric($contextValue) && is_numeric($value) && $contextValue < $value;
            case 'between':
                if (is_array($value) && count($value) === 2 && is_numeric($contextValue)) {
                    return $contextValue >= $value[0] && $contextValue <= $value[1];
                }
                return false;
            default:
                return false;
        }
    }
}



