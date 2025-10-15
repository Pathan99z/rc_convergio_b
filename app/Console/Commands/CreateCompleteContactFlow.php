<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\Company;
use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyExecution;
use App\Models\ContactInteraction;
use App\Models\Deal;
use App\Models\Task;
use App\Models\Meeting;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;

class CreateCompleteContactFlow extends Command
{
    protected $signature = 'contact:create-complete-flow {email=zerodha@trade.in}';
    protected $description = 'Create a complete contact with full journey automation flow';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🚀 Creating Complete Contact Flow for: $email");
        $this->info("=====================================================");

        try {
            DB::beginTransaction();

            // Step 1: Create or find company
            $this->info("1. Creating company...");
            $company = Company::updateOrCreate(
                ['name' => 'Zerodha Trading Ltd'],
                [
                    'industry' => 'Financial Services',
                    'size' => 1000, // Number of employees
                    'website' => 'https://zerodha.com',
                    'phone' => '+91-80-4040-2020',
                    'address' => ['city' => 'Bangalore', 'state' => 'Karnataka', 'country' => 'India'],
                    'description' => 'Leading discount brokerage firm in India',
                    'tenant_id' => 41, // Using existing tenant
                    'owner_id' => 41,
                ]
            );
            $this->info("✅ Company created: {$company->name} (ID: {$company->id})");

            // Step 2: Create contact
            $this->info("2. Creating contact...");
            $contact = Contact::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => 'Nithin',
                    'last_name' => 'Kamath',
                    'phone' => '+91-9876543210',
                    'company_id' => $company->id,
                    'lifecycle_stage' => 'lead',
                    'lead_score' => 85,
                    'source' => 'website',
                    'tenant_id' => 41,
                    'owner_id' => 41,
                    'last_activity' => now(),
                ]
            );
            $this->info("✅ Contact created: {$contact->first_name} {$contact->last_name} (ID: {$contact->id})");

            // Step 3: Create comprehensive journey
            $this->info("3. Creating comprehensive journey...");
            $journey = Journey::updateOrCreate(
                ['name' => 'Enterprise Financial Services Journey'],
                [
                    'description' => 'Complete automation journey for financial services leads from initial contact to customer conversion',
                    'status' => 'active',
                    'is_active' => true,
                    'tenant_id' => 41,
                    'created_by' => 41,
                    'settings' => [
                        'auto_advance' => true,
                        'send_notifications' => true,
                        'track_engagement' => true,
                        'lead_scoring' => true,
                    ]
                ]
            );
            $this->info("✅ Journey created: {$journey->name} (ID: {$journey->id})");

            // Step 4: Create journey steps
            $this->info("4. Creating journey steps...");
            $steps = [
                [
                    'step_type' => 'send_email',
                    'order_no' => 1,
                    'config' => [
                        'template' => 'financial_welcome',
                        'subject' => 'Welcome to our Financial Services Platform',
                        'delay_minutes' => 0,
                        'personalized' => true
                    ]
                ],
                [
                    'step_type' => 'wait',
                    'order_no' => 2,
                    'config' => [
                        'wait_type' => 'time',
                        'wait_value' => 2,
                        'wait_unit' => 'hours'
                    ]
                ],
                [
                    'step_type' => 'send_email',
                    'order_no' => 3,
                    'config' => [
                        'template' => 'financial_education',
                        'subject' => 'Understanding Our Trading Platform',
                        'delay_minutes' => 0
                    ]
                ],
                [
                    'step_type' => 'create_task',
                    'order_no' => 4,
                    'config' => [
                        'task_title' => 'Schedule Financial Consultation',
                        'task_description' => 'Contact lead to schedule financial consultation call',
                        'assign_to_owner' => true,
                        'priority' => 'high'
                    ]
                ],
                [
                    'step_type' => 'wait',
                    'order_no' => 5,
                    'config' => [
                        'wait_type' => 'time',
                        'wait_value' => 1,
                        'wait_unit' => 'days'
                    ]
                ],
                [
                    'step_type' => 'send_email',
                    'order_no' => 6,
                    'config' => [
                        'template' => 'follow_up_consultation',
                        'subject' => 'Ready to discuss your trading needs?',
                        'delay_minutes' => 0
                    ]
                ],
                [
                    'step_type' => 'create_deal',
                    'order_no' => 7,
                    'config' => [
                        'deal_title' => 'Financial Services Opportunity',
                        'deal_value' => 50000,
                        'pipeline_stage' => 'qualification'
                    ]
                ],
                [
                    'step_type' => 'schedule_meeting',
                    'order_no' => 8,
                    'config' => [
                        'meeting_title' => 'Financial Services Demo',
                        'meeting_type' => 'demo',
                        'duration_minutes' => 60
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

            // Step 5: Create journey execution
            $this->info("5. Creating journey execution...");
            $firstStep = $journey->steps()->orderBy('order_no')->first();
            
            $execution = JourneyExecution::create([
                'journey_id' => $journey->id,
                'contact_id' => $contact->id,
                'current_step_id' => $firstStep->id,
                'status' => 'running',
                'started_at' => now()->subDays(3),
                'next_step_at' => now()->addMinutes(30),
                'tenant_id' => 41,
                'execution_data' => [
                    'completed_steps' => [],
                    'step_history' => [],
                    'engagement_score' => 0,
                ]
            ]);
            $this->info("✅ Journey execution created (ID: {$execution->id})");

            // Step 6: Simulate step progression with realistic timeline
            $this->info("6. Simulating journey progression...");
            $allSteps = $journey->steps()->orderBy('order_no')->get();
            $executionData = $execution->execution_data;
            $completedSteps = [];
            
            // Complete first 5 steps (about 60% progress)
            $stepsToComplete = 5;
            
            for ($i = 0; $i < $stepsToComplete && $i < $allSteps->count(); $i++) {
                $step = $allSteps[$i];
                $completedSteps[] = $step->id;
                $executionData['step_completed_' . $step->id] = now()->subDays(3 - $i)->toIso8601String();
                
                $this->info("  ✅ Completed step {$step->order_no}: {$step->step_type}");
                
                // Create realistic interactions for each step
                $this->createStepInteraction($contact, $step, $journey, $i);
            }

            // Update execution with progress
            $nextStep = $allSteps->get($stepsToComplete);
            $execution->update([
                'current_step_id' => $nextStep ? $nextStep->id : null,
                'status' => $nextStep ? 'running' : 'completed',
                'execution_data' => array_merge($executionData, [
                    'completed_steps' => $completedSteps,
                    'engagement_score' => 75,
                    'last_activity' => now()->toIso8601String()
                ]),
                'completed_at' => $nextStep ? null : now(),
            ]);

            // Step 7: Create additional business entities
            $this->info("7. Creating additional business entities...");
            
            // Get existing pipeline and stage
            $pipeline = \App\Models\Pipeline::first();
            $stage = \App\Models\Stage::first();
            
            // Create a deal
            $deal = Deal::create([
                'title' => 'Zerodha Enterprise Trading Platform',
                'description' => 'Enterprise trading platform setup for institutional clients',
                'value' => 250000,
                'contact_id' => $contact->id,
                'company_id' => $company->id,
                'stage_id' => $stage->id,
                'pipeline_id' => $pipeline->id,
                'probability' => 75,
                'expected_close_date' => now()->addDays(30),
                'tenant_id' => 41,
                'owner_id' => 41,
            ]);
            $this->info("✅ Deal created: {$deal->title} (Value: ₹{$deal->value})");

            // Create tasks
            $task = Task::create([
                'title' => 'Follow up on Zerodha trading platform demo',
                'description' => 'Schedule follow-up call to discuss platform features and pricing',
                'assigned_to' => 41,
                'due_date' => now()->addDays(2),
                'priority' => 'high',
                'status' => 'pending',
                'tenant_id' => 41,
                'owner_id' => 41,
                'related_type' => 'App\\Models\\Contact',
                'related_id' => $contact->id,
            ]);
            $this->info("✅ Task created: {$task->title}");

            // Create meeting (simplified)
            $meeting = Meeting::create([
                'title' => 'Zerodha Platform Demo & Consultation',
                'description' => 'Comprehensive demo of trading platform features and consultation on enterprise needs',
                'contact_id' => $contact->id,
                'user_id' => 41,
                'scheduled_at' => now()->addDays(1)->setTime(14, 0),
                'duration_minutes' => 90,
                'location' => 'Virtual Meeting - Zoom',
                'status' => 'scheduled',
                'tenant_id' => 41,
            ]);
            $this->info("✅ Meeting created: {$meeting->title}");

            // Step 8: Create comprehensive interaction history
            $this->info("8. Creating comprehensive interaction history...");
            $this->createComprehensiveInteractions($contact, $company, $deal, $journey);

            DB::commit();

            $this->info("");
            $this->info("🎉 SUCCESS: Complete contact flow created for $email");
            $this->info("=======================================================");
            $this->info("");
            $this->info("📊 Summary:");
            $this->info("- Contact ID: {$contact->id}");
            $this->info("- Company ID: {$company->id}");
            $this->info("- Journey ID: {$journey->id}");
            $this->info("- Execution ID: {$execution->id}");
            $this->info("- Deal ID: {$deal->id}");
            $this->info("- Journey Progress: " . round((count($completedSteps) / $allSteps->count()) * 100, 1) . "%");
            $this->info("- Total Interactions: " . ContactInteraction::where('contact_id', $contact->id)->count());
            $this->info("");
            $this->info("🌐 Test URLs:");
            $this->info("- Journey Timeline: http://localhost:5173/journey-timeline?contact=$email");
            $this->info("- Journey Progress: http://localhost:5173/journey-progress");
            $this->info("- Journey Flow: http://localhost:5173/journey-flow");
            $this->info("");
            $this->info("📡 API Endpoints to test:");
            $this->info("- GET /api/contacts/journey/$email");
            $this->info("- GET /api/contacts/{$contact->id}/journey-timeline");
            $this->info("- GET /api/contacts/{$contact->id}/journey/events");
            $this->info("- GET /api/contacts/{$contact->id}/journey/stages");

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    private function createStepInteraction($contact, $step, $journey, $dayOffset)
    {
        $interactionTypes = [
            'send_email' => 'email',
            'create_task' => 'task',
            'create_deal' => 'deal',
            'schedule_meeting' => 'meeting',
            'wait' => 'system',
        ];

        $messages = [
            'send_email' => [
                'Financial welcome email sent successfully',
                'Educational content email delivered',
                'Follow-up consultation email sent',
            ],
            'create_task' => 'Task created: Schedule Financial Consultation',
            'create_deal' => 'Deal opportunity created for financial services',
            'schedule_meeting' => 'Meeting scheduled: Financial Services Demo',
            'wait' => 'Automation wait period completed',
        ];

        $type = $interactionTypes[$step->step_type] ?? 'system';
        $message = is_array($messages[$step->step_type]) ? 
                   $messages[$step->step_type][array_rand($messages[$step->step_type])] : 
                   ($messages[$step->step_type] ?? "Journey step completed: {$step->step_type}");

        ContactInteraction::create([
            'contact_id' => $contact->id,
            'type' => $type,
            'message' => $message,
            'details' => "Journey automation step {$step->order_no}: " . ucfirst(str_replace('_', ' ', $step->step_type)),
            'metadata' => [
                'journey_id' => $journey->id,
                'step_id' => $step->id,
                'step_type' => $step->step_type,
                'step_order' => $step->order_no,
                'automation' => true,
                'completed_at' => now()->subDays(3 - $dayOffset)->toIso8601String(),
                'config' => $step->config,
            ],
            'created_at' => now()->subDays(3 - $dayOffset),
            'updated_at' => now()->subDays(3 - $dayOffset),
        ]);
    }

    private function createComprehensiveInteractions($contact, $company, $deal, $journey)
    {
        $interactions = [
            [
                'type' => 'form_submission',
                'message' => 'Enterprise inquiry form submitted',
                'details' => 'Zerodha submitted inquiry for enterprise trading platform solutions',
                'metadata' => [
                    'form_name' => 'Enterprise Trading Platform Inquiry',
                    'company_size' => 'Large',
                    'trading_volume' => 'High',
                    'requirements' => 'API access, bulk trading, institutional features'
                ],
                'days_ago' => 4
            ],
            [
                'type' => 'website_visit',
                'message' => 'Visited enterprise solutions page',
                'details' => 'Browsed enterprise trading platform features and pricing',
                'metadata' => [
                    'pages_visited' => ['enterprise', 'api-docs', 'pricing', 'features'],
                    'session_duration' => '12 minutes',
                    'source' => 'organic_search'
                ],
                'days_ago' => 4
            ],
            [
                'type' => 'email',
                'message' => 'Welcome email opened and clicked',
                'details' => 'Opened welcome email and clicked on platform demo link',
                'metadata' => [
                    'email_template' => 'financial_welcome',
                    'opened' => true,
                    'clicked' => true,
                    'click_url' => '/platform-demo'
                ],
                'days_ago' => 3
            ],
            [
                'type' => 'call',
                'message' => 'Inbound inquiry call received',
                'details' => 'Nithin called to discuss enterprise trading platform requirements',
                'metadata' => [
                    'call_duration' => '15 minutes',
                    'call_type' => 'inbound',
                    'topics_discussed' => ['API integration', 'volume pricing', 'support']
                ],
                'days_ago' => 2
            ],
            [
                'type' => 'email',
                'message' => 'Educational content email engaged',
                'details' => 'Opened platform education email and downloaded API documentation',
                'metadata' => [
                    'email_template' => 'financial_education',
                    'opened' => true,
                    'downloaded' => 'API_Documentation.pdf'
                ],
                'days_ago' => 2
            ],
            [
                'type' => 'website_visit',
                'message' => 'API documentation accessed',
                'details' => 'Spent significant time reviewing API documentation and code examples',
                'metadata' => [
                    'pages_visited' => ['api-docs', 'code-examples', 'authentication'],
                    'session_duration' => '25 minutes'
                ],
                'days_ago' => 1
            ]
        ];

        foreach ($interactions as $interaction) {
            ContactInteraction::create([
                'contact_id' => $contact->id,
                'type' => $interaction['type'],
                'message' => $interaction['message'],
                'details' => $interaction['details'],
                'metadata' => $interaction['metadata'],
                'created_at' => now()->subDays($interaction['days_ago']),
                'updated_at' => now()->subDays($interaction['days_ago']),
            ]);
        }
    }
}
