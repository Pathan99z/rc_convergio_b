<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\IntentEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $exportJob;

    /**
     * Create a new job instance.
     */
    public function __construct(ExportJob $exportJob)
    {
        $this->exportJob = $exportJob;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->exportJob->markAsStarted();

            Log::info('Starting export job', [
                'job_id' => $this->exportJob->job_id,
                'type' => $this->exportJob->type,
                'tenant_id' => $this->exportJob->tenant_id
            ]);

            // Generate filename
            $filename = $this->generateFilename();
            $filePath = $this->getFilePath($filename);

            // Process export based on type
            if ($this->exportJob->type === 'csv') {
                $this->exportToCsv($filePath);
            } elseif ($this->exportJob->type === 'json') {
                $this->exportToJson($filePath);
            } else {
                throw new \Exception("Unsupported export type: {$this->exportJob->type}");
            }

            // Mark as completed
            $this->exportJob->markAsCompleted($filePath, $this->getTotalRecords());

            Log::info('Export job completed successfully', [
                'job_id' => $this->exportJob->job_id,
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            Log::error('Export job failed', [
                'job_id' => $this->exportJob->job_id,
                'error' => $e->getMessage()
            ]);

            $this->exportJob->markAsFailed($e->getMessage());
        }
    }

    /**
     * Export data to CSV format.
     */
    private function exportToCsv(string $filePath): void
    {
        $handle = fopen($filePath, 'w');
        
        if (!$handle) {
            throw new \Exception('Failed to create CSV file');
        }

        try {
            // Write CSV header with professional business-friendly column names
            $headers = [
                'Event ID', 'Action Type', 'Action Name', 'Intent Score', 'Contact ID', 'Company ID',
                'Page Visited', 'User Action', 'Source', 'Session ID', 'IP Address', 'Browser Info',
                'Date & Time', 'Last Updated'
            ];
            fputcsv($handle, $headers);

            // Get data with pagination to handle large datasets
            $page = 1;
            $perPage = 1000;
            $totalProcessed = 0;

            do {
                $events = $this->getEvents($page, $perPage);
                
                foreach ($events as $event) {
                    $eventData = json_decode($event->event_data, true);
                    
                    // Format data professionally for business users
                    $pageUrl = $eventData['page_url'] ?? '';
                    $pageName = $this->formatPageName($pageUrl);
                    
                    $actionName = $this->formatActionName($event->event_name);
                    
                    // Format user agent to show just browser info
                    $browserInfo = $this->formatBrowserInfo($event->user_agent);
                    
                    $row = [
                        $event->id,
                        $this->formatEventType($event->event_type),
                        $actionName,
                        $event->intent_score,
                        $event->contact_id ?? 'Anonymous',
                        $event->company_id ?? 'Unknown Company',
                        $pageName,
                        $actionName,
                        $this->formatSource($event->source),
                        $event->session_id,
                        $event->ip_address,
                        $browserInfo,
                        $event->created_at->format('M d, Y h:i A'),
                        $event->updated_at->format('M d, Y h:i A'),
                    ];
                    
                    fputcsv($handle, $row);
                    $totalProcessed++;
                }

                $this->exportJob->updateProgress($totalProcessed);
                $page++;

            } while ($events->count() === $perPage);

        } finally {
            fclose($handle);
        }
    }

    /**
     * Export data to JSON format.
     */
    private function exportToJson(string $filePath): void
    {
        $data = [];
        $page = 1;
        $perPage = 1000;
        $totalProcessed = 0;

        do {
            $events = $this->getEvents($page, $perPage);
            
            foreach ($events as $event) {
                $eventData = json_decode($event->event_data, true);
                $metadata = json_decode($event->metadata, true);
                
                $data[] = [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'event_name' => $event->event_name,
                    'intent_score' => $event->intent_score,
                    'contact_id' => $event->contact_id,
                    'company_id' => $event->company_id,
                    'page_url' => $eventData['page_url'] ?? null,
                    'page_url_normalized' => $eventData['page_url_normalized'] ?? null,
                    'action' => $eventData['action'] ?? null,
                    'source' => $event->source,
                    'session_id' => $event->session_id,
                    'ip_address' => $event->ip_address,
                    'user_agent' => $event->user_agent,
                    'metadata' => $metadata,
                    'created_at' => $event->created_at->toISOString(),
                    'updated_at' => $event->updated_at->toISOString(),
                ];
                
                $totalProcessed++;
            }

            $this->exportJob->updateProgress($totalProcessed);
            $page++;

        } while ($events->count() === $perPage);

        // Write JSON file
        $jsonData = json_encode($data, JSON_PRETTY_PRINT);
        if (file_put_contents($filePath, $jsonData) === false) {
            throw new \Exception('Failed to write JSON file');
        }
    }

    /**
     * Get events for export with filters.
     */
    private function getEvents(int $page, int $perPage)
    {
        $query = IntentEvent::where('tenant_id', $this->exportJob->tenant_id);

        // Apply filters from parameters
        $parameters = $this->exportJob->parameters;
        
        if (isset($parameters['date_from'])) {
            $query->where('created_at', '>=', $parameters['date_from']);
        }
        
        if (isset($parameters['date_to'])) {
            $query->where('created_at', '<=', $parameters['date_to']);
        }
        
        if (isset($parameters['min_score'])) {
            $query->where('intent_score', '>=', $parameters['min_score']);
        }
        
        if (isset($parameters['action'])) {
            $query->where('event_name', $parameters['action']);
        }
        
        if (isset($parameters['contact_id'])) {
            $query->where('contact_id', $parameters['contact_id']);
        }
        
        if (isset($parameters['company_id'])) {
            $query->where('company_id', $parameters['company_id']);
        }

        return $query->orderBy('created_at', 'desc')
                    ->offset(($page - 1) * $perPage)
                    ->limit($perPage)
                    ->get();
    }

    /**
     * Get total number of records to export.
     */
    private function getTotalRecords(): int
    {
        $query = IntentEvent::where('tenant_id', $this->exportJob->tenant_id);

        // Apply same filters as getEvents
        $parameters = $this->exportJob->parameters;
        
        if (isset($parameters['date_from'])) {
            $query->where('created_at', '>=', $parameters['date_from']);
        }
        
        if (isset($parameters['date_to'])) {
            $query->where('created_at', '<=', $parameters['date_to']);
        }
        
        if (isset($parameters['min_score'])) {
            $query->where('intent_score', '>=', $parameters['min_score']);
        }
        
        if (isset($parameters['action'])) {
            $query->where('event_name', $parameters['action']);
        }
        
        if (isset($parameters['contact_id'])) {
            $query->where('contact_id', $parameters['contact_id']);
        }
        
        if (isset($parameters['company_id'])) {
            $query->where('company_id', $parameters['company_id']);
        }

        return $query->count();
    }

    /**
     * Generate filename for export.
     */
    private function generateFilename(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $type = $this->exportJob->type;
        $tenantId = $this->exportJob->tenant_id;
        
        return "intent_export_tenant_{$tenantId}_{$timestamp}.{$type}";
    }

    /**
     * Get full file path for export.
     */
    private function getFilePath(string $filename): string
    {
        $directory = 'exports';
        $fullPath = storage_path("app/{$directory}/{$filename}");
        
        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return $fullPath;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Export job failed permanently', [
            'job_id' => $this->exportJob->job_id,
            'error' => $exception->getMessage()
        ]);

        $this->exportJob->markAsFailed($exception->getMessage());
    }

    /**
     * Format page name for professional display.
     */
    private function formatPageName(string $pageUrl): string
    {
        if (empty($pageUrl)) {
            return 'Unknown Page';
        }

        $parsed = parse_url($pageUrl);
        if (!$parsed) {
            return 'Unknown Page';
        }

        $path = $parsed['path'] ?? '';

        // Format common pages professionally
        if (strpos($path, '/pricing') !== false) {
            return 'Pricing Page';
        } elseif (strpos($path, '/contact') !== false) {
            return 'Contact Page';
        } elseif (strpos($path, '/demo') !== false) {
            return 'Demo Request Page';
        } elseif (strpos($path, '/product') !== false) {
            return 'Product Page';
        } elseif (strpos($path, '/about') !== false) {
            return 'About Page';
        } elseif ($path === '/' || $path === '') {
            return 'Homepage';
        } else {
            return 'Page: ' . trim($path, '/');
        }
    }

    /**
     * Format action name for professional display.
     */
    private function formatActionName(string $eventName): string
    {
        $actionMap = [
            'page_view' => 'Page View',
            'form_submit' => 'Form Submission',
            'form_fill' => 'Form Fill',
            'download' => 'File Download',
            'pricing_view' => 'Pricing Page View',
            'contact_view' => 'Contact Page View',
            'demo_request' => 'Demo Request',
            'trial_signup' => 'Trial Signup',
            'email_open' => 'Email Open',
            'email_click' => 'Email Click',
            'video_watch' => 'Video Watch',
            'whitepaper_download' => 'Whitepaper Download',
            'case_study_view' => 'Case Study View',
            'product_tour' => 'Product Tour',
            'chat_start' => 'Live Chat Started',
            'webinar_register' => 'Webinar Registration',
            'newsletter_signup' => 'Newsletter Signup',
            'social_share' => 'Social Media Share',
            'search' => 'Site Search',
            'cart_add' => 'Add to Cart',
            'checkout_start' => 'Checkout Started',
            'purchase' => 'Purchase Made',
        ];

        return $actionMap[$eventName] ?? ucfirst(str_replace('_', ' ', $eventName));
    }

    /**
     * Format event type for professional display.
     */
    private function formatEventType(string $eventType): string
    {
        $typeMap = [
            'visitor_action' => 'Website Activity',
            'form_submission' => 'Form Activity',
            'email_engagement' => 'Email Activity',
            'campaign_interaction' => 'Campaign Activity',
        ];

        return $typeMap[$eventType] ?? ucfirst(str_replace('_', ' ', $eventType));
    }

    /**
     * Format source for professional display.
     */
    private function formatSource(string $source): string
    {
        $sourceMap = [
            'web_tracking' => 'Website',
            'email_campaign' => 'Email Campaign',
            'social_media' => 'Social Media',
            'paid_ads' => 'Paid Advertising',
            'organic_search' => 'Search Engine',
            'referral' => 'Referral Site',
        ];

        return $sourceMap[$source] ?? ucfirst(str_replace('_', ' ', $source));
    }

    /**
     * Format browser info for professional display.
     */
    private function formatBrowserInfo(string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown Browser';
        }

        // Extract browser name
        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Google Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Mozilla Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Microsoft Edge';
        } elseif (strpos($userAgent, 'Opera') !== false) {
            return 'Opera';
        } else {
            return 'Other Browser';
        }
    }
}