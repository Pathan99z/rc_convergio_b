<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadScoringRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'condition',
        'points',
        'is_active',
        'priority',
        'metadata',
        'tenant_id',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'condition' => 'array',
        'points' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns the scoring rule.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the scoring rule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include rules for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by priority (highest first).
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Get available event types for scoring rules.
     */
    public static function getAvailableEventTypes(): array
    {
        return [
            'email_open' => 'Email Open',
            'email_click' => 'Email Click',
            'email_bounce' => 'Email Bounce',
            'email_unsubscribe' => 'Email Unsubscribe',
            'form_submission' => 'Form Submission',
            'page_visit' => 'Page Visit',
            'file_download' => 'File Download',
            'event_attendance' => 'Event Attendance',
            'deal_created' => 'Deal Created',
            'deal_updated' => 'Deal Updated',
            'deal_closed' => 'Deal Closed',
            'contact_created' => 'Contact Created',
            'contact_updated' => 'Contact Updated',
            'company_created' => 'Company Created',
            'company_updated' => 'Company Updated',
            'campaign_sent' => 'Campaign Sent',
            'campaign_delivered' => 'Campaign Delivered',
            'campaign_opened' => 'Campaign Opened',
            'campaign_clicked' => 'Campaign Clicked',
        ];
    }

    /**
     * Get available condition operators.
     */
    public static function getAvailableOperators(): array
    {
        return [
            'equals' => 'Equals',
            'not_equals' => 'Not Equals',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
            'greater_than_or_equal' => 'Greater Than or Equal',
            'less_than_or_equal' => 'Less Than or Equal',
            'contains' => 'Contains',
            'not_contains' => 'Not Contains',
            'in' => 'In',
            'not_in' => 'Not In',
            'is_null' => 'Is Null',
            'is_not_null' => 'Is Not Null',
        ];
    }

    /**
     * Validate rule condition structure.
     */
    public function validateCondition(): bool
    {
        if (!is_array($this->condition)) {
            return false;
        }

        // Check for required fields
        $requiredFields = ['event'];
        foreach ($requiredFields as $field) {
            if (!isset($this->condition[$field])) {
                return false;
            }
        }

        // Validate event type
        $availableEvents = array_keys(self::getAvailableEventTypes());
        if (!in_array($this->condition['event'], $availableEvents)) {
            return false;
        }

        // Validate criteria if present
        if (isset($this->condition['criteria']) && is_array($this->condition['criteria'])) {
            foreach ($this->condition['criteria'] as $criterion) {
                if (!isset($criterion['field']) || !isset($criterion['operator'])) {
                    return false;
                }
                
                $availableOperators = array_keys(self::getAvailableOperators());
                if (!in_array($criterion['operator'], $availableOperators)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get rule condition as human-readable text.
     */
    public function getConditionDescription(): string
    {
        if (!$this->validateCondition()) {
            return 'Invalid condition';
        }

        $eventTypes = self::getAvailableEventTypes();
        $eventName = $eventTypes[$this->condition['event']] ?? $this->condition['event'];
        
        $description = "When {$eventName} occurs";
        
        if (isset($this->condition['criteria']) && is_array($this->condition['criteria'])) {
            $criteria = [];
            foreach ($this->condition['criteria'] as $criterion) {
                $operators = self::getAvailableOperators();
                $operator = $operators[$criterion['operator']] ?? $criterion['operator'];
                $value = $criterion['value'] ?? '';
                $criteria[] = "{$criterion['field']} {$operator} {$value}";
            }
            $description .= " and " . implode(' and ', $criteria);
        }
        
        return $description;
    }

    /**
     * Check if this rule matches the given event data.
     */
    public function matchesEvent(array $eventData): bool
    {
        if (!$this->validateCondition() || !$this->is_active) {
            return false;
        }

        // Check event type
        if ($eventData['event'] !== $this->condition['event']) {
            return false;
        }

        // Check criteria if present
        if (isset($this->condition['criteria']) && is_array($this->condition['criteria'])) {
            foreach ($this->condition['criteria'] as $criterion) {
                if (!$this->evaluateCriterion($criterion, $eventData)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Evaluate a single criterion against event data.
     */
    private function evaluateCriterion(array $criterion, array $eventData): bool
    {
        $field = $criterion['field'];
        $operator = $criterion['operator'];
        $expectedValue = $criterion['value'] ?? null;
        $actualValue = $eventData[$field] ?? null;

        switch ($operator) {
            case 'equals':
                return $actualValue == $expectedValue;
            case 'not_equals':
                return $actualValue != $expectedValue;
            case 'greater_than':
                return $actualValue > $expectedValue;
            case 'less_than':
                return $actualValue < $expectedValue;
            case 'greater_than_or_equal':
                return $actualValue >= $expectedValue;
            case 'less_than_or_equal':
                return $actualValue <= $expectedValue;
            case 'contains':
                return str_contains((string)$actualValue, (string)$expectedValue);
            case 'not_contains':
                return !str_contains((string)$actualValue, (string)$expectedValue);
            case 'in':
                return in_array($actualValue, (array)$expectedValue);
            case 'not_in':
                return !in_array($actualValue, (array)$expectedValue);
            case 'is_null':
                return is_null($actualValue);
            case 'is_not_null':
                return !is_null($actualValue);
            default:
                return false;
        }
    }
}
