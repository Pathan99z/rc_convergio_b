<?php

namespace App\Services;

use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyExecution;
use App\Models\Contact;
use App\Models\Task;
use App\Models\Deal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class JourneyEngine
{
    /**
     * Start a journey execution for a contact.
     */
    public function startJourney(Journey $journey, Contact $contact): JourneyExecution
    {
        try {
            DB::beginTransaction();

            // Check if journey can be executed
            if (!$journey->canBeExecuted()) {
                throw new \Exception('Journey cannot be executed');
            }

            // Check if contact already has an active execution for this journey
            $existingExecution = JourneyExecution::where('journey_id', $journey->id)
                ->where('contact_id', $contact->id)
                ->whereIn('status', ['running', 'paused'])
                ->first();

            if ($existingExecution) {
                throw new \Exception('Contact already has an active execution for this journey');
            }

            // Get the first step
            $firstStep = $journey->getFirstStep();
            if (!$firstStep) {
                throw new \Exception('Journey has no active steps');
            }

            // Create execution
            $execution = JourneyExecution::create([
                'journey_id' => $journey->id,
                'contact_id' => $contact->id,
                'current_step_id' => $firstStep->id,
                'status' => 'running',
                'started_at' => now(),
                'next_step_at' => $this->calculateNextStepTime($firstStep),
                'tenant_id' => $contact->tenant_id,
            ]);

            // Execute the first step
            $this->executeStep($execution, $firstStep);

            DB::commit();

            Log::info('Journey execution started', [
                'journey_id' => $journey->id,
                'contact_id' => $contact->id,
                'execution_id' => $execution->id,
                'first_step_id' => $firstStep->id
            ]);

            return $execution;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to start journey execution', [
                'journey_id' => $journey->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process all ready executions.
     */
    public function processReadyExecutions(): void
    {
        $readyExecutions = JourneyExecution::readyForNextStep()
            ->with(['journey', 'contact', 'currentStep'])
            ->get();

        foreach ($readyExecutions as $execution) {
            try {
                $this->processExecution($execution);
            } catch (\Exception $e) {
                Log::error('Failed to process journey execution', [
                    'execution_id' => $execution->id,
                    'error' => $e->getMessage()
                ]);
                $execution->markAsFailed($e->getMessage());
            }
        }
    }

    /**
     * Process a single execution.
     */
    public function processExecution(JourneyExecution $execution): void
    {
        if (!$execution->isReadyForNextStep()) {
            return;
        }

        $currentStep = $execution->currentStep;
        if (!$currentStep) {
            $execution->markAsFailed('Current step not found');
            return;
        }

        // Check step conditions
        if (!$currentStep->conditionsMet($this->getExecutionContext($execution))) {
            // Skip this step and move to next
            $this->moveToNextStep($execution);
            return;
        }

        // Execute the current step
        $this->executeStep($execution, $currentStep);

        // Move to next step or complete
        $this->moveToNextStep($execution);
    }

    /**
     * Execute a specific step.
     */
    private function executeStep(JourneyExecution $execution, JourneyStep $step): void
    {
        try {
            $context = $this->getExecutionContext($execution);
            
            switch ($step->step_type) {
                case 'send_email':
                    $this->executeSendEmail($execution, $step, $context);
                    break;
                case 'wait':
                    // Wait step is handled by next_step_at calculation
                    break;
                case 'create_task':
                    $this->executeCreateTask($execution, $step, $context);
                    break;
                case 'update_contact':
                    $this->executeUpdateContact($execution, $step, $context);
                    break;
                case 'create_deal':
                    $this->executeCreateDeal($execution, $step, $context);
                    break;
                case 'add_tag':
                    $this->executeAddTag($execution, $step, $context);
                    break;
                case 'remove_tag':
                    $this->executeRemoveTag($execution, $step, $context);
                    break;
                case 'update_lead_score':
                    $this->executeUpdateLeadScore($execution, $step, $context);
                    break;
                case 'send_sms':
                    $this->executeSendSms($execution, $step, $context);
                    break;
                case 'webhook':
                    $this->executeWebhook($execution, $step, $context);
                    break;
                case 'condition':
                    $this->executeCondition($execution, $step, $context);
                    break;
                case 'end':
                    $this->executeEnd($execution, $step, $context);
                    break;
                default:
                    Log::warning('Unknown step type', [
                        'step_type' => $step->step_type,
                        'step_id' => $step->id
                    ]);
            }

            Log::info('Step executed successfully', [
                'execution_id' => $execution->id,
                'step_id' => $step->id,
                'step_type' => $step->step_type
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to execute step', [
                'execution_id' => $execution->id,
                'step_id' => $step->id,
                'step_type' => $step->step_type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Move to the next step or complete the journey.
     */
    private function moveToNextStep(JourneyExecution $execution): void
    {
        $currentStep = $execution->currentStep;
        if (!$currentStep) {
            $execution->complete();
            return;
        }

        $nextStep = $execution->journey->getNextStep($currentStep);
        
        if ($nextStep) {
            $execution->moveToNextStep($nextStep);
        } else {
            $execution->complete();
        }
    }

    /**
     * Get execution context for step evaluation.
     */
    private function getExecutionContext(JourneyExecution $execution): array
    {
        $contact = $execution->contact;
        $executionData = $execution->execution_data ?? [];

        return array_merge([
            'contact_id' => $contact->id,
            'contact_name' => $contact->first_name . ' ' . $contact->last_name,
            'contact_email' => $contact->email,
            'contact_phone' => $contact->phone,
            'contact_company' => $contact->company?->name,
            'contact_lead_score' => $contact->lead_score,
            'contact_tags' => $contact->tags ?? [],
            'journey_id' => $execution->journey_id,
            'execution_id' => $execution->id,
        ], $executionData);
    }

    /**
     * Calculate when the next step should execute.
     */
    private function calculateNextStepTime(JourneyStep $step): \DateTime
    {
        if ($step->step_type === 'wait') {
            $config = $step->config;
            $days = $config['days'] ?? 0;
            $hours = $config['hours'] ?? 0;
            $minutes = $config['minutes'] ?? 0;
            
            return now()->addDays($days)->addHours($hours)->addMinutes($minutes);
        }

        // For immediate steps, execute now
        return now();
    }

    /**
     * Execute send email step.
     */
    private function executeSendEmail(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $contact = $execution->contact;

        // In a real implementation, you would:
        // 1. Load email template
        // 2. Replace placeholders with contact data
        // 3. Send email via your email service
        
        Log::info('Email step executed', [
            'execution_id' => $execution->id,
            'contact_email' => $contact->email,
            'template_id' => $config['template_id'] ?? null
        ]);

        // Update execution data
        $executionData = $execution->execution_data ?? [];
        $executionData['emails_sent'][] = [
            'step_id' => $step->id,
            'sent_at' => now()->toISOString(),
            'template_id' => $config['template_id'] ?? null
        ];
        $execution->update(['execution_data' => $executionData]);
    }

    /**
     * Execute create task step.
     */
    private function executeCreateTask(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $contact = $execution->contact;

        $task = Task::create([
            'title' => $config['title'] ?? 'Journey Task',
            'description' => $config['description'] ?? 'Task created by journey workflow',
            'related_type' => 'App\Models\Contact',
            'related_id' => $contact->id,
            'assigned_to' => $config['assigned_to'] ?? $contact->owner_id,
            'due_date' => isset($config['due_date']) ? $config['due_date'] : now()->addDays(7),
            'priority' => $config['priority'] ?? 'medium',
            'status' => 'pending',
            'tenant_id' => $contact->tenant_id,
        ]);

        Log::info('Task created by journey', [
            'execution_id' => $execution->id,
            'task_id' => $task->id,
            'contact_id' => $contact->id
        ]);

        // Update execution data
        $executionData = $execution->execution_data ?? [];
        $executionData['tasks_created'][] = [
            'step_id' => $step->id,
            'task_id' => $task->id,
            'created_at' => now()->toISOString()
        ];
        $execution->update(['execution_data' => $executionData]);
    }

    /**
     * Execute update contact step.
     */
    private function executeUpdateContact(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $contact = $execution->contact;

        $updateData = [];
        foreach ($config['fields'] ?? [] as $field) {
            $updateData[$field['field']] = $field['value'];
        }

        if (!empty($updateData)) {
            $contact->update($updateData);
            
            Log::info('Contact updated by journey', [
                'execution_id' => $execution->id,
                'contact_id' => $contact->id,
                'updated_fields' => array_keys($updateData)
            ]);
        }
    }

    /**
     * Execute create deal step.
     */
    private function executeCreateDeal(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $contact = $execution->contact;

        $deal = Deal::create([
            'name' => $config['name'] ?? 'Journey Deal',
            'value' => $config['value'] ?? 0,
            'stage' => $config['stage'] ?? 'prospecting',
            'close_date' => $config['close_date'] ?? now()->addDays(30),
            'contact_id' => $contact->id,
            'assigned_to' => $config['assigned_to'] ?? $contact->owner_id,
            'tenant_id' => $contact->tenant_id,
        ]);

        Log::info('Deal created by journey', [
            'execution_id' => $execution->id,
            'deal_id' => $deal->id,
            'contact_id' => $contact->id
        ]);

        // Update execution data
        $executionData = $execution->execution_data ?? [];
        $executionData['deals_created'][] = [
            'step_id' => $step->id,
            'deal_id' => $deal->id,
            'created_at' => now()->toISOString()
        ];
        $execution->update(['execution_data' => $executionData]);
    }

    /**
     * Execute add tag step.
     */
    private function executeAddTag(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $contact = $execution->contact;

        $currentTags = $contact->tags ?? [];
        $newTags = array_unique(array_merge($currentTags, $config['tags'] ?? []));
        
        $contact->update(['tags' => $newTags]);

        Log::info('Tags added by journey', [
            'execution_id' => $execution->id,
            'contact_id' => $contact->id,
            'tags_added' => $config['tags'] ?? []
        ]);
    }

    /**
     * Execute remove tag step.
     */
    private function executeRemoveTag(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $contact = $execution->contact;

        $currentTags = $contact->tags ?? [];
        $tagsToRemove = $config['tags'] ?? [];
        $newTags = array_diff($currentTags, $tagsToRemove);
        
        $contact->update(['tags' => array_values($newTags)]);

        Log::info('Tags removed by journey', [
            'execution_id' => $execution->id,
            'contact_id' => $contact->id,
            'tags_removed' => $tagsToRemove
        ]);
    }

    /**
     * Execute update lead score step.
     */
    private function executeUpdateLeadScore(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $contact = $execution->contact;

        $action = $config['action'] ?? 'add';
        $points = $config['points'] ?? 0;

        switch ($action) {
            case 'add':
                $newScore = $contact->lead_score + $points;
                break;
            case 'subtract':
                $newScore = max(0, $contact->lead_score - $points);
                break;
            case 'set':
                $newScore = $points;
                break;
            default:
                $newScore = $contact->lead_score;
        }

        $contact->update([
            'lead_score' => $newScore,
            'lead_score_updated_at' => now()
        ]);

        Log::info('Lead score updated by journey', [
            'execution_id' => $execution->id,
            'contact_id' => $contact->id,
            'action' => $action,
            'points' => $points,
            'new_score' => $newScore
        ]);
    }

    /**
     * Execute send SMS step.
     */
    private function executeSendSms(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $contact = $execution->contact;

        // In a real implementation, you would send SMS via your SMS service
        Log::info('SMS step executed', [
            'execution_id' => $execution->id,
            'contact_phone' => $contact->phone,
            'message' => $config['message'] ?? ''
        ]);
    }

    /**
     * Execute webhook step.
     */
    private function executeWebhook(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;

        // In a real implementation, you would make HTTP request to webhook URL
        Log::info('Webhook step executed', [
            'execution_id' => $execution->id,
            'url' => $config['url'] ?? '',
            'method' => $config['method'] ?? 'POST'
        ]);
    }

    /**
     * Execute condition step.
     */
    private function executeCondition(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $field = $config['field'] ?? '';
        $operator = $config['operator'] ?? 'equals';
        $expectedValue = $config['value'] ?? null;
        $actualValue = $context[$field] ?? null;

        $conditionMet = $this->evaluateCondition($actualValue, $operator, $expectedValue);

        // Update execution data with condition result
        $executionData = $execution->execution_data ?? [];
        $executionData['condition_results'][] = [
            'step_id' => $step->id,
            'field' => $field,
            'operator' => $operator,
            'expected_value' => $expectedValue,
            'actual_value' => $actualValue,
            'result' => $conditionMet,
            'evaluated_at' => now()->toISOString()
        ];
        $execution->update(['execution_data' => $executionData]);

        Log::info('Condition step executed', [
            'execution_id' => $execution->id,
            'condition_met' => $conditionMet,
            'field' => $field,
            'operator' => $operator
        ]);
    }

    /**
     * Execute end step.
     */
    private function executeEnd(JourneyExecution $execution, JourneyStep $step, array $context): void
    {
        $config = $step->config;
        $reason = $config['reason'] ?? 'Journey completed';

        $execution->complete();

        Log::info('Journey ended', [
            'execution_id' => $execution->id,
            'reason' => $reason
        ]);
    }

    /**
     * Evaluate a condition.
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
