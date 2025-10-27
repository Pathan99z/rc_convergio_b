<?php

namespace App\Services;

use App\Models\AssignmentRule;
use App\Models\AssignmentDefault;
use App\Models\AssignmentCounter;
use App\Models\AssignmentAudit;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AssignmentService
{
    /**
     * Assign an owner for a record based on tenant rules and defaults.
     */
    public function assignOwnerForRecord($record, string $recordType, array $context = []): ?int
    {
        $tenantId = $record->tenant_id ?? $context['tenant_id'] ?? null;
        
        if (!$tenantId) {
            Log::warning('AssignmentService: No tenant_id found for record', [
                'record_type' => $recordType,
                'record_id' => $record->id ?? null,
                'context' => $context
            ]);
            return null;
        }

        // Check if automatic assignment is enabled for this tenant
        $defaults = AssignmentDefault::getForTenant($tenantId);
        if (!$defaults->enable_automatic_assignment) {
            Log::info('AssignmentService: Automatic assignment disabled for tenant', [
                'tenant_id' => $tenantId,
                'record_type' => $recordType,
                'record_id' => $record->id ?? null
            ]);
            return null;
        }

        // Prepare record data for rule evaluation
        $recordData = $this->prepareRecordData($record, $recordType, $context);

        // Try to find a matching rule
        $assignedUserId = $this->evaluateRules($tenantId, $recordType, $recordData);

        // Only use default assignment if there are active rules but none matched
        // If no active rules exist at all, don't assign (keep original owner)
        if (!$assignedUserId) {
            $hasActiveRules = AssignmentRule::forTenant($tenantId)->active()->exists();
            if ($hasActiveRules) {
                // There are active rules but none matched, use default assignment
                $assignedUserId = $this->getDefaultAssignment($tenantId, $defaults, $recordData);
            }
            // If no active rules exist, don't assign (return null)
        }

        // Log the assignment
        if ($assignedUserId) {
            $this->logAssignment($tenantId, $recordType, $record->id ?? null, $assignedUserId, $context);
        }

        return $assignedUserId;
    }

    /**
     * Apply assignment to a record (owner_id and team_id).
     */
    public function applyAssignmentToRecord($record, int $assignedUserId): void
    {
        $assignedUser = User::find($assignedUserId);
        if (!$assignedUser) {
            Log::warning('AssignmentService: Assigned user not found', [
                'assigned_user_id' => $assignedUserId,
                'record_type' => get_class($record),
                'record_id' => $record->id ?? null
            ]);
            return;
        }

        $updateData = ['owner_id' => $assignedUserId];
        
        // Set team_id if the record supports it and the assigned user has a team
        // Only set team_id if the feature is enabled or for backward compatibility
        $teamAccessService = app(\App\Services\TeamAccessService::class);
        if ($teamAccessService->enabled() && $assignedUser->team_id) {
            // Use TeamAccessService to check if model supports team_id
            if (method_exists($record, 'isFillable') && $record->isFillable('team_id')) {
                $updateData['team_id'] = $assignedUser->team_id;
            }
        } elseif (!$teamAccessService->enabled() && method_exists($record, 'isFillable') && $record->isFillable('team_id') && $assignedUser->team_id) {
            // Legacy behavior when feature is disabled
            $updateData['team_id'] = $assignedUser->team_id;
        }

        $record->update($updateData);

        Log::info('AssignmentService: Assignment applied to record', [
            'record_type' => get_class($record),
            'record_id' => $record->id ?? null,
            'assigned_user_id' => $assignedUserId,
            'assigned_team_id' => $assignedUser->team_id,
            'tenant_id' => $record->tenant_id ?? null,
            'team_access_enabled' => $teamAccessService->enabled()
        ]);
    }

    /**
     * Prepare record data for rule evaluation.
     */
    private function prepareRecordData($record, string $recordType, array $context): array
    {
        $data = [
            'record_type' => $recordType,
            'tenant_id' => $record->tenant_id ?? $context['tenant_id'] ?? null,
        ];

        // Add record-specific fields
        if ($recordType === 'contact') {
            $data = array_merge($data, [
                'first_name' => $record->first_name ?? null,
                'last_name' => $record->last_name ?? null,
                'email' => $record->email ?? null,
                'phone' => $record->phone ?? null,
                'lifecycle_stage' => $record->lifecycle_stage ?? null,
                'source' => $record->source ?? null,
                'tags' => $record->tags ?? [],
                'lead_score' => $record->lead_score ?? null,
                'company_id' => $record->company_id ?? null,
            ]);

            // Add company data if available
            if ($record->company_id && $record->company) {
                $data = array_merge($data, [
                    'company_name' => $record->company->name ?? null,
                    'company_industry' => $record->company->industry ?? null,
                    'company_size' => $record->company->size ?? null,
                    'company_country' => $record->company->address['country'] ?? null,
                ]);
            }
        } elseif ($recordType === 'deal') {
            $data = array_merge($data, [
                'title' => $record->title ?? null,
                'value' => $record->value ?? null,
                'currency' => $record->currency ?? null,
                'status' => $record->status ?? null,
                'probability' => $record->probability ?? null,
                'pipeline_id' => $record->pipeline_id ?? null,
                'stage_id' => $record->stage_id ?? null,
                'contact_id' => $record->contact_id ?? null,
                'company_id' => $record->company_id ?? null,
                'tags' => $record->tags ?? [],
            ]);

            // Add contact data if available
            if ($record->contact_id && $record->contact) {
                $data = array_merge($data, [
                    'contact_lifecycle_stage' => $record->contact->lifecycle_stage ?? null,
                    'contact_source' => $record->contact->source ?? null,
                    'contact_email' => $record->contact->email ?? null,
                ]);
            }

            // Add company data if available
            if ($record->company_id && $record->company) {
                $data = array_merge($data, [
                    'company_name' => $record->company->name ?? null,
                    'company_industry' => $record->company->industry ?? null,
                    'company_size' => $record->company->size ?? null,
                    'company_country' => $record->company->address['country'] ?? null,
                ]);
            }
        }

        // Add context data
        $data = array_merge($data, $context);

        return $data;
    }

    /**
     * Evaluate rules for the given record data.
     */
    private function evaluateRules(int $tenantId, string $recordType, array $recordData): ?int
    {
        // Get active rules for this tenant, ordered by priority
        $rules = $this->getCachedRules($tenantId, $recordType);

        foreach ($rules as $rule) {
            if ($rule->matches($recordData)) {
                Log::info('AssignmentService: Rule matched', [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'tenant_id' => $tenantId,
                    'record_type' => $recordType,
                    'record_data' => $recordData
                ]);

                return $this->executeRuleAction($rule, $tenantId, $recordData);
            }
        }

        return null;
    }

    /**
     * Get cached rules for a tenant and record type.
     */
    private function getCachedRules(int $tenantId, string $recordType): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "assignment_rules_{$tenantId}_{$recordType}";
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId, $recordType) {
            // Get all active rules for the tenant, then filter by record type in memory
            // This is because some rules might not have record_type in their criteria
            $allRules = AssignmentRule::forTenant($tenantId)
                ->active()
                ->byPriority()
                ->get();
            
            // Filter rules that either:
            // 1. Have record_type in criteria and it matches
            // 2. Don't have record_type in criteria (legacy rules)
            return $allRules->filter(function ($rule) use ($recordType) {
                $criteria = $rule->criteria;
                
                // If rule has record_type in criteria, check if it matches
                if (isset($criteria['all'])) {
                    foreach ($criteria['all'] as $condition) {
                        if (($condition['field'] ?? '') === 'record_type') {
                            return ($condition['value'] ?? '') === $recordType;
                        }
                    }
                }
                
                // If no record_type condition found, include the rule (legacy behavior)
                return true;
            });
        });
    }

    /**
     * Execute the action defined in a rule.
     */
    private function executeRuleAction(AssignmentRule $rule, int $tenantId, array $recordData): ?int
    {
        $action = $rule->action;

        switch ($action['type'] ?? null) {
            case 'assign_user':
                return $action['user_id'] ?? null;

            case 'assign_team_round_robin':
                return $this->assignTeamRoundRobin($tenantId, $action['team_id'] ?? null, $recordData);

            case 'assign_default':
                $defaults = AssignmentDefault::getForTenant($tenantId);
                return $this->getDefaultAssignment($tenantId, $defaults, $recordData);

            default:
                Log::warning('AssignmentService: Unknown rule action type', [
                    'rule_id' => $rule->id,
                    'action' => $action
                ]);
                return null;
        }
    }

    /**
     * Assign using round-robin within a team.
     */
    private function assignTeamRoundRobin(int $tenantId, ?int $teamId, array $recordData): ?int
    {
        if (!$teamId) {
            return null;
        }

        // For now, we'll implement a simple round-robin among all users in the tenant
        // In a real implementation, you might have a teams table with team members
        $teamUserIds = $this->getTeamUserIds($tenantId, $teamId);

        if (empty($teamUserIds)) {
            Log::warning('AssignmentService: No users found for team round-robin', [
                'tenant_id' => $tenantId,
                'team_id' => $teamId
            ]);
            return null;
        }

        return AssignmentCounter::getNextUserForTeam($tenantId, $teamId, $teamUserIds);
    }

    /**
     * Get user IDs for a team (placeholder implementation).
     */
    private function getTeamUserIds(int $tenantId, int $teamId): array
    {
        // For now, return all users in the tenant
        // In a real implementation, you would query a teams table
        return User::where('tenant_id', $tenantId)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get default assignment for a tenant.
     */
    private function getDefaultAssignment(int $tenantId, AssignmentDefault $defaults, array $recordData): ?int
    {
        // If round-robin is enabled, use it
        if ($defaults->round_robin_enabled) {
            $userIds = User::where('tenant_id', $tenantId)->pluck('id')->toArray();
            if (!empty($userIds)) {
                return AssignmentCounter::getNextUserForTeam($tenantId, null, $userIds);
            }
        }

        // Otherwise, use the default user
        return $defaults->default_user_id ?? null;
    }

    /**
     * Log an assignment for audit purposes.
     */
    private function logAssignment(int $tenantId, string $recordType, ?int $recordId, int $assignedUserId, array $context): void
    {
        try {
            AssignmentAudit::createAudit(
                $tenantId,
                $recordType,
                $recordId ?? 0,
                $assignedUserId,
                $context['rule_id'] ?? null,
                $context['assignment_type'] ?? 'default',
                $context
            );

            Log::info('AssignmentService: Assignment logged', [
                'tenant_id' => $tenantId,
                'record_type' => $recordType,
                'record_id' => $recordId,
                'assigned_user_id' => $assignedUserId,
                'assignment_type' => $context['assignment_type'] ?? 'default'
            ]);
        } catch (\Exception $e) {
            Log::error('AssignmentService: Failed to log assignment', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'record_type' => $recordType,
                'record_id' => $recordId,
                'assigned_user_id' => $assignedUserId
            ]);
        }
    }

    /**
     * Clear cached rules for a tenant.
     */
    public function clearRulesCache(int $tenantId): void
    {
        Cache::forget("assignment_rules_{$tenantId}_contact");
        Cache::forget("assignment_rules_{$tenantId}_deal");
    }

    /**
     * Reset round-robin counters for a tenant.
     */
    public function resetRoundRobinCounters(int $tenantId, ?int $teamId = null): void
    {
        $query = AssignmentCounter::forTenant($tenantId);
        
        if ($teamId) {
            $query->forTeam($teamId);
        }

        $query->get()->each(function ($counter) {
            $counter->reset();
        });

        Log::info('AssignmentService: Round-robin counters reset', [
            'tenant_id' => $tenantId,
            'team_id' => $teamId
        ]);
    }

    /**
     * Get assignment statistics for a tenant.
     */
    public function getAssignmentStats(int $tenantId, array $filters = []): array
    {
        $query = AssignmentAudit::forTenant($tenantId);

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['record_type'])) {
            $query->where('record_type', $filters['record_type']);
        }

        $audits = $query->get();

        return [
            'total_assignments' => $audits->count(),
            'by_type' => $audits->groupBy('assignment_type')->map->count(),
            'by_record_type' => $audits->groupBy('record_type')->map->count(),
            'by_user' => $audits->groupBy('assigned_to')->map->count(),
            'by_rule' => $audits->whereNotNull('rule_id')->groupBy('rule_id')->map->count(),
        ];
    }
}
