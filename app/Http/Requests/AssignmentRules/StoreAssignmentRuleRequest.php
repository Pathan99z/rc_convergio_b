<?php

namespace App\Http\Requests\AssignmentRules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin') || $this->user()->can('manage_assignments');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'criteria' => ['required', 'array'],
            'criteria.all' => ['sometimes', 'array'],
            'criteria.all.*' => ['required', 'array'],
            'criteria.all.*.field' => ['required', 'string'],
            'criteria.all.*.op' => ['required', 'string', Rule::in([
                'eq', 'ne', 'in', 'not_in', 'contains', 'exists', 'not_exists',
                'gt', 'gte', 'lt', 'lte'
            ])],
            'criteria.all.*.value' => ['nullable'],
            'criteria.any' => ['sometimes', 'array'],
            'criteria.any.*' => ['required', 'array'],
            'criteria.any.*.field' => ['required', 'string'],
            'criteria.any.*.op' => ['required', 'string', Rule::in([
                'eq', 'ne', 'in', 'not_in', 'contains', 'exists', 'not_exists',
                'gt', 'gte', 'lt', 'lte'
            ])],
            'criteria.any.*.value' => ['nullable'],
            // Support for single condition (backward compatibility)
            'criteria.field' => ['sometimes', 'string'],
            'criteria.op' => ['sometimes', 'string', Rule::in([
                'eq', 'ne', 'in', 'not_in', 'contains', 'exists', 'not_exists',
                'gt', 'gte', 'lt', 'lte'
            ])],
            'criteria.value' => ['nullable'],
            'action' => ['required', 'array'],
            'action.type' => ['required', 'string', Rule::in(['assign_user', 'assign_team_round_robin', 'assign_default'])],
            'action.user_id' => ['required_if:action.type,assign_user', 'integer', 'exists:users,id'],
            'action.team_id' => ['required_if:action.type,assign_team_round_robin', 'integer'],
            'active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Rule name is required.',
            'name.max' => 'Rule name cannot exceed 255 characters.',
            'priority.required' => 'Priority is required.',
            'priority.integer' => 'Priority must be a number.',
            'priority.min' => 'Priority must be at least 1.',
            'priority.max' => 'Priority cannot exceed 1000.',
            'criteria.required' => 'Rule criteria are required.',
            'criteria.array' => 'Criteria must be an array.',
            'action.required' => 'Rule action is required.',
            'action.type.required' => 'Action type is required.',
            'action.type.in' => 'Invalid action type.',
            'action.user_id.required_if' => 'User ID is required when action type is assign_user.',
            'action.user_id.exists' => 'Selected user does not exist.',
            'action.team_id.required_if' => 'Team ID is required when action type is assign_team_round_robin.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate criteria structure
            if ($this->has('criteria')) {
                $this->validateCriteriaStructure($validator);
            }

            // Validate action structure
            if ($this->has('action')) {
                $this->validateActionStructure($validator);
            }
        });
    }

    /**
     * Validate criteria structure.
     */
    private function validateCriteriaStructure($validator): void
    {
        $criteria = $this->input('criteria');

        if (is_array($criteria)) {
            if (isset($criteria['all']) && is_array($criteria['all'])) {
                foreach ($criteria['all'] as $index => $condition) {
                    $this->validateCondition($validator, "criteria.all.{$index}", $condition);
                }
            } elseif (isset($criteria['any']) && is_array($criteria['any'])) {
                foreach ($criteria['any'] as $index => $condition) {
                    $this->validateCondition($validator, "criteria.any.{$index}", $condition);
                }
            } else {
                // Single condition
                $this->validateCondition($validator, 'criteria', $criteria);
            }
        }
    }

    /**
     * Validate a single condition.
     */
    private function validateCondition($validator, string $path, array $condition): void
    {
        if (!isset($condition['field']) || !isset($condition['op'])) {
            $validator->errors()->add($path, 'Each condition must have a field and operator.');
            return;
        }

        $field = $condition['field'];
        $operator = $condition['op'];

        // Validate field names
        $validFields = [
            'first_name', 'last_name', 'email', 'phone', 'lifecycle_stage', 'source',
            'tags', 'lead_score', 'company_name', 'company_industry', 'company_size',
            'company_country', 'title', 'value', 'currency', 'status', 'probability',
            'pipeline_id', 'stage_id', 'contact_lifecycle_stage', 'contact_source',
            'contact_email', 'record_type'
        ];

        if (!in_array($field, $validFields)) {
            $validator->errors()->add($path . '.field', 'Invalid field name.');
        }

        // Validate operator-specific requirements
        if (in_array($operator, ['in', 'not_in']) && !is_array($condition['value'] ?? null)) {
            $validator->errors()->add($path . '.value', 'Value must be an array for ' . $operator . ' operator.');
        }

        if (in_array($operator, ['gt', 'gte', 'lt', 'lte']) && !is_numeric($condition['value'] ?? null)) {
            $validator->errors()->add($path . '.value', 'Value must be numeric for ' . $operator . ' operator.');
        }
    }

    /**
     * Validate action structure.
     */
    private function validateActionStructure($validator): void
    {
        $action = $this->input('action');
        $type = $action['type'] ?? null;

        if ($type === 'assign_user' && !isset($action['user_id'])) {
            $validator->errors()->add('action.user_id', 'User ID is required for assign_user action.');
        }

        if ($type === 'assign_team_round_robin' && !isset($action['team_id'])) {
            $validator->errors()->add('action.team_id', 'Team ID is required for assign_team_round_robin action.');
        }
    }
}
