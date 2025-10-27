<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'priority',
        'criteria',
        'action',
        'active',
        'created_by',
    ];

    protected $casts = [
        'criteria' => 'array',
        'action' => 'array',
        'active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the tenant that owns the rule.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the rule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the audit records for this rule.
     */
    public function audits(): HasMany
    {
        return $this->hasMany(AssignmentAudit::class, 'rule_id');
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
        return $query->where('active', true);
    }

    /**
     * Scope a query to order by priority (ascending - lower number = higher priority).
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Scope a query to filter by record type.
     */
    public function scopeForRecordType($query, $recordType)
    {
        return $query->whereJsonContains('criteria->record_type', $recordType);
    }

    /**
     * Check if the rule matches the given record data.
     */
    public function matches(array $recordData): bool
    {
        if (!$this->active) {
            return false;
        }

        return $this->evaluateCriteria($this->criteria, $recordData);
    }

    /**
     * Recursively evaluate criteria conditions.
     */
    private function evaluateCriteria(array $criteria, array $recordData): bool
    {
        if (isset($criteria['all'])) {
            // ALL conditions must be true
            foreach ($criteria['all'] as $condition) {
                if (!$this->evaluateCondition($condition, $recordData)) {
                    return false;
                }
            }
            return true;
        }

        if (isset($criteria['any'])) {
            // ANY condition must be true
            foreach ($criteria['any'] as $condition) {
                if ($this->evaluateCondition($condition, $recordData)) {
                    return true;
                }
            }
            return false;
        }

        // Single condition
        return $this->evaluateCondition($criteria, $recordData);
    }

    /**
     * Evaluate a single condition.
     */
    private function evaluateCondition(array $condition, array $recordData): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['op'] ?? null;
        $value = $condition['value'] ?? null;

        if (!$field || !$operator) {
            return false;
        }

        $recordValue = $recordData[$field] ?? null;

        switch ($operator) {
            case 'eq':
                // Case-insensitive comparison for string fields to handle both 'Lead' and 'lead'
                if (is_string($recordValue) && is_string($value)) {
                    return strtolower($recordValue) === strtolower($value);
                }
                return $recordValue === $value;
            case 'ne':
                // Case-insensitive comparison for string fields
                if (is_string($recordValue) && is_string($value)) {
                    return strtolower($recordValue) !== strtolower($value);
                }
                return $recordValue !== $value;
            case 'in':
                if (is_array($value)) {
                    // Case-insensitive comparison for string arrays
                    if (is_string($recordValue)) {
                        return in_array(strtolower($recordValue), array_map('strtolower', $value));
                    }
                    return in_array($recordValue, $value);
                }
                return false;
            case 'not_in':
                if (is_array($value)) {
                    // Case-insensitive comparison for string arrays
                    if (is_string($recordValue)) {
                        return !in_array(strtolower($recordValue), array_map('strtolower', $value));
                    }
                    return !in_array($recordValue, $value);
                }
                return false;
            case 'contains':
                return is_string($recordValue) && is_string($value) && str_contains($recordValue, $value);
            case 'exists':
                return !is_null($recordValue) && $recordValue !== '';
            case 'not_exists':
                return is_null($recordValue) || $recordValue === '';
            case 'gt':
                return is_numeric($recordValue) && is_numeric($value) && $recordValue > $value;
            case 'gte':
                return is_numeric($recordValue) && is_numeric($value) && $recordValue >= $value;
            case 'lt':
                return is_numeric($recordValue) && is_numeric($value) && $recordValue < $value;
            case 'lte':
                return is_numeric($recordValue) && is_numeric($value) && $recordValue <= $value;
            default:
                return false;
        }
    }
}
