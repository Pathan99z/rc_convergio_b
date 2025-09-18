<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignTemplate;
use App\Models\Contact;
use App\Jobs\SendCampaignJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CampaignEnhancementService
{
    /**
     * Send test email for a campaign.
     */
    public function sendTestEmail(Campaign $campaign, array $testEmails): array
    {
        DB::beginTransaction();
        try {
            // Increment test sent count
            $campaign->increment('test_sent_count');

            // Send test emails
            $sentEmails = [];
            foreach ($testEmails as $email) {
                try {
                    // Send actual test email using Laravel Mail
                    Mail::raw($this->getTestEmailContent($campaign), function ($message) use ($campaign, $email) {
                        $message->to($email)
                                ->subject('[TEST] ' . $campaign->subject)
                                ->from(config('mail.from.address'), config('mail.from.name'));
                    });
                    
                    Log::info("Test email sent successfully for campaign {$campaign->id} to {$email}");
                    $sentEmails[] = $email;
                } catch (\Exception $e) {
                    Log::error("Failed to send test email for campaign {$campaign->id} to {$email}: " . $e->getMessage());
                    throw new \Exception("Failed to send test email to {$email}: " . $e->getMessage());
                }
            }

            DB::commit();

            return [
                'campaign_id' => $campaign->id,
                'test_emails_sent' => $sentEmails,
                'test_sent_count' => $campaign->fresh()->test_sent_count,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate campaign preview.
     */
    public function generatePreview(Campaign $campaign): array
    {
        // Replace template variables with sample data
        $previewContent = $this->replaceTemplateVariables($campaign->content, [
            'name' => 'John Doe',
            'company' => 'Sample Company',
            'email' => 'john@example.com',
        ]);

        $previewSubject = $this->replaceTemplateVariables($campaign->subject, [
            'name' => 'John Doe',
            'company' => 'Sample Company',
        ]);

        return [
            'campaign_id' => $campaign->id,
            'subject' => $previewSubject,
            'content' => $previewContent,
            'html_preview' => $previewContent,
            'text_preview' => strip_tags($previewContent),
            'recipient_count' => $campaign->total_recipients ?? 0,
            'status' => $campaign->status,
        ];
    }

    /**
     * Validate campaign.
     */
    public function validateCampaign(Campaign $campaign): array
    {
        $errors = [];
        $warnings = [];

        // Required fields validation
        if (empty($campaign->name)) {
            $errors[] = 'Campaign name is required';
        }

        if (empty($campaign->subject)) {
            $errors[] = 'Campaign subject is required';
        }

        if (empty($campaign->content)) {
            $errors[] = 'Campaign content is required';
        }

        // Content validation
        if (strlen($campaign->subject) > 78) {
            $warnings[] = 'Subject line is longer than recommended (78 characters)';
        }

        if (strlen($campaign->content) < 50) {
            $warnings[] = 'Content seems too short';
        }

        // Check for unsubscribed links
        if (!str_contains($campaign->content, 'unsubscribe')) {
            $warnings[] = 'Consider adding an unsubscribe link';
        }

        // Check recipient count
        if (($campaign->total_recipients ?? 0) === 0) {
            $errors[] = 'No recipients selected';
        }

        return [
            'campaign_id' => $campaign->id,
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'validation_score' => $this->calculateValidationScore($errors, $warnings),
        ];
    }

    /**
     * Schedule campaign.
     */
    public function scheduleCampaign(Campaign $campaign, string $scheduledAt): array
    {
        DB::beginTransaction();
        try {
            $campaign->update([
                'status' => 'scheduled',
                'scheduled_at' => Carbon::parse($scheduledAt),
            ]);

            DB::commit();

            return [
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
                'scheduled_at' => $campaign->scheduled_at,
                'message' => 'Campaign scheduled successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Unschedule campaign.
     */
    public function unscheduleCampaign(Campaign $campaign): array
    {
        DB::beginTransaction();
        try {
            $campaign->update([
                'status' => 'draft',
                'scheduled_at' => null,
            ]);

            DB::commit();

            return [
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
                'scheduled_at' => null,
                'message' => 'Campaign unscheduled successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Archive campaign.
     */
    public function archiveCampaign(Campaign $campaign): array
    {
        DB::beginTransaction();
        try {
            $campaign->update([
                'status' => 'archived',
                'archived_at' => now(),
            ]);

            DB::commit();

            return [
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
                'archived_at' => $campaign->archived_at,
                'message' => 'Campaign archived successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Restore archived campaign.
     */
    public function restoreCampaign(Campaign $campaign): array
    {
        DB::beginTransaction();
        try {
            $campaign->update([
                'status' => 'draft',
                'archived_at' => null,
            ]);

            DB::commit();

            return [
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
                'archived_at' => null,
                'message' => 'Campaign restored successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Bulk send campaigns.
     */
    public function bulkSendCampaigns(int $tenantId, array $campaignIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($campaignIds as $campaignId) {
            try {
                $campaign = Campaign::forTenant($tenantId)->find($campaignId);
                
                if (!$campaign) {
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'status' => 'error',
                        'message' => 'Campaign not found'
                    ];
                    $errorCount++;
                    continue;
                }

                if (!in_array($campaign->status, ['draft', 'scheduled'])) {
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'status' => 'error',
                        'message' => 'Campaign cannot be sent in current status'
                    ];
                    $errorCount++;
                    continue;
                }

                // Dispatch job to send campaign
                SendCampaignJob::dispatch($campaign);
                
                $results[] = [
                    'campaign_id' => $campaignId,
                    'status' => 'success',
                    'message' => 'Campaign queued for sending'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'campaign_id' => $campaignId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_campaigns' => count($campaignIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk pause campaigns.
     */
    public function bulkPauseCampaigns(int $tenantId, array $campaignIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($campaignIds as $campaignId) {
            try {
                $campaign = Campaign::forTenant($tenantId)->find($campaignId);
                
                if (!$campaign) {
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'status' => 'error',
                        'message' => 'Campaign not found'
                    ];
                    $errorCount++;
                    continue;
                }

                if (!in_array($campaign->status, ['scheduled', 'sending'])) {
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'status' => 'error',
                        'message' => 'Campaign cannot be paused in current status'
                    ];
                    $errorCount++;
                    continue;
                }

                $campaign->update(['status' => 'cancelled']);
                
                $results[] = [
                    'campaign_id' => $campaignId,
                    'status' => 'success',
                    'message' => 'Campaign paused successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'campaign_id' => $campaignId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_campaigns' => count($campaignIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk resume campaigns.
     */
    public function bulkResumeCampaigns(int $tenantId, array $campaignIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($campaignIds as $campaignId) {
            try {
                $campaign = Campaign::forTenant($tenantId)->find($campaignId);
                
                if (!$campaign) {
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'status' => 'error',
                        'message' => 'Campaign not found'
                    ];
                    $errorCount++;
                    continue;
                }

                if ($campaign->status !== 'cancelled') {
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'status' => 'error',
                        'message' => 'Only cancelled campaigns can be resumed'
                    ];
                    $errorCount++;
                    continue;
                }

                $newStatus = $campaign->scheduled_at ? 'scheduled' : 'draft';
                $campaign->update(['status' => $newStatus]);
                
                $results[] = [
                    'campaign_id' => $campaignId,
                    'status' => 'success',
                    'message' => 'Campaign resumed successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'campaign_id' => $campaignId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_campaigns' => count($campaignIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk archive campaigns.
     */
    public function bulkArchiveCampaigns(int $tenantId, array $campaignIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($campaignIds as $campaignId) {
            try {
                $campaign = Campaign::forTenant($tenantId)->find($campaignId);
                
                if (!$campaign) {
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'status' => 'error',
                        'message' => 'Campaign not found'
                    ];
                    $errorCount++;
                    continue;
                }

                if ($campaign->status === 'archived') {
                    $results[] = [
                        'campaign_id' => $campaignId,
                        'status' => 'error',
                        'message' => 'Campaign is already archived'
                    ];
                    $errorCount++;
                    continue;
                }

                $campaign->update([
                    'status' => 'archived',
                    'archived_at' => now(),
                ]);
                
                $results[] = [
                    'campaign_id' => $campaignId,
                    'status' => 'success',
                    'message' => 'Campaign archived successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'campaign_id' => $campaignId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_campaigns' => count($campaignIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Export campaigns.
     */
    public function exportCampaigns(int $tenantId, array $filters = []): array
    {
        $query = Campaign::forTenant($tenantId);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $campaigns = $query->get();

        $format = $filters['format'] ?? 'json';
        $filename = 'campaigns_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        // Generate export data
        $exportData = $campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'subject' => $campaign->subject,
                'status' => $campaign->status,
                'type' => $campaign->type,
                'total_recipients' => $campaign->total_recipients,
                'sent_count' => $campaign->sent_count,
                'opened_count' => $campaign->opened_count,
                'clicked_count' => $campaign->clicked_count,
                'created_at' => $campaign->created_at,
                'sent_at' => $campaign->sent_at,
            ];
        });

        // Store file temporarily
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'total_campaigns' => $campaigns->count(),
            'format' => $format,
        ];
    }

    /**
     * Import campaigns.
     */
    public function importCampaigns(int $tenantId, UploadedFile $file, ?int $templateId = null, int $userId = null): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        try {
            // Read file content
            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);

            if (!$data) {
                throw new \Exception('Invalid file format');
            }

            $template = null;
            if ($templateId) {
                $template = CampaignTemplate::forTenant($tenantId)->find($templateId);
            }

            foreach ($data as $row) {
                try {
                    $campaignData = [
                        'name' => $row['name'] ?? 'Imported Campaign',
                        'subject' => $row['subject'] ?? 'Imported Subject',
                        'content' => $row['content'] ?? ($template ? $template->content : 'Imported content'),
                        'type' => $row['type'] ?? 'email',
                        'status' => 'draft',
                        'tenant_id' => $tenantId,
                        'created_by' => $userId,
                    ];

                    if ($template) {
                        $campaignData['settings'] = $template->settings;
                    }

                    Campaign::create($campaignData);
                    $successCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'row' => $row,
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            throw new \Exception('Failed to process import file: ' . $e->getMessage());
        }

        return [
            'total_rows' => count($data),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $results,
        ];
    }

    /**
     * Replace template variables in content.
     */
    private function replaceTemplateVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
            $content = str_replace("{{ $key }}", $value, $content);
        }

        return $content;
    }

    /**
     * Calculate validation score.
     */
    private function calculateValidationScore(array $errors, array $warnings): int
    {
        $score = 100;
        $score -= count($errors) * 20; // Each error reduces score by 20
        $score -= count($warnings) * 5; // Each warning reduces score by 5
        return max(0, $score);
    }

    /**
     * Generate test email content.
     */
    private function getTestEmailContent(Campaign $campaign): string
    {
        $content = "This is a test email for campaign: {$campaign->name}\n\n";
        $content .= "Subject: {$campaign->subject}\n\n";
        $content .= "Content:\n";
        $content .= strip_tags($campaign->content) . "\n\n";
        $content .= "---\n";
        $content .= "This is a test email sent from RC Convergio CRM.\n";
        $content .= "Campaign ID: {$campaign->id}\n";
        $content .= "Sent at: " . now()->format('Y-m-d H:i:s') . "\n";
        
        return $content;
    }
}
