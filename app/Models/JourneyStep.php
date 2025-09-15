<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JourneyStep extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'journey_id',
        'step_type',
        'config',
        'order_no',
        'is_active',
        'conditions',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'config' => 'array',
        'conditions' => 'array',
        'is_active' => 'boolean',
        'order_no' => 'integer',
    ];

    /**
     * Get the journey that owns the step.
     */
    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    /**
     * Get the executions that are currently on this step.
     */
    public function executions(): HasMany
    {
        return $this->hasMany(JourneyExecution::class, 'current_step_id');
    }

    /**
     * Scope a query to only include active steps.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include steps of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('step_type', $type);
    }

    /**
     * Get available step types.
     */
    public static function getAvailableStepTypes(): array
    {
        return [
            'send_email' => 'Send Email',
            'wait' => 'Wait/Delay',
            'create_task' => 'Create Task',
            'update_contact' => 'Update Contact',
            'create_deal' => 'Create Deal',
            'update_deal' => 'Update Deal',
            'add_tag' => 'Add Tag',
            'remove_tag' => 'Remove Tag',
            'update_lead_score' => 'Update Lead Score',
            'send_sms' => 'Send SMS',
            'webhook' => 'Webhook Call',
            'condition' => 'Conditional Branch',
            'end' => 'End Journey',
        ];
    }

    /**
     * Get step type configuration schema.
     */
    public static function getStepTypeConfigSchema(string $stepType): array
    {
        $schemas = [
            'send_email' => [
                'template_id' => 'required|integer|exists:email_templates,id',
                'subject' => 'nullable|string|max:255',
                'from_email' => 'nullable|email',
                'from_name' => 'nullable|string|max:255',
            ],
            'wait' => [
                'days' => 'required|integer|min:0|max:365',
                'hours' => 'nullable|integer|min:0|max:23',
                'minutes' => 'nullable|integer|min:0|max:59',
            ],
            'create_task' => [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'due_date' => 'nullable|date|after:now',
                'priority' => 'nullable|in:low,medium,high',
                'assigned_to' => 'nullable|integer|exists:users,id',
            ],
            'update_contact' => [
                'fields' => 'required|array',
                'fields.*.field' => 'required|string',
                'fields.*.value' => 'required',
            ],
            'create_deal' => [
                'name' => 'required|string|max:255',
                'value' => 'nullable|numeric|min:0',
                'stage' => 'nullable|string|max:100',
                'close_date' => 'nullable|date|after:now',
                'assigned_to' => 'nullable|integer|exists:users,id',
            ],
            'add_tag' => [
                'tags' => 'required|array',
                'tags.*' => 'required|string|max:50',
            ],
            'remove_tag' => [
                'tags' => 'required|array',
                'tags.*' => 'required|string|max:50',
            ],
            'update_lead_score' => [
                'action' => 'required|in:add,subtract,set',
                'points' => 'required|integer|min:0|max:1000',
            ],
            'send_sms' => [
                'message' => 'required|string|max:160',
                'from_number' => 'nullable|string|max:20',
            ],
            'webhook' => [
                'url' => 'required|url',
                'method' => 'nullable|in:GET,POST,PUT,DELETE',
                'headers' => 'nullable|array',
                'payload' => 'nullable|array',
            ],
            'condition' => [
                'field' => 'required|string',
                'operator' => 'required|in:equals,not_equals,greater_than,less_than,contains,not_contains',
                'value' => 'required',
                'true_step' => 'nullable|integer|exists:journey_steps,id',
                'false_step' => 'nullable|integer|exists:journey_steps,id',
            ],
            'end' => [
                'reason' => 'nullable|string|max:255',
            ],
        ];

        return $schemas[$stepType] ?? [];
    }

    /**
     * Validate step configuration.
     */
    public function validateConfig(): bool
    {
        $schema = self::getStepTypeConfigSchema($this->step_type);
        
        if (empty($schema)) {
            return true; // No validation schema means it's valid
        }

        // Basic validation - in a real implementation, you'd use Laravel's validator
        foreach ($schema as $field => $rules) {
            if (str_contains($rules, 'required') && !isset($this->config[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get step description for display.
     */
    public function getDescription(): string
    {
        $stepTypes = self::getAvailableStepTypes();
        $typeName = $stepTypes[$this->step_type] ?? $this->step_type;
        
        switch ($this->step_type) {
            case 'send_email':
                return "Send Email" . (isset($this->config['template_id']) ? " (Template: {$this->config['template_id']})" : "");
            case 'wait':
                $days = $this->config['days'] ?? 0;
                $hours = $this->config['hours'] ?? 0;
                $minutes = $this->config['minutes'] ?? 0;
                return "Wait {$days} days, {$hours} hours, {$minutes} minutes";
            case 'create_task':
                return "Create Task: " . ($this->config['title'] ?? 'Untitled');
            case 'update_contact':
                return "Update Contact Fields";
            case 'create_deal':
                return "Create Deal: " . ($this->config['name'] ?? 'Untitled');
            case 'add_tag':
                $tags = $this->config['tags'] ?? [];
                return "Add Tags: " . implode(', ', $tags);
            case 'remove_tag':
                $tags = $this->config['tags'] ?? [];
                return "Remove Tags: " . implode(', ', $tags);
            case 'update_lead_score':
                $action = $this->config['action'] ?? 'add';
                $points = $this->config['points'] ?? 0;
                return "Update Lead Score: {$action} {$points} points";
            case 'send_sms':
                return "Send SMS: " . substr($this->config['message'] ?? '', 0, 30) . "...";
            case 'webhook':
                return "Webhook: " . ($this->config['url'] ?? 'No URL');
            case 'condition':
                $field = $this->config['field'] ?? 'field';
                $operator = $this->config['operator'] ?? 'equals';
                $value = $this->config['value'] ?? 'value';
                return "If {$field} {$operator} {$value}";
            case 'end':
                return "End Journey";
            default:
                return $typeName;
        }
    }

    /**
     * Check if step conditions are met.
     */
    public function conditionsMet(array $context = []): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        // Simple condition evaluation - can be extended for complex logic
        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $expectedValue = $condition['value'] ?? null;
            $actualValue = $context[$field] ?? null;

            if (!$this->evaluateCondition($actualValue, $operator, $expectedValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition.
     */
    private function evaluateCondition($actualValue, string $operator, $expectedValue): bool
    {
        switch ($operator) {
            case 'equals':
                return $actualValue == $expectedValue;
            case 'not_equals':
                return $actualValue != $expectedValue;
            case 'greater_than':
                return $actualValue > $expectedValue;
            case 'less_than':
                return $actualValue < $expectedValue;
            case 'contains':
                return str_contains((string)$actualValue, (string)$expectedValue);
            case 'not_contains':
                return !str_contains((string)$actualValue, (string)$expectedValue);
            default:
                return false;
        }
    }
}
