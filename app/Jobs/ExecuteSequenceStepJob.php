<?php

namespace App\Jobs;

use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Models\SequenceLog;
use App\Models\Task;
use App\Models\Activity;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ExecuteSequenceStepJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    protected $enrollmentId;
    protected $stepId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $enrollmentId, int $stepId)
    {
        $this->enrollmentId = $enrollmentId;
        $this->stepId = $stepId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $enrollment = SequenceEnrollment::find($this->enrollmentId);
            $step = SequenceStep::find($this->stepId);

            if (!$enrollment || !$step) {
                Log::warning('Sequence step execution failed: enrollment or step not found', [
                    'enrollment_id' => $this->enrollmentId,
                    'step_id' => $this->stepId,
                ]);
                return;
            }

            // Check if enrollment is still active and on the correct step
            if (!$enrollment->isActive() || $enrollment->current_step !== $step->step_order) {
                Log::info('Sequence step skipped: enrollment not active or wrong step', [
                    'enrollment_id' => $this->enrollmentId,
                    'step_id' => $this->stepId,
                    'current_step' => $enrollment->current_step,
                    'expected_step' => $step->step_order,
                ]);
                return;
            }

            // Execute the step based on action type
            $result = $this->executeStep($enrollment, $step);

            // Log the execution
            $this->logExecution($enrollment, $step, $result);

            // Advance to next step or complete sequence
            $this->advanceSequence($enrollment, $step);

        } catch (\Exception $e) {
            Log::error('Sequence step execution failed', [
                'enrollment_id' => $this->enrollmentId,
                'step_id' => $this->stepId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Log the failure
            if (isset($enrollment) && isset($step)) {
                $this->logExecution($enrollment, $step, [
                    'status' => 'failed',
                    'notes' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Execute the specific step action
     */
    protected function executeStep(SequenceEnrollment $enrollment, SequenceStep $step): array
    {
        switch ($step->action_type) {
            case 'email':
                return $this->executeEmailStep($enrollment, $step);
            case 'task':
                return $this->executeTaskStep($enrollment, $step);
            case 'wait':
                return $this->executeWaitStep($enrollment, $step);
            default:
                throw new \Exception("Unknown action type: {$step->action_type}");
        }
    }

    /**
     * Execute email step
     */
    protected function executeEmailStep(SequenceEnrollment $enrollment, SequenceStep $step): array
    {
        if (!$step->email_template_id) {
            throw new \Exception('Email template ID is required for email steps');
        }

        // Get the target's email address
        $target = $this->getTargetModel($enrollment);
        $emailAddress = $this->getTargetEmailAddress($target, $enrollment->target_type);

        if (!$emailAddress) {
            return [
                'status' => 'failed',
                'notes' => 'No email address found for target',
            ];
        }

        // Get email template (CampaignTemplate for sequences)
        $template = \App\Models\CampaignTemplate::find($step->email_template_id);
        if (!$template) {
            return [
                'status' => 'failed',
                'notes' => 'Email template not found',
            ];
        }

        // Generate email content using template (same as campaigns)
        $emailContent = $this->generateEmailContentFromTemplate($template, $target, $enrollment->target_type);
        $subject = $this->replaceTemplateVariables($template->subject, $target, $enrollment->target_type);

        // Send email using Laravel Mail (same as campaigns)
        \Illuminate\Support\Facades\Mail::html($emailContent, function ($message) use ($subject, $emailAddress, $target, $enrollment) {
            $message->to($emailAddress, $this->getTargetName($target, $enrollment->target_type))
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
        });

        return [
            'status' => 'success',
            'notes' => 'Email sent successfully',
        ];
    }

    /**
     * Execute task step
     */
    protected function executeTaskStep(SequenceEnrollment $enrollment, SequenceStep $step): array
    {
        if (!$step->task_title) {
            throw new \Exception('Task title is required for task steps');
        }

        // Create task
        $task = Task::create([
            'title' => $step->task_title,
            'description' => $step->task_description,
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => now()->addDays(7),
            'owner_id' => $enrollment->created_by,
            'tenant_id' => $enrollment->tenant_id,
            'related_type' => $enrollment->target_type,
            'related_id' => $enrollment->target_id,
        ]);

        // Create activity log
        Activity::create([
            'type' => 'task_created',
            'subject' => 'Task created from sequence',
            'description' => "Task '{$step->task_title}' was created from sequence step",
            'status' => 'completed',
            'completed_at' => now(),
            'owner_id' => $enrollment->created_by,
            'tenant_id' => $enrollment->tenant_id,
            'related_type' => $enrollment->target_type,
            'related_id' => $enrollment->target_id,
        ]);

        return [
            'status' => 'success',
            'notes' => "Task created: {$task->title}",
        ];
    }

    /**
     * Execute wait step
     */
    protected function executeWaitStep(SequenceEnrollment $enrollment, SequenceStep $step): array
    {
        // Wait steps don't perform any action, just advance to next step
        return [
            'status' => 'success',
            'notes' => 'Wait step completed',
        ];
    }

    /**
     * Get target model based on target type
     */
    protected function getTargetModel(SequenceEnrollment $enrollment)
    {
        switch ($enrollment->target_type) {
            case 'contact':
                return \App\Models\Contact::find($enrollment->target_id);
            case 'deal':
                return \App\Models\Deal::find($enrollment->target_id);
            case 'company':
                return \App\Models\Company::find($enrollment->target_id);
            default:
                return null;
        }
    }

    /**
     * Get target email address
     */
    protected function getTargetEmailAddress($target, string $targetType): ?string
    {
        switch ($targetType) {
            case 'contact':
                return $target->email ?? null;
            case 'company':
                // Get primary contact email
                $primaryContact = $target->contacts()->where('is_primary', true)->first();
                return $primaryContact?->email ?? $target->email ?? null;
            case 'deal':
                // Get contact email from deal
                $contact = $target->contact;
                return $contact?->email ?? null;
            default:
                return null;
        }
    }

    /**
     * Log the step execution
     */
    protected function logExecution(SequenceEnrollment $enrollment, SequenceStep $step, array $result): void
    {
        SequenceLog::create([
            'enrollment_id' => $enrollment->id,
            'step_id' => $step->id,
            'action_performed' => $step->action_type,
            'performed_at' => now(),
            'status' => $result['status'],
            'notes' => $result['notes'] ?? null,
            'tenant_id' => $enrollment->tenant_id,
            'created_by' => $enrollment->created_by,
        ]);
    }

    /**
     * Advance the sequence to the next step or complete it
     */
    protected function advanceSequence(SequenceEnrollment $enrollment, SequenceStep $step): void
    {
        $enrollment->advanceStep();

        // Check if this was the last step
        $totalSteps = $enrollment->sequence->steps()->count();
        if ($enrollment->current_step >= $totalSteps) {
            $enrollment->markCompleted();
            
            // Fire sequence completed event
            event(new \App\Events\SequenceCompleted($enrollment));
        } else {
            // Schedule next step
            $nextStep = $enrollment->sequence->steps()
                ->where('step_order', $enrollment->current_step + 1)
                ->first();
            
            if ($nextStep) {
                $delayHours = $nextStep->delay_hours;
                if ($delayHours > 0) {
                    ExecuteSequenceStepJob::dispatch($enrollment->id, $nextStep->id)
                        ->delay(now()->addHours($delayHours));
                } else {
                    ExecuteSequenceStepJob::dispatch($enrollment->id, $nextStep->id);
                }
            }
        }
    }

    /**
     * Generate email content from template (same as campaigns)
     */
    protected function generateEmailContentFromTemplate($template, $target, string $targetType): string
    {
        // Check if this is a pre-built template type
        $templateType = $this->detectTemplateType($template);
        
        if ($templateType) {
            // Generate smart content for pre-built templates
            $content = $this->generatePreBuiltTemplateContent($templateType, $target, $targetType);
        } else {
            // Use existing template content with variable replacement
            $content = $template->content;
            
            // Replace template variables with target data
            $placeholders = $this->getTemplatePlaceholders($target, $targetType);
            
            foreach ($placeholders as $placeholder => $value) {
                $content = str_replace($placeholder, $value, $content);
            }
        }
        
        return $content;
    }

    /**
     * Replace template variables in subject (same as campaigns)
     */
    protected function replaceTemplateVariables(string $content, $target, string $targetType): string
    {
        $placeholders = $this->getTemplatePlaceholders($target, $targetType);
        
        foreach ($placeholders as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }
        
        return $content;
    }

    /**
     * Get template placeholders for target (same as campaigns)
     */
    protected function getTemplatePlaceholders($target, string $targetType): array
    {
        switch ($targetType) {
            case 'contact':
                return [
                    '{{first_name}}' => $target->first_name ?? '',
                    '{{last_name}}' => $target->last_name ?? '',
                    '{{email}}' => $target->email ?? '',
                    '{{phone}}' => $target->phone ?? '',
                    '{{company}}' => $target->company?->name ?? '',
                ];
            case 'company':
                return [
                    '{{company_name}}' => $target->name ?? '',
                    '{{email}}' => $target->email ?? '',
                    '{{phone}}' => $target->phone ?? '',
                ];
            case 'deal':
                $contact = $target->contact;
                $latestQuote = $target->quotes()->latest()->first();
                $quoteLink = $latestQuote ? $this->generateQuoteLink($latestQuote) : '';
                
                return [
                    '{{deal_name}}' => $target->name ?? '',
                    '{{deal_value}}' => '$' . number_format($target->value ?? 0, 2),
                    '{{first_name}}' => $contact?->first_name ?? '',
                    '{{last_name}}' => $contact?->last_name ?? '',
                    '{{email}}' => $contact?->email ?? '',
                    '{{phone}}' => $contact?->phone ?? '',
                    '{{company}}' => $contact?->company?->name ?? '',
                    '{{quote_link}}' => $quoteLink,
                    '{{quote_number}}' => $latestQuote?->quote_number ?? '',
                    '{{quote_total}}' => $latestQuote ? '$' . number_format($latestQuote->total ?? 0, 2) : '',
                ];
            default:
                return [];
        }
    }

    /**
     * Get target name for email
     */
    protected function getTargetName($target, string $targetType): string
    {
        switch ($targetType) {
            case 'contact':
                return trim(($target->first_name ?? '') . ' ' . ($target->last_name ?? ''));
            case 'company':
                return $target->name ?? '';
            case 'deal':
                $contact = $target->contact;
                return trim(($contact?->first_name ?? '') . ' ' . ($contact?->last_name ?? ''));
            default:
                return '';
        }
    }

    /**
     * Generate quote link for email templates
     */
    protected function generateQuoteLink($quote): string
    {
        // Generate the quote viewing URL
        $baseUrl = config('app.url');
        return "{$baseUrl}/quotes/{$quote->uuid}/view";
    }

    /**
     * Detect if template is a pre-built template type
     */
    protected function detectTemplateType($template): ?string
    {
        $templateName = strtolower($template->name);
        
        // Check for pre-built template types
        if (strpos($templateName, 'quote follow') !== false || strpos($templateName, 'quote follow-up') !== false) {
            return 'quote_followup';
        }
        
        if (strpos($templateName, 'deal follow') !== false || strpos($templateName, 'deal follow-up') !== false) {
            return 'deal_followup';
        }
        
        if (strpos($templateName, 'welcome') !== false) {
            return 'welcome';
        }
        
        if (strpos($templateName, 'meeting') !== false) {
            return 'meeting_reminder';
        }
        
        return null; // Not a pre-built template
    }

    /**
     * Generate pre-built template content
     */
    protected function generatePreBuiltTemplateContent(string $templateType, $target, string $targetType): string
    {
        $placeholders = $this->getTemplatePlaceholders($target, $targetType);
        
        switch ($templateType) {
            case 'quote_followup':
                return $this->generateQuoteFollowupContent($placeholders);
            case 'deal_followup':
                return $this->generateDealFollowupContent($placeholders);
            case 'welcome':
                return $this->generateWelcomeContent($placeholders);
            case 'meeting_reminder':
                return $this->generateMeetingReminderContent($placeholders);
            default:
                return $this->generateDefaultContent($placeholders);
        }
    }

    /**
     * Generate quote follow-up template content
     */
    protected function generateQuoteFollowupContent(array $placeholders): string
    {
        $firstName = $placeholders['{{first_name}}'] ?? '';
        $lastName = $placeholders['{{last_name}}'] ?? '';
        $dealName = $placeholders['{{deal_name}}'] ?? '';
        $quoteNumber = $placeholders['{{quote_number}}'] ?? '';
        $quoteTotal = $placeholders['{{quote_total}}'] ?? '';
        $quoteLink = $placeholders['{{quote_link}}'] ?? '';
        
        $name = trim($firstName . ' ' . $lastName);
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Hi {$name},</h2>
            
            <p>Thank you for your interest in <strong>{$dealName}</strong>.</p>
            
            <p>Your quote <strong>{$quoteNumber}</strong> for <strong>{$quoteTotal}</strong> is ready for review.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$quoteLink}' style='background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Your Quote</a>
            </div>
            
            <p>If you have any questions about this quote, please don't hesitate to reach out.</p>
            
            <p>Best regards,<br>
            RC Convergio Team</p>
        </div>";
    }

    /**
     * Generate deal follow-up template content
     */
    protected function generateDealFollowupContent(array $placeholders): string
    {
        $firstName = $placeholders['{{first_name}}'] ?? '';
        $lastName = $placeholders['{{last_name}}'] ?? '';
        $dealName = $placeholders['{{deal_name}}'] ?? '';
        $dealValue = $placeholders['{{deal_value}}'] ?? '';
        
        $name = trim($firstName . ' ' . $lastName);
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Hi {$name},</h2>
            
            <p>I wanted to follow up on our discussion about <strong>{$dealName}</strong> (Value: {$dealValue}).</p>
            
            <p>I'm excited about the opportunity to work with you and would love to discuss the next steps.</p>
            
            <p>Would you be available for a brief call this week to discuss your requirements in more detail?</p>
            
            <p>Looking forward to hearing from you.</p>
            
            <p>Best regards,<br>
            RC Convergio Team</p>
        </div>";
    }

    /**
     * Generate welcome template content
     */
    protected function generateWelcomeContent(array $placeholders): string
    {
        $firstName = $placeholders['{{first_name}}'] ?? '';
        $lastName = $placeholders['{{last_name}}'] ?? '';
        $company = $placeholders['{{company}}'] ?? '';
        
        $name = trim($firstName . ' ' . $lastName);
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Welcome {$name}!</h2>
            
            <p>Thank you for choosing RC Convergio for your business needs.</p>
            
            <p>We're excited to have you on board and look forward to providing you with exceptional service.</p>
            
            <p>If you have any questions or need assistance, please don't hesitate to reach out to our team.</p>
            
            <p>Welcome aboard!</p>
            
            <p>Best regards,<br>
            RC Convergio Team</p>
        </div>";
    }

    /**
     * Generate meeting reminder template content
     */
    protected function generateMeetingReminderContent(array $placeholders): string
    {
        $firstName = $placeholders['{{first_name}}'] ?? '';
        $lastName = $placeholders['{{last_name}}'] ?? '';
        
        $name = trim($firstName . ' ' . $lastName);
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Hi {$name},</h2>
            
            <p>This is a friendly reminder about our upcoming meeting.</p>
            
            <p>I'm looking forward to our discussion and will be prepared to answer any questions you may have.</p>
            
            <p>If you need to reschedule or have any questions, please let me know.</p>
            
            <p>See you soon!</p>
            
            <p>Best regards,<br>
            RC Convergio Team</p>
        </div>";
    }

    /**
     * Generate default template content
     */
    protected function generateDefaultContent(array $placeholders): string
    {
        $firstName = $placeholders['{{first_name}}'] ?? '';
        $lastName = $placeholders['{{last_name}}'] ?? '';
        
        $name = trim($firstName . ' ' . $lastName);
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Hi {$name},</h2>
            
            <p>Thank you for your interest in our services.</p>
            
            <p>We appreciate your business and look forward to working with you.</p>
            
            <p>Best regards,<br>
            RC Convergio Team</p>
        </div>";
    }
}
