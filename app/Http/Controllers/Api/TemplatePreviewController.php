<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignTemplate;
use App\Models\Deal;
use App\Models\Contact;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TemplatePreviewController extends Controller
{
    /**
     * Preview template content with sample data
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|integer|exists:campaign_templates,id',
            'target_type' => 'required|string|in:contact,deal,company',
            'target_id' => 'nullable|integer',
        ]);

        $template = CampaignTemplate::find($request->template_id);
        $targetType = $request->target_type;
        $targetId = $request->target_id;

        // Get target data (real or sample)
        $target = $this->getTargetData($targetType, $targetId);
        
        // Generate preview content
        $previewData = $this->generatePreviewContent($template, $target, $targetType);

        return response()->json([
            'success' => true,
            'data' => $previewData
        ]);
    }

    /**
     * Update template content (for editing pre-built templates)
     */
    public function updateContent(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|integer|exists:campaign_templates,id',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'is_custom' => 'boolean',
        ]);

        $template = CampaignTemplate::find($request->template_id);
        
        // Check if user can edit this template
        if ($template->tenant_id !== $request->user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit this template.'
            ], 403);
        }

        // Update template content
        $template->update([
            'subject' => $request->subject,
            'content' => $request->content,
            'updated_by' => $request->user()->id,
        ]);

        // If marked as custom, rename to indicate it's been customized
        if ($request->is_custom && !str_contains($template->name, '(Customized)')) {
            $template->update([
                'name' => $template->name . ' (Customized)'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully.',
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
                'subject' => $template->subject,
                'content' => $template->content,
                'updated_at' => $template->updated_at,
            ]
        ]);
    }

    /**
     * Create a custom template from pre-built template
     */
    public function createCustom(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|integer|exists:campaign_templates,id',
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $originalTemplate = CampaignTemplate::find($request->template_id);
        
        // Check if user can access this template
        if ($originalTemplate->tenant_id !== $request->user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to access this template.'
            ], 403);
        }

        // Create new custom template
        $customTemplate = CampaignTemplate::create([
            'name' => $request->name,
            'description' => 'Custom template based on ' . $originalTemplate->name,
            'subject' => $request->subject,
            'content' => $request->content,
            'type' => 'email',
            'is_active' => true,
            'tenant_id' => $request->user()->tenant_id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom template created successfully.',
            'data' => [
                'id' => $customTemplate->id,
                'name' => $customTemplate->name,
                'subject' => $customTemplate->subject,
                'content' => $customTemplate->content,
                'created_at' => $customTemplate->created_at,
            ]
        ]);
    }

    /**
     * Get target data (real or sample)
     */
    private function getTargetData(string $targetType, ?int $targetId)
    {
        if ($targetId) {
            // Use real target data
            switch ($targetType) {
                case 'deal':
                    return Deal::with(['contact', 'quotes'])->find($targetId);
                case 'contact':
                    return Contact::with('company')->find($targetId);
                case 'company':
                    return Company::find($targetId);
            }
        }

        // Use sample data for preview
        return $this->getSampleData($targetType);
    }

    /**
     * Get sample data for preview
     */
    private function getSampleData(string $targetType)
    {
        switch ($targetType) {
            case 'deal':
                return (object) [
                    'name' => 'Sample Deal',
                    'value' => 50000,
                    'contact' => (object) [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john.doe@example.com',
                        'phone' => '+1-555-0123',
                        'company' => (object) ['name' => 'Sample Company']
                    ],
                    'quotes' => collect([
                        (object) [
                            'quote_number' => 'Q-2025-0001',
                            'total' => 50000,
                            'uuid' => 'sample-uuid-123'
                        ]
                    ])
                ];
            case 'contact':
                return (object) [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'email' => 'jane.smith@example.com',
                    'phone' => '+1-555-0456',
                    'company' => (object) ['name' => 'Sample Company']
                ];
            case 'company':
                return (object) [
                    'name' => 'Sample Company',
                    'email' => 'info@samplecompany.com',
                    'phone' => '+1-555-0789'
                ];
        }
    }

    /**
     * Generate preview content
     */
    private function generatePreviewContent($template, $target, string $targetType): array
    {
        // Check if this is a pre-built template
        $templateType = $this->detectTemplateType($template);
        
        if ($templateType) {
            // Generate smart content for pre-built templates
            $content = $this->generatePreBuiltTemplateContent($templateType, $target, $targetType);
            $subject = $this->generatePreBuiltSubject($templateType, $target, $targetType);
        } else {
            // Use existing template content with variable replacement
            $content = $template->content;
            $subject = $template->subject;
            
            // Replace template variables
            $placeholders = $this->getTemplatePlaceholders($target, $targetType);
            foreach ($placeholders as $placeholder => $value) {
                $content = str_replace($placeholder, $value, $content);
                $subject = str_replace($placeholder, $value, $subject);
            }
        }

        return [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_type' => $templateType,
            'subject' => $subject,
            'content' => $content,
            'is_prebuilt' => $templateType !== null,
            'target_type' => $targetType,
            'target_data' => $this->getTargetSummary($target, $targetType),
            'available_variables' => $this->getAvailableVariables($targetType)
        ];
    }

    /**
     * Detect if template is a pre-built template type
     */
    private function detectTemplateType($template): ?string
    {
        $templateName = strtolower($template->name);
        
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
        
        return null;
    }

    /**
     * Generate pre-built template content (same as ExecuteSequenceStepJob)
     */
    private function generatePreBuiltTemplateContent(string $templateType, $target, string $targetType): string
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
     * Generate pre-built subject
     */
    private function generatePreBuiltSubject(string $templateType, $target, string $targetType): string
    {
        $placeholders = $this->getTemplatePlaceholders($target, $targetType);
        $firstName = $placeholders['{{first_name}}'] ?? '';
        $lastName = $placeholders['{{last_name}}'] ?? '';
        $name = trim($firstName . ' ' . $lastName);
        
        switch ($templateType) {
            case 'quote_followup':
                return "Your Quote is Ready - " . ($placeholders['{{quote_number}}'] ?? '');
            case 'deal_followup':
                return "Following up on " . ($placeholders['{{deal_name}}'] ?? 'your deal');
            case 'welcome':
                return "Welcome to RC Convergio, " . $name;
            case 'meeting_reminder':
                return "Meeting Reminder - " . $name;
            default:
                return "Message from RC Convergio";
        }
    }

    /**
     * Generate quote follow-up template content
     */
    private function generateQuoteFollowupContent(array $placeholders): string
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
    private function generateDealFollowupContent(array $placeholders): string
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
    private function generateWelcomeContent(array $placeholders): string
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
    private function generateMeetingReminderContent(array $placeholders): string
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
    private function generateDefaultContent(array $placeholders): string
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

    /**
     * Get template placeholders for target
     */
    private function getTemplatePlaceholders($target, string $targetType): array
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
                $latestQuote = $target->quotes?->sortByDesc('created_at')->first();
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
     * Generate quote link
     */
    private function generateQuoteLink($quote): string
    {
        $baseUrl = config('app.url');
        return "{$baseUrl}/quotes/{$quote->uuid}/view";
    }

    /**
     * Get target summary for preview
     */
    private function getTargetSummary($target, string $targetType): array
    {
        switch ($targetType) {
            case 'deal':
                return [
                    'name' => $target->name ?? 'Sample Deal',
                    'value' => '$' . number_format($target->value ?? 0, 2),
                    'contact' => $target->contact ? $target->contact->first_name . ' ' . $target->contact->last_name : 'No contact',
                    'email' => $target->contact?->email ?? 'No email',
                    'quotes_count' => $target->quotes?->count() ?? 0
                ];
            case 'contact':
                return [
                    'name' => ($target->first_name ?? '') . ' ' . ($target->last_name ?? ''),
                    'email' => $target->email ?? 'No email',
                    'phone' => $target->phone ?? 'No phone',
                    'company' => $target->company?->name ?? 'No company'
                ];
            case 'company':
                return [
                    'name' => $target->name ?? 'Sample Company',
                    'email' => $target->email ?? 'No email',
                    'phone' => $target->phone ?? 'No phone'
                ];
            default:
                return [];
        }
    }

    /**
     * Get available variables for target type
     */
    private function getAvailableVariables(string $targetType): array
    {
        switch ($targetType) {
            case 'deal':
                return [
                    '{{deal_name}}' => 'Deal name',
                    '{{deal_value}}' => 'Deal value',
                    '{{first_name}}' => 'Contact first name',
                    '{{last_name}}' => 'Contact last name',
                    '{{email}}' => 'Contact email',
                    '{{phone}}' => 'Contact phone',
                    '{{company}}' => 'Contact company',
                    '{{quote_link}}' => 'Latest quote link',
                    '{{quote_number}}' => 'Latest quote number',
                    '{{quote_total}}' => 'Latest quote total'
                ];
            case 'contact':
                return [
                    '{{first_name}}' => 'First name',
                    '{{last_name}}' => 'Last name',
                    '{{email}}' => 'Email address',
                    '{{phone}}' => 'Phone number',
                    '{{company}}' => 'Company name'
                ];
            case 'company':
                return [
                    '{{company_name}}' => 'Company name',
                    '{{email}}' => 'Email address',
                    '{{phone}}' => 'Phone number'
                ];
            default:
                return [];
        }
    }
}
