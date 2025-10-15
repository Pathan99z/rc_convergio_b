<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyExecution;
use App\Models\ContactInteraction;

class SetupJourneyExecution extends Command
{
    protected $signature = 'journey:setup-execution {email=ashok@reliance.in}';
    protected $description = 'Set up journey execution for a contact';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Setting up Journey Executions for $email");
        $this->info("===================================================");

        try {
            // Find the contact
            $contact = Contact::where('email', $email)->first();
            
            if (!$contact) {
                $this->error("Contact $email not found");
                return 1;
            }
            
            $this->info("✅ Found contact: {$contact->email} (ID: {$contact->id})");
            $this->info("Contact name: " . ($contact->full_name ?: 'N/A'));
            $this->info("Tenant ID: {$contact->tenant_id}");
            
            // Check if journey executions already exist
            $existingExecutions = JourneyExecution::where('contact_id', $contact->id)->count();
            $this->info("Existing journey executions: $existingExecutions");
            
            // Find or create a journey
            $journey = Journey::where('tenant_id', $contact->tenant_id)->first();
            
            if (!$journey) {
                $this->info("Creating a new journey...");
                
                $journey = Journey::create([
                    'name' => 'Enterprise Customer Journey',
                    'description' => 'Complete journey for enterprise customers from lead to customer',
                    'status' => 'active',
                    'is_active' => true,
                    'tenant_id' => $contact->tenant_id,
                    'created_by' => $contact->tenant_id,
                    'settings' => [
                        'auto_advance' => true,
                        'send_notifications' => true,
                    ]
                ]);
                
                $this->info("✅ Created journey: {$journey->name} (ID: {$journey->id})");
                
                // Create journey steps
                $steps = [
                    [
                        'step_type' => 'send_email',
                        'order_no' => 1,
                        'config' => [
                            'template' => 'welcome_email',
                            'subject' => 'Welcome to our platform',
                            'delay_minutes' => 0
                        ]
                    ],
                    [
                        'step_type' => 'wait',
                        'order_no' => 2,
                        'config' => [
                            'wait_type' => 'time',
                            'wait_value' => 24,
                            'wait_unit' => 'hours'
                        ]
                    ],
                    [
                        'step_type' => 'send_email',
                        'order_no' => 3,
                        'config' => [
                            'template' => 'follow_up_email',
                            'subject' => 'How are you finding our platform?',
                            'delay_minutes' => 0
                        ]
                    ],
                    [
                        'step_type' => 'create_task',
                        'order_no' => 4,
                        'config' => [
                            'task_title' => 'Schedule discovery call',
                            'task_description' => 'Contact the lead to schedule a discovery call',
                            'assign_to_owner' => true
                        ]
                    ],
                    [
                        'step_type' => 'wait',
                        'order_no' => 5,
                        'config' => [
                            'wait_type' => 'time',
                            'wait_value' => 7,
                            'wait_unit' => 'days'
                        ]
                    ]
                ];
                
                foreach ($steps as $stepData) {
                    JourneyStep::create([
                        'journey_id' => $journey->id,
                        'step_type' => $stepData['step_type'],
                        'order_no' => $stepData['order_no'],
                        'config' => $stepData['config'],
                        'conditions' => null,
                        'is_active' => true,
                    ]);
                }
                
                $this->info("✅ Created " . count($steps) . " journey steps");
            } else {
                $this->info("✅ Found existing journey: {$journey->name} (ID: {$journey->id})");
            }
            
            // Create or update journey execution
            $execution = JourneyExecution::where('contact_id', $contact->id)
                ->where('journey_id', $journey->id)
                ->first();
            
            if (!$execution) {
                $this->info("Creating journey execution...");
                
                $firstStep = $journey->steps()->orderBy('order_no')->first();
                
                $execution = JourneyExecution::create([
                    'journey_id' => $journey->id,
                    'contact_id' => $contact->id,
                    'current_step_id' => $firstStep ? $firstStep->id : null,
                    'status' => 'running',
                    'started_at' => now()->subDays(7),
                    'next_step_at' => now()->addHours(1),
                    'tenant_id' => $contact->tenant_id,
                    'execution_data' => [
                        'completed_steps' => [],
                        'step_history' => [],
                    ]
                ]);
                
                $this->info("✅ Created journey execution (ID: {$execution->id})");
            } else {
                $this->info("✅ Found existing journey execution (ID: {$execution->id})");
            }
            
            // Simulate step progression
            $steps = $journey->steps()->orderBy('order_no')->get();
            $executionData = $execution->execution_data ?: [];
            $completedSteps = $executionData['completed_steps'] ?: [];
            
            $this->info("Simulating journey progression...");
            
            // Mark first 3 steps as completed
            $stepsToComplete = min(3, $steps->count());
            
            for ($i = 0; $i < $stepsToComplete; $i++) {
                $step = $steps[$i];
                
                if (!in_array($step->id, $completedSteps)) {
                    $completedSteps[] = $step->id;
                    $executionData['step_completed_' . $step->id] = now()->subDays(6 - $i)->toIso8601String();
                    
                    $this->info("  ✅ Completed step {$step->order_no}: {$step->step_type}");
                    
                    // Create interaction for this step
                    ContactInteraction::create([
                        'contact_id' => $contact->id,
                        'type' => $step->step_type === 'send_email' ? 'email' : 'task',
                        'message' => "Journey step completed: " . ucfirst(str_replace('_', ' ', $step->step_type)),
                        'details' => "Step {$step->order_no} of journey '{$journey->name}' was completed",
                        'metadata' => [
                            'journey_id' => $journey->id,
                            'step_id' => $step->id,
                            'step_type' => $step->step_type,
                            'step_order' => $step->order_no,
                            'completed_at' => now()->subDays(6 - $i)->toIso8601String()
                        ]
                    ]);
                }
            }
            
            // Update execution data
            $executionData['completed_steps'] = $completedSteps;
            
            // Set current step to the next uncompleted step
            $nextStep = null;
            foreach ($steps as $step) {
                if (!in_array($step->id, $completedSteps)) {
                    $nextStep = $step;
                    break;
                }
            }
            
            $execution->update([
                'current_step_id' => $nextStep ? $nextStep->id : null,
                'status' => $nextStep ? 'running' : 'completed',
                'execution_data' => $executionData,
                'completed_at' => $nextStep ? null : now(),
            ]);
            
            $this->info("✅ Journey execution updated");
            $this->info("Completed steps: " . count($completedSteps) . "/" . $steps->count());
            $this->info("Current step: " . ($nextStep ? "Step {$nextStep->order_no} ({$nextStep->step_type})" : "Journey completed"));
            $this->info("Status: {$execution->status}");
            
            // Update contact's last activity
            $contact->update(['last_activity' => now()]);
            
            $this->info("🎉 SUCCESS: Journey execution set up for $email");
            $this->info("The frontend should now show journey data for this contact.");
            
            $this->info("📊 Summary:");
            $this->info("- Contact ID: {$contact->id}");
            $this->info("- Journey ID: {$journey->id}");
            $this->info("- Execution ID: {$execution->id}");
            $progressPercentage = $steps->count() > 0 ? round((count($completedSteps) / $steps->count()) * 100, 1) : 0;
            $this->info("- Progress: {$progressPercentage}%");
            $this->info("- Status: {$execution->status}");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}
