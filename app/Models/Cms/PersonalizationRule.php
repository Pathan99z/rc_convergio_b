<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalizationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cms_personalization_rules';

    protected $fillable = [
        'page_id',
        'section_id',
        'name',
        'description',
        'conditions',
        'variant_data',
        'priority',
        'is_active',
        'performance_data',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'variant_data' => 'array',
        'performance_data' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the page this rule belongs to.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get the user who created this rule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get rules for a specific page.
     */
    public function scopeForPage($query, int $pageId)
    {
        return $query->where('page_id', $pageId);
    }

    /**
     * Scope to get rules for a specific section.
     */
    public function scopeForSection($query, string $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }

    /**
     * Scope to order by priority.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Evaluate if conditions match for given context.
     */
    public function evaluateConditions(array $context): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? '';
        $contextValue = data_get($context, $field);

        switch ($operator) {
            case '=':
            case 'equals':
                return strtolower($contextValue) == strtolower($value);
            case '!=':
            case 'not_equals':
                return strtolower($contextValue) != strtolower($value);
            case 'contains':
                return str_contains(strtolower($contextValue), strtolower($value));
            case 'starts_with':
                return str_starts_with(strtolower($contextValue), strtolower($value));
            case 'in':
                return in_array($contextValue, (array) $value);
            case 'not_in':
                return !in_array($contextValue, (array) $value);
            default:
                return false;
        }
    }
}



