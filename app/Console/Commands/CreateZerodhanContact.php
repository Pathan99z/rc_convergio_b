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
use App\Models\CampaignRecipient;
use App\Models\Campaign;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateZerodhanContact extends Command
{
    protected $signature = 'contact:create-zerodhan';
    protected $description = 'Create zerodhan@domain.in contact with complete journey flow and welcome email';

    public function handle()
    {
        $email = 'zerodhan@domain.in';
        
        $this->info("🚀 Creating Complete Contact Journey for: $email");
        $this->info("====================================================");

        try {
            DB::beginTransaction();

            // Step 1: Create company
            $this->info("1. Creating company...");
            $company = Company::updateOrCreate(
                ['name' => 'Domain Technologies Pvt Ltd'],
                [
                    'industry' => 'Technology',
                    'size' => 500,
                    'website' => 'https://domain.in',
                    'phone' => '+91-11-4567-8900',
                    'address' => ['city' => 'New Delhi', 'state' => 'Delhi', 'country' => 'India'],
                    'description' => 'Leading technology solutions provider in India',
                    'tenant_id' => 41,
                    'owner_id' => 41,
                ]
            );
            $this->info("✅ Company: {$company->name} (ID: {$company->id})");

            // Step 2: Create contact
            $this->info("2. Creating contact...");
            $contact = Contact::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => 'Zerodhan',
                    'last_name' => 'Kumar',
                    'phone' => '+91-9876543210',
                    'company_id' => $company->id,
                    'lifecycle_stage' => 'lead',
                    'lead_score' => 75,
                    'source' => 'website',
                    'tenant_id' => 41,
                    'owner_id' => 41,
                    'last_activity' => now(),
                ]
            );
            $this->info("✅ Contact: {$contact->full_name} (ID: {$contact->id})");

            // Step 3: Create welcome email journey
            $this->info("3. Creating welcome email journey...");
            $journey = Journey::updateOrCreate(
                ['name' => 'Welcome Email Automation Journey'],
                [
                    'description' => 'Automated welcome email sequence with follow-up actions for new leads',
                    'status' => 'active',
                    'is_active' => true,
                    'tenant_id' => 41,
                    'created_by' => 41,
                    'settings' => [
                        'auto_advance' => true,
                        'send_notifications' => true,
                        'track_engagement' => true,
                        'welcome_email_enabled' => true,
                    ]
                ]
            );
            $this->info("✅ Journey: {$journey->name} (ID: {$journey->id})");

            // Step 4: Create journey steps with welcome email
            $this->info("4. Creating journey steps...");
            
            // Clear existing steps for this journey
            JourneyStep::where('journey_id', $journey->id)->delete();
            
            $steps = [
                [
                    'step_type' => 'send_email',
                    'order_no' => 1,
                    'config' => [
                        'template' => 'welcome_email',
                        'subject' => 'Welcome to our platform, Zerodhan!',
                        'delay_minutes' => 0,
                        'personalized' => true,
                        'track_opens' => true,
                        'track_clicks' => true
                    ]
                ],
                [
                    'step_type' => 'wait',
                    'order_no' => 2,
                    'config' => [
                        'wait_type' => 'time',
                        'wait_value' => 4,
                        'wait_unit' => 'hours'
                    ]
                ],
                [
                    'step_type' => 'send_email',
                    'order_no' => 3,
                    'config' => [
                        'template' => 'getting_started',
                        'subject' => 'Getting started with your account',
                        'delay_minutes' => 0
                    ]
                ],
                [
                    'step_type' => 'create_task',
                    'order_no' => 4,
                    'config' => [
                        'task_title' => 'Follow up with Zerodhan Kumar',
                        'task_description' => 'Personal follow-up call to ensure smooth onboarding',
                        'assign_to_owner' => true,
                        'priority' => 'medium'
                    ]
                ],
                [
                    'step_type' => 'wait',
                    'order_no' => 5,
                    'config' => [
                        'wait_type' => 'time',
                        'wait_value' => 2,
                        'wait_unit' => 'days'
                    ]
                ],
                [
                    'step_type' => 'send_email',
                    'order_no' => 6,
                    'config' => [
                        'template' => 'feature_introduction',
                        'subject' => 'Discover powerful features for your business',
                        'delay_minutes' => 0
                    ]
                ],
                [
                    'step_type' => 'create_deal',
                    'order_no' => 7,
                    'config' => [
                        'deal_title' => 'Technology Solutions Opportunity',
                        'deal_value' => 75000,
                        'pipeline_stage' => 'initial_contact'
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
            $this->info("5. Starting journey execution...");
            $firstStep = $journey->steps()->orderBy('order_no')->first();
            
            // Delete existing execution if any
            JourneyExecution::where('contact_id', $contact->id)
                ->where('journey_id', $journey->id)
                ->delete();
            
            $execution = JourneyExecution::create([
                'journey_id' => $journey->id,
                'contact_id' => $contact->id,
                'current_step_id' => $firstStep->id,
                'status' => 'running',
                'started_at' => now(),
                'next_step_at' => now()->addMinutes(1), // Execute first step immediately
                'tenant_id' => 41,
                'execution_data' => [
                    'completed_steps' => [],
                    'step_history' => [],
                    'welcome_email_sent' => false,
                ]
            ]);
            $this->info("✅ Journey execution started (ID: {$execution->id})");

            // Step 6: Simulate welcome email sending
            $this->info("6. Sending welcome email...");
            
            // Create welcome email interaction
            $welcomeInteraction = ContactInteraction::create([
                'contact_id' => $contact->id,
                'type' => 'email',
                'message' => 'Welcome email sent successfully',
                'details' => "Welcome email sent to {$contact->full_name} at {$email}. Subject: 'Welcome to our platform, Zerodhan!'",
                'metadata' => [
                    'journey_id' => $journey->id,
                    'step_id' => $firstStep->id,
                    'email_template' => 'welcome_email',
                    'subject' => 'Welcome to our platform, Zerodhan!',
                    'sent_at' => now()->toIso8601String(),
                    'status' => 'sent',
                    'tracking_enabled' => true,
                ],
                'created_at' => now(),
            ]);
            
            // Mark first step as completed
            $executionData = $execution->execution_data;
            $executionData['completed_steps'] = [$firstStep->id];
            $executionData['step_completed_' . $firstStep->id] = now()->toIso8601String();
            $executionData['welcome_email_sent'] = true;
            
            // Move to next step
            $nextStep = $journey->steps()->where('order_no', 2)->first();
            $execution->update([
                'current_step_id' => $nextStep->id,
                'execution_data' => $executionData,
                'next_step_at' => now()->addHours(4), // Next step in 4 hours
            ]);
            
            $this->info("✅ Welcome email sent and tracked");

            // Step 7: Simulate email engagement
            $this->info("7. Simulating email engagement...");
            
            // Simulate email opened after 30 minutes
            ContactInteraction::create([
                'contact_id' => $contact->id,
                'type' => 'email',
                'message' => 'Welcome email opened',
                'details' => 'Zerodhan opened the welcome email and spent 2 minutes reading it',
                'metadata' => [
                    'journey_id' => $journey->id,
                    'step_id' => $firstStep->id,
                    'email_template' => 'welcome_email',
                    'action' => 'opened',
                    'opened_at' => now()->addMinutes(30)->toIso8601String(),
                    'read_time_seconds' => 120,
                ],
                'created_at' => now()->addMinutes(30),
            ]);

            // Simulate email clicked after 45 minutes
            ContactInteraction::create([
                'contact_id' => $contact->id,
                'type' => 'email',
                'message' => 'Welcome email link clicked',
                'details' => 'Clicked on "Get Started" button in welcome email',
                'metadata' => [
                    'journey_id' => $journey->id,
                    'step_id' => $firstStep->id,
                    'email_template' => 'welcome_email',
                    'action' => 'clicked',
                    'clicked_at' => now()->addMinutes(45)->toIso8601String(),
                    'click_url' => '/getting-started',
                ],
                'created_at' => now()->addMinutes(45),
            ]);

            $this->info("✅ Email engagement simulated (opened + clicked)");

            // Step 8: Create additional journey interactions
            $this->info("8. Creating comprehensive journey interactions...");
            
            $interactions = [
                [
                    'type' => 'form_submission',
                    'message' => 'Contact inquiry form submitted',
                    'details' => 'Zerodhan submitted a contact inquiry form expressing interest in technology solutions',
                    'metadata' => [
                        'form_name' => 'Technology Solutions Inquiry',
                        'company_size' => '500 employees',
                        'budget_range' => '50k-100k',
                        'timeline' => 'Q1 2025'
                    ],
                    'created_at' => now()->subHours(2)
                ],
                [
                    'type' => 'website_visit',
                    'message' => 'Visited pricing page',
                    'details' => 'Browsed technology solutions pricing and feature comparison',
                    'metadata' => [
                        'pages_visited' => ['pricing', 'features', 'case-studies'],
                        'session_duration' => '8 minutes',
                        'source' => 'email_link'
                    ],
                    'created_at' => now()->subMinutes(30)
                ],
                [
                    'type' => 'call',
                    'message' => 'Inbound inquiry call',
                    'details' => 'Zerodhan called to discuss technology solutions for his company',
                    'metadata' => [
                        'call_duration' => '12 minutes',
                        'call_type' => 'inbound',
                        'topics' => ['pricing', 'implementation', 'support']
                    ],
                    'created_at' => now()->subMinutes(15)
                ]
            ];

            foreach ($interactions as $interactionData) {
                ContactInteraction::create([
                    'contact_id' => $contact->id,
                    'type' => $interactionData['type'],
                    'message' => $interactionData['message'],
                    'details' => $interactionData['details'],
                    'metadata' => $interactionData['metadata'],
                    'created_at' => $interactionData['created_at'],
                    'updated_at' => $interactionData['created_at'],
                ]);
            }

            // Step 9: Create business entities
            $this->info("9. Creating business entities...");
            
            // Get existing pipeline and stage
            $pipeline = \App\Models\Pipeline::first();
            $stage = \App\Models\Stage::first();
            
            // Create deal
            $deal = Deal::create([
                'title' => 'Domain Technologies - Tech Solutions',
                'description' => 'Technology solutions implementation for Domain Technologies',
                'value' => 75000,
                'contact_id' => $contact->id,
                'company_id' => $company->id,
                'stage_id' => $stage->id,
                'pipeline_id' => $pipeline->id,
                'probability' => 60,
                'expected_close_date' => now()->addDays(45),
                'tenant_id' => 41,
                'owner_id' => 41,
            ]);
            $this->info("✅ Deal: {$deal->title} (₹{$deal->value})");

            // Create follow-up task
            $task = Task::create([
                'title' => 'Follow up with Zerodhan Kumar - Welcome Call',
                'description' => 'Schedule welcome call to discuss technology solutions and next steps',
                'assigned_to' => 41,
                'due_date' => now()->addDays(1),
                'priority' => 'high',
                'status' => 'pending',
                'tenant_id' => 41,
                'owner_id' => 41,
                'related_type' => 'App\\Models\\Contact',
                'related_id' => $contact->id,
            ]);
            $this->info("✅ Task: {$task->title}");

            // Step 10: Create campaign for welcome email tracking
            $this->info("10. Creating welcome email campaign...");
            $campaign = Campaign::create([
                'name' => 'Welcome Email - Zerodhan Kumar',
                'description' => 'Personalized welcome email for new lead',
                'subject' => 'Welcome to our platform, Zerodhan!',
                'content' => $this->getWelcomeEmailContent($contact),
                'status' => 'sent',
                'type' => 'email', // Using 'email' instead of 'welcome'
                'sent_at' => now(),
                'total_recipients' => 1,
                'sent_count' => 1,
                'delivered_count' => 1,
                'opened_count' => 1,
                'clicked_count' => 1,
                'tenant_id' => 41,
                'created_by' => 41,
                'settings' => [
                    'track_opens' => true,
                    'track_clicks' => true,
                    'personalized' => true,
                ]
            ]);

            // Create campaign recipient
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'status' => 'sent',
                'sent_at' => now(),
                'delivered_at' => now()->addMinutes(1),
                'opened_at' => now()->addMinutes(30),
                'clicked_at' => now()->addMinutes(45),
                'tenant_id' => 41,
            ]);
            
            $this->info("✅ Welcome email campaign created and tracked");

            // Step 11: Update contact with latest activity
            $contact->update([
                'last_activity' => now(),
                'lead_score' => 85, // Increased due to engagement
            ]);

            DB::commit();

            $this->info("");
            $this->info("🎉 SUCCESS: Complete journey created for $email");
            $this->info("=====================================================");
            $this->info("");
            $this->info("📊 SUMMARY:");
            $this->info("- Contact ID: {$contact->id}");
            $this->info("- Contact: {$contact->full_name} ({$contact->email})");
            $this->info("- Company: {$company->name}");
            $this->info("- Journey ID: {$journey->id}");
            $this->info("- Execution ID: {$execution->id}");
            $this->info("- Deal: ₹{$deal->value} ({$deal->title})");
            $this->info("- Campaign ID: {$campaign->id}");
            $this->info("- Welcome Email: ✅ SENT");
            $this->info("- Email Engagement: ✅ OPENED & CLICKED");
            $this->info("- Total Interactions: " . ContactInteraction::where('contact_id', $contact->id)->count());
            $this->info("");
            $this->info("🌐 TEST URLS:");
            $this->info("- Journey Timeline: http://localhost:5173/journey-timeline?contact=$email");
            $this->info("- Journey Progress: http://localhost:5173/journey-progress");
            $this->info("- Journey Flow: http://localhost:5173/journey-flow");
            $this->info("");
            $this->info("📡 API ENDPOINTS:");
            $this->info("- GET /api/contacts/journey/$email");
            $this->info("- GET /api/contacts/{$contact->id}/journey-timeline");
            $this->info("- GET /api/contacts/{$contact->id}/journey/events");

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    private function getWelcomeEmailContent($contact)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h1 style='color: #2596be;'>Welcome to our platform, {$contact->first_name}!</h1>
                
                <p>Dear {$contact->full_name},</p>
                
                <p>Thank you for your interest in our technology solutions! We're excited to help {$contact->company->name} achieve its technology goals.</p>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #2596be;'>What's Next?</h3>
                    <ul>
                        <li>✅ Your account is being set up</li>
                        <li>📞 We'll call you within 24 hours</li>
                        <li>📊 Custom demo will be prepared</li>
                        <li>🚀 Implementation planning begins</li>
                    </ul>
                </div>
                
                <p>
                    <a href='#' style='background: #2596be; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                        Get Started Now
                    </a>
                </p>
                
                <p>Best regards,<br>
                The Technology Solutions Team</p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
                <p style='font-size: 12px; color: #666;'>
                    This email was sent as part of your welcome journey. 
                    <a href='#'>Unsubscribe</a> | <a href='#'>Update preferences</a>
                </p>
            </div>
        </body>
        </html>
        ";
    }
}
