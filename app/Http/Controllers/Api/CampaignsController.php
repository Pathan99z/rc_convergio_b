<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Campaigns\UpdateCampaignRequest;
use App\Http\Requests\Campaigns\SendCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\Contact;
use App\Services\FeatureRestrictionService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log as FrameworkLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CampaignsController extends Controller
{
    public function __construct(
        private FeatureRestrictionService $featureRestrictionService,
        private TeamAccessService $teamAccessService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $userId = $request->user()->id;

        $query = Campaign::query()->where('tenant_id', $tenantId);

        // ✅ FIX: Apply team filtering instead of created_by filtering
        $this->teamAccessService->applyTeamFilter($query);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($from = $request->query('created_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('created_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sort = (string) $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column, $direction);

        $perPage = min((int) $request->query('per_page', 15), 100);
        
        // Create cache key for this specific query with tenant and user isolation
        $cacheKey = "campaigns_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        // Cache campaigns list for 5 minutes (300 seconds)
        $campaigns = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->with(['recipients'])->paginate($perPage);
        });

        return response()->json([
            'data' => CampaignResource::collection($campaigns->items()),
            'meta' => [
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'per_page' => $campaigns->perPage(),
                'total' => $campaigns->total(),
                'from' => $campaigns->firstItem(),
                'to' => $campaigns->lastItem(),
            ],
        ]);
    }

    /**
     * Create a campaign from a template
     */
    public function createFromTemplate(Request $request, int $templateId): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }

        // Find the template
        $template = \App\Models\CampaignTemplate::where('id', $templateId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->firstOrFail();

        // Create campaign from template
        $campaign = Campaign::create([
            'name' => $template->name . ' (Copy)',
            'description' => $template->description,
            'subject' => $template->subject,
            'content' => $template->content,
            'type' => $template->type,
            'status' => 'draft',
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
            'settings' => [],
        ]);

        // Increment template usage count
        $template->increment('usage_count');

        // Clear cache after creating campaign
        $this->clearCampaignsCache($tenantId, $request->user()->id);

        return response()->json([
            'message' => 'Campaign created from template successfully',
            'data' => new CampaignResource($campaign->load(['recipients'])),
        ], 201);
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $data = $request->validated();
        
        // If this is a template, validate that description is required
        if (isset($data['is_template']) && $data['is_template']) {
            if (empty($data['description'])) {
                return response()->json([
                    'message' => 'The description field is required for templates.',
                    'errors' => [
                        'description' => ['The description field is required for templates.']
                    ]
                ], 422);
            }
        }
        
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = $request->user()->id;
        
        // If scheduled_at is provided and in the future, automatically set status to 'scheduled'
        // Otherwise, keep it as 'draft'
        if (isset($data['scheduled_at']) && $data['scheduled_at']) {
            $scheduleAt = Carbon::parse($data['scheduled_at']);
            if ($scheduleAt->isFuture()) {
                $data['status'] = 'scheduled';
            } else {
                $data['status'] = 'draft';
            }
        } else {
            $data['status'] = 'draft';
        }
        
        // Handle CSV file upload if CSV mode is selected
        $csvFile = null;
        if (isset($data['recipient_mode']) && $data['recipient_mode'] === 'csv' && $request->hasFile('csv_file')) {
            $csvFile = $request->file('csv_file');
            // Validate CSV mode requires CSV file
            if (!$csvFile) {
                return response()->json([
                    'message' => 'CSV file is required when recipient mode is csv',
                    'errors' => [
                        'csv_file' => ['CSV file is required when recipient mode is csv']
                    ]
                ], 422);
            }
        }

        // Persist new recipient settings additively under settings to avoid schema changes
        $data['settings'] = array_merge($data['settings'] ?? [], [
            'recipient_mode' => $data['recipient_mode'] ?? null,
            'recipient_contact_ids' => $data['recipient_contact_ids'] ?? null,
            'segment_id' => $data['segment_id'] ?? null,
        ]);

        // If this is a template, save ONLY to campaign_templates table (not campaigns)
        if (isset($data['is_template']) && $data['is_template']) {
            // Log what we're receiving for debugging
            Log::info('Template creation data received', [
                'name' => $data['name'] ?? 'NOT_SET',
                'description' => $data['description'] ?? 'NOT_SET',
                'subject' => $data['subject'] ?? 'NOT_SET',
                'content' => $data['content'] ?? 'NOT_SET',
                'all_data' => $data
            ]);
            
            $template = \App\Models\CampaignTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'subject' => $data['subject'],
                'content' => $data['content'],
                'type' => $data['type'] ?? 'email',
                'category' => 'custom',
                'is_active' => true,
                'is_public' => false,
                'usage_count' => 0,
                'tenant_id' => $tenantId,
                'created_by' => $request->user()->id,
            ]);

            // Clear cache after creating template
            $this->clearCampaignsCache($tenantId, $request->user()->id);

            return response()->json([
                'message' => 'Template saved successfully',
                'data' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'subject' => $template->subject,
                    'content' => $template->content,
                    'type' => $template->type,
                    'is_template' => true,
                ]
            ], 201);
        }

        // If this is a regular campaign, save to campaigns table
        $campaign = Campaign::create($data);
        
        // Handle CSV file upload after campaign is created (need campaign ID)
        if ($csvFile && isset($data['recipient_mode']) && $data['recipient_mode'] === 'csv') {
            $csvService = new \App\Services\CampaignCsvService();
            $csvFilePath = $csvService->saveCsvFile($csvFile, $campaign->id);
            
            // Update campaign settings with CSV file path
            $settings = $campaign->settings ?? [];
            $settings['csv_file_path'] = $csvFilePath;
            $settings['csv_uploaded_at'] = now()->toISOString();
            $campaign->update(['settings' => $settings]);
            
            Log::info('CSV file saved for campaign', [
                'campaign_id' => $campaign->id,
                'csv_file_path' => $csvFilePath
            ]);
        }

        // If campaign was created with scheduled_at in the future, automatically schedule it
        if ($campaign->status === 'scheduled' && $campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
            // FREEZE AUDIENCE: Resolve and save contacts at the moment of scheduling
            $this->freezeCampaignAudience($campaign);
            
            // Check queue connection - if sync, don't dispatch (let scheduled command handle it)
            // If async (database/redis), dispatch with delay
            $queue = config('queue.default');
            if ($queue !== 'sync') {
                // Dispatch the sending job with delay for async queues
                Bus::chain([
                    new \App\Jobs\SendCampaignEmails($campaign->id),
                ])->delay($campaign->scheduled_at)->dispatch();
                
                FrameworkLog::info('Campaign auto-scheduled on creation (async queue)', [
                    'campaign_id' => $campaign->id,
                    'scheduled_at' => $campaign->scheduled_at->toIso8601String(),
                    'queue' => $queue,
                ]);
            } else {
                // For sync queue, just mark as scheduled - ProcessScheduledCampaigns command will handle it
                FrameworkLog::info('Campaign auto-scheduled on creation (sync queue - will be processed by scheduled command)', [
                    'campaign_id' => $campaign->id,
                    'scheduled_at' => $campaign->scheduled_at->toIso8601String(),
                    'queue' => $queue,
                ]);
            }
        }

        // Clear cache after creating campaign
        $this->clearCampaignsCache($tenantId, $request->user()->id);

        return response()->json([
            'data' => new CampaignResource($campaign->load(['recipients'])),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $userId = $request->user()->id;
        
        // Create cache key with tenant, user, and campaign ID isolation
        $cacheKey = "campaign_show_{$tenantId}_{$userId}_{$id}";
        
        // Cache campaign detail for 15 minutes (900 seconds)
        $campaign = Cache::remember($cacheKey, 900, function () use ($tenantId, $id) {
            return Campaign::where('tenant_id', $tenantId)->findOrFail($id);
        });

        $this->authorize('view', $campaign);

        return response()->json([
            'data' => new CampaignResource($campaign->load(['recipients'])),
        ]);
    }

    public function update(UpdateCampaignRequest $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $campaign);

        $update = $request->validated();
        // If the request intends to only toggle is_template via PATCH, allow partial update
        if ($request->isMethod('patch') && array_keys($update) === ['is_template']) {
            $campaign->update(['is_template' => (bool) $update['is_template']]);
            
            // Handle template creation/deletion in campaign_templates table
            if ($update['is_template']) {
                // Create template in campaign_templates table
                \App\Models\CampaignTemplate::updateOrCreate(
                    ['name' => $campaign->name, 'tenant_id' => $campaign->tenant_id],
                    [
                        'description' => $campaign->description,
                        'subject' => $campaign->subject,
                        'content' => $campaign->content,
                        'type' => $campaign->type ?? 'email',
                        'category' => 'custom',
                        'is_active' => true,
                        'is_public' => false,
                        'created_by' => $campaign->created_by,
                    ]
                );
            } else {
                // Remove template from campaign_templates table
                \App\Models\CampaignTemplate::where('name', $campaign->name)
                    ->where('tenant_id', $campaign->tenant_id)
                    ->delete();
            }
        } else {
            // Merge recipient fields into settings without breaking existing keys
            $settings = array_merge($campaign->settings ?? [], [
                'recipient_mode' => $update['recipient_mode'] ?? ($campaign->settings['recipient_mode'] ?? null),
                'recipient_contact_ids' => $update['recipient_contact_ids'] ?? ($campaign->settings['recipient_contact_ids'] ?? null),
                'segment_id' => $update['segment_id'] ?? ($campaign->settings['segment_id'] ?? null),
            ]);
            $update['settings'] = $settings;
            $campaign->update($update);
            
            // If this is a template, also update it in campaign_templates table
            if ($campaign->is_template) {
                \App\Models\CampaignTemplate::updateOrCreate(
                    ['name' => $campaign->name, 'tenant_id' => $campaign->tenant_id],
                    [
                        'description' => $campaign->description,
                        'subject' => $campaign->subject,
                        'content' => $campaign->content,
                        'type' => $campaign->type ?? 'email',
                        'category' => 'custom',
                        'is_active' => true,
                        'is_public' => false,
                        'created_by' => $campaign->created_by,
                    ]
                );
            }
        }

        // Clear cache after updating campaign
        $this->clearCampaignsCache($tenantId, $request->user()->id);
        Cache::forget("campaign_show_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'data' => new CampaignResource($campaign->load(['recipients'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('delete', $campaign);

        // Guard: allow deletion here only for templates
        if (!$campaign->is_template) {
            return response()->json([
                'message' => 'Only templates can be deleted via this view.'
            ], 422);
        }

        $userId = $request->user()->id;
        $campaign->delete();

        // Clear cache after deleting campaign
        $this->clearCampaignsCache($tenantId, $userId);
        Cache::forget("campaign_show_{$tenantId}_{$userId}_{$id}");

        return response()->json(['message' => 'Campaign deleted successfully']);
    }

    public function send(SendCampaignRequest $request, int $id): JsonResponse
    {
        // Check feature restriction for campaign sending
        if (!$this->featureRestrictionService->canSendCampaigns($request->user())) {
            abort(403, $this->featureRestrictionService->getRestrictionMessage('campaign_sending'));
        }

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('send', $campaign);

        $data = $request->validated();
        $scheduleAt = isset($data['schedule_at']) && $data['schedule_at']
            ? Carbon::parse($data['schedule_at'])
            : null;

        FrameworkLog::info('Campaign send requested', [
            'campaign_id' => $campaign->id,
            'tenant_id' => $campaign->tenant_id,
            'schedule_at' => $scheduleAt?->toIso8601String(),
            'queue' => config('queue.default'),
        ]);

        // FREEZE AUDIENCE: Resolve and save contacts at the moment of scheduling/sending
        $this->freezeCampaignAudience($campaign);

        if ($scheduleAt) {
            $campaign->update([
                'status' => 'scheduled',
                'scheduled_at' => $scheduleAt,
            ]);
            Bus::chain([
                new \App\Jobs\SendCampaignEmails($campaign->id),
            ])->delay($scheduleAt)->dispatch();
        } else {
            $campaign->update(['status' => 'sending']);
            Bus::chain([
                new \App\Jobs\SendCampaignEmails($campaign->id),
            ])->dispatch();

            // Only execute inline if queue is sync mode (not async)
            $queue = config('queue.default');
            if ($queue === 'sync') {
                FrameworkLog::info('Queue sync mode: executing campaign inline', ['campaign_id' => $campaign->id]);
                
                try {
                    // Execute sending job inline (audience already frozen)
                    $sendJob = new \App\Jobs\SendCampaignEmails($campaign->id);
                    $sendJob->handle();
                    
                    FrameworkLog::info('Campaign executed inline successfully', ['campaign_id' => $campaign->id]);
                } catch (\Throwable $e) {
                    FrameworkLog::error('Inline campaign execution failed', [
                        'campaign_id' => $campaign->id,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                FrameworkLog::info('Campaign queued for async processing', ['campaign_id' => $campaign->id, 'queue' => $queue]);
            }
        }

        // Clear cache after sending/scheduling campaign
        $this->clearCampaignsCache($campaign->tenant_id, $request->user()->id);
        Cache::forget("campaign_show_{$campaign->tenant_id}_{$request->user()->id}_{$id}");

        return response()->json([
            'data' => new CampaignResource($campaign->load(['recipients'])),
            'message' => $scheduleAt ? 'Campaign scheduled successfully' : 'Campaign queued for sending',
        ]);
    }

    /**
     * FREEZE AUDIENCE: Resolve and save contacts at the moment of scheduling/sending
     * This prevents dynamic segments from changing the campaign audience later
     */
    private function freezeCampaignAudience(Campaign $campaign): void
    {
        $settings = $campaign->settings ?? [];
        $mode = $settings['recipient_mode'] ?? null;
        $contactIds = $settings['recipient_contact_ids'] ?? [];
        $segmentId = $settings['segment_id'] ?? null;
        $tenantId = $campaign->tenant_id;

        FrameworkLog::info('Freezing campaign audience', [
            'campaign_id' => $campaign->id,
            'mode' => $mode,
            'contact_ids_count' => count($contactIds),
            'segment_id' => $segmentId
        ]);

        // Log campaign audience freezing event
        \App\Models\AuditLog::log('audience_frozen', [
            'campaign_id' => $campaign->id,
            'metadata' => [
                'mode' => $mode,
                'contact_ids_count' => count($contactIds),
                'segment_id' => $segmentId,
                'tenant_id' => $tenantId
            ]
        ]);

        // Clear existing recipients to ensure clean state
        DB::table('campaign_recipients')->where('campaign_id', $campaign->id)->delete();

        $query = Contact::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('email')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('contact_subscriptions')
                  ->whereColumn('contact_subscriptions.contact_id', 'contacts.id')
                  ->where('contact_subscriptions.unsubscribed', true);
            }); // SUPPRESSION: Exclude unsubscribed contacts

        if ($mode === 'segment' && $segmentId) {
            // Resolve dynamic segment at this moment and freeze it
            $query->whereIn('id', function ($q) use ($segmentId) {
                $q->select('contact_id')->from('list_members')->where('list_id', $segmentId);
            });
            
            // Store the resolved contact IDs for traceability
            $resolvedContactIds = $query->pluck('id')->toArray();
            $campaign->update([
                'settings' => array_merge($settings, [
                    'frozen_contact_ids' => $resolvedContactIds,
                    'frozen_at' => now()->toISOString(),
                    'original_segment_id' => $segmentId
                ])
            ]);
            
        } elseif (in_array($mode, ['manual', 'static'], true) && !empty($contactIds)) {
            // Use manually selected contacts
            $query->whereIn('id', $contactIds);
            
            $campaign->update([
                'settings' => array_merge($settings, [
                    'frozen_contact_ids' => $contactIds,
                    'frozen_at' => now()->toISOString()
                ])
            ]);
        } elseif ($mode === 'csv') {
            // CSV mode: Parse CSV and count emails (no campaign_recipients created)
            $csvFilePath = $settings['csv_file_path'] ?? null;
            
            if (!$csvFilePath) {
                FrameworkLog::warning('CSV mode selected but no CSV file path found', [
                    'campaign_id' => $campaign->id
                ]);
                return;
            }
            
            $fullCsvPath = storage_path('app/' . $csvFilePath);
            
            if (!file_exists($fullCsvPath)) {
                FrameworkLog::error('CSV file not found for campaign', [
                    'campaign_id' => $campaign->id,
                    'csv_path' => $fullCsvPath
                ]);
                return;
            }
            
            // Parse CSV to count emails
            $csvService = new \App\Services\CampaignCsvService();
            $emailCount = $csvService->countEmailsInCsv($fullCsvPath);
            
            // Store email count in settings (for display purposes)
            $campaign->update([
                'settings' => array_merge($settings, [
                    'csv_email_count' => $emailCount,
                    'frozen_at' => now()->toISOString()
                ])
            ]);
            
            FrameworkLog::info('CSV campaign audience frozen', [
                'campaign_id' => $campaign->id,
                'email_count' => $emailCount
            ]);
            
            // CSV mode doesn't create campaign_recipients records
            // Emails will be sent directly from CSV file in SendCampaignEmails job
            return;
        } else {
            FrameworkLog::warning('No valid recipient mode found for campaign', [
                'campaign_id' => $campaign->id,
                'mode' => $mode
            ]);
            return;
        }

        // Bulk insert frozen audience into campaign_recipients
        $now = now();
        $batch = [];
        $hasTenantColumn = Schema::hasColumn('campaign_recipients', 'tenant_id');
        $hasContactIdColumn = Schema::hasColumn('campaign_recipients', 'contact_id');

        $query->chunkById(500, function ($contacts) use (&$batch, $campaign, $now, $hasTenantColumn, $hasContactIdColumn) {
            $batch = [];
            foreach ($contacts as $contact) {
                $name = trim(($contact->first_name ?: '') . ' ' . ($contact->last_name ?: '')) ?: null;
                $batch[] = [
                    'campaign_id' => $campaign->id,
                    'contact_id' => $hasContactIdColumn ? $contact->id : null,
                    'email' => $contact->email,
                    'name' => $name,
                    'status' => 'pending',
                    'tenant_id' => $hasTenantColumn ? $campaign->tenant_id : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($batch)) {
                DB::table('campaign_recipients')->insert($batch);
            }
        });

        // Update campaign with frozen recipient count
        $totalRecipients = DB::table('campaign_recipients')->where('campaign_id', $campaign->id)->count();
        $campaign->update(['total_recipients' => $totalRecipients]);

        // Count suppressed contacts for audit
        $originalCount = $query->count();
        $suppressedCount = $originalCount - $totalRecipients;

        FrameworkLog::info('Campaign audience frozen successfully', [
            'campaign_id' => $campaign->id,
            'total_recipients' => $totalRecipients,
            'original_count' => $originalCount,
            'suppressed_count' => $suppressedCount,
            'mode' => $mode
        ]);

        // Log suppression results
        if ($suppressedCount > 0) {
            \App\Models\AuditLog::log('contacts_suppressed', [
                'campaign_id' => $campaign->id,
                'metadata' => [
                    'original_count' => $originalCount,
                    'suppressed_count' => $suppressedCount,
                    'final_count' => $totalRecipients,
                    'suppression_reasons' => ['unsubscribed', 'bounced']
                ]
            ]);
        }
    }

    /**
     * Check if queue worker is running
     */
    private function isQueueWorkerRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $processes = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV | findstr "queue:work"');
            return !empty($processes);
        } else {
            $processes = shell_exec('ps aux | grep "queue:work" | grep -v grep');
            return !empty($processes);
        }
    }

    public function metrics(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $campaign);

        // Recompute base counts live to reflect latest recipient statuses
        $live = DB::table('campaign_recipients')->selectRaw(
            "SUM(status='sent') as sent, SUM(status='bounced') as bounced, SUM(opened_at IS NOT NULL) as opened, SUM(clicked_at IS NOT NULL) as clicked, SUM(delivered_at IS NOT NULL) as delivered, COUNT(*) as total"
        )->where('campaign_id', $campaign->id)->first();

        $sent = (int) ($live->sent ?? 0);
        $bounced = (int) ($live->bounced ?? 0);
        $opened = (int) ($live->opened ?? 0);
        $clicked = (int) ($live->clicked ?? 0);
        $delivered = (int) ($live->delivered ?? 0);
        $total = (int) ($live->total ?? 0);

        $metrics = [
            'sent_count' => $sent,
            'delivered_count' => $delivered,
            'opened_count' => $opened,
            'clicked_count' => $clicked,
            'bounced_count' => $bounced,
            'open_percentage' => $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0,
            'click_percentage' => $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0,
            'bounce_percentage' => $total > 0 ? round(($bounced / $total) * 100, 2) : 0,
        ];

        return response()->json([
            'data' => $metrics,
            'meta' => [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'status' => $campaign->status,
                'sent_at' => $campaign->sent_at?->toISOString(),
            ],
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $campaign);

        if (!in_array($campaign->status, ['active', 'sending'])) {
            return response()->json([
                'error' => 'Campaign cannot be paused in its current status',
            ], 422);
        }

        $campaign->update(['status' => 'paused']);

        // Clear cache after pausing campaign
        $this->clearCampaignsCache($tenantId, $request->user()->id);
        Cache::forget("campaign_show_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'data' => new CampaignResource($campaign->load(['recipients'])),
            'message' => 'Campaign paused successfully',
        ]);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $campaign);

        if ($campaign->status !== 'paused') {
            return response()->json([
                'error' => 'Campaign is not paused',
            ], 422);
        }

        $campaign->update(['status' => 'active']);

        // Clear cache after resuming campaign
        $this->clearCampaignsCache($tenantId, $request->user()->id);
        Cache::forget("campaign_show_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'data' => new CampaignResource($campaign->load(['recipients'])),
            'message' => 'Campaign resumed successfully',
        ]);
    }

    public function recipients(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $campaign);

        $recipients = $campaign->recipients()
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json([
            'data' => $recipients->items(),
            'meta' => [
                'current_page' => $recipients->currentPage(),
                'last_page' => $recipients->lastPage(),
                'per_page' => $recipients->perPage(),
                'total' => $recipients->total(),
                'campaign_id' => $campaign->id,
            ],
        ]);
    }

    public function addRecipients(Request $request, int $id): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $campaign);

        $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*.email' => 'required|email',
            'recipients.*.name' => 'nullable|string|max:255',
        ]);

        $recipients = collect($request->recipients)->map(function ($recipient) use ($campaign) {
            return [
                'campaign_id' => $campaign->id,
                'email' => $recipient['email'],
                'name' => $recipient['name'] ?? null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        DB::table('campaign_recipients')->insert($recipients);

        return response()->json([
            'message' => 'Recipients added successfully',
            'added_count' => count($recipients),
        ]);
    }

    public function removeRecipients(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->header('X-Tenant-ID');
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $campaign);

        $request->validate([
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'integer|exists:campaign_recipients,id',
        ]);

        $deleted = DB::table('campaign_recipients')
            ->where('campaign_id', $campaign->id)
            ->whereIn('id', $request->recipient_ids)
            ->delete();

        return response()->json([
            'message' => 'Recipients removed successfully',
            'removed_count' => $deleted,
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        // Use authenticated tenant scoping consistently
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }

        $userId = $request->user()->id;
        
        // Create cache key for templates with tenant and user isolation
        $cacheKey = "campaigns_templates_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        // Cache templates list for 5 minutes (300 seconds)
        $templates = Cache::remember($cacheKey, 300, function () use ($tenantId, $request) {
            return \App\Models\CampaignTemplate::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->select(['id', 'name', 'description', 'subject', 'content', 'type', 'created_at', 'usage_count'])
                ->orderBy('created_at', 'desc')
                ->paginate(min((int) $request->query('per_page', 15), 100));
        });

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        // Enforce tenant scoping via authenticated user
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        try {
            $originalCampaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Campaign not found for your account',
            ], 404);
        }

        $this->authorize('create', Campaign::class);

        // Create new campaign with copied data
        $newCampaign = $originalCampaign->replicate();
        $newCampaign->name = $originalCampaign->name . ' (Copy)';
        $newCampaign->status = 'draft';
        $newCampaign->created_by = $request->user()->id;
        $newCampaign->sent_at = null;
        $newCampaign->scheduled_at = null;
        $newCampaign->save();

        // Do not copy recipients for duplicated campaigns (start fresh)

        // Clear cache after duplicating campaign
        $this->clearCampaignsCache($tenantId, $request->user()->id);

        return response()->json([
            'data' => new CampaignResource($newCampaign->load(['recipients'])),
            'message' => 'Campaign duplicated successfully',
        ], 201);
    }

    /**
     * Create an ad campaign.
     */
    public function createAd(Request $request, int $campaignId): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Verify campaign exists and belongs to tenant
        $campaign = Campaign::where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'ad_account_id' => 'required|exists:ad_accounts,id',
            'ad_settings' => 'required|array',
            'ad_settings.budget' => 'required|numeric|min:1',
            'ad_settings.duration_days' => 'required|integer|min:1|max:365',
            'ad_settings.targeting' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            // Update campaign to be an ad campaign
            $campaign->update([
                'type' => 'ad',
                'settings' => array_merge($campaign->settings ?? [], [
                    'ad_account_id' => $validated['ad_account_id'],
                    'ad_settings' => $validated['ad_settings']
                ])
            ]);

            DB::commit();

            // Clear cache after creating ad campaign
            $this->clearCampaignsCache($tenantId, $user->id);
            Cache::forget("campaign_show_{$tenantId}_{$user->id}_{$campaignId}");

            return response()->json([
                'data' => new CampaignResource($campaign),
                'message' => 'Ad campaign created successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create ad campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ad campaign metrics.
     */
    public function getAdMetrics(Request $request, int $campaignId): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Verify campaign exists and belongs to tenant
        $campaign = Campaign::where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'ad')
            ->firstOrFail();

        try {
            // Get ad account from campaign settings
            $adAccountId = $campaign->settings['ad_account_id'] ?? null;
            if (!$adAccountId) {
                return response()->json([
                    'message' => 'No ad account associated with this campaign'
                ], 400);
            }

            // Get ad account
            $adAccount = \App\Models\AdAccount::where('id', $adAccountId)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Simulate fetching metrics from ad provider
            // In a real implementation, this would call the actual ad provider API
            $metrics = $this->fetchAdMetricsFromProvider($adAccount, $campaign);

            return response()->json([
                'data' => [
                    'campaign_id' => $campaignId,
                    'provider' => $adAccount->provider,
                    'impressions' => $metrics['impressions'],
                    'clicks' => $metrics['clicks'],
                    'conversions' => $metrics['conversions'],
                    'roi' => $metrics['roi'],
                    'spend' => $metrics['spend'],
                    'ctr' => $metrics['ctr'],
                    'cpc' => $metrics['cpc'],
                    'updated_at' => now()->toISOString()
                ],
                'message' => 'Ad campaign metrics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch ad campaign metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch ad metrics from provider (simulated).
     */
    private function fetchAdMetricsFromProvider($adAccount, $campaign): array
    {
        // This is a simulation - in real implementation, you would call the actual ad provider API
        // using the credentials stored in $adAccount->credentials
        
        $baseImpressions = rand(500, 5000);
        $baseClicks = rand(50, 500);
        $baseConversions = rand(5, 50);
        
        // Simulate different performance based on provider
        switch ($adAccount->provider) {
            case 'linkedin':
                $impressions = $baseImpressions * 1.2;
                $clicks = $baseClicks * 1.1;
                $conversions = $baseConversions * 1.3;
                break;
            case 'google':
                $impressions = $baseImpressions * 1.5;
                $clicks = $baseClicks * 1.2;
                $conversions = $baseConversions * 1.1;
                break;
            case 'facebook':
                $impressions = $baseImpressions * 1.8;
                $clicks = $baseClicks * 1.4;
                $conversions = $baseConversions * 1.0;
                break;
            default:
                $impressions = $baseImpressions;
                $clicks = $baseClicks;
                $conversions = $baseConversions;
        }

        $spend = rand(100, 2000); // Simulated spend
        $roi = $conversions > 0 ? round(($conversions / $clicks) * 100, 1) . '%' : '0%';
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) . '%' : '0%';
        $cpc = $clicks > 0 ? round($spend / $clicks, 2) : 0;

        return [
            'impressions' => (int) $impressions,
            'clicks' => (int) $clicks,
            'conversions' => (int) $conversions,
            'roi' => $roi,
            'spend' => $spend,
            'ctr' => $ctr,
            'cpc' => $cpc,
        ];
    }

    /**
     * Clear campaigns cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearCampaignsCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for campaigns list
            $commonParams = [
                '',
                md5(serialize(['sort' => '-created_at', 'page' => 1, 'per_page' => 15])),
                md5(serialize(['sort' => '-updated_at', 'page' => 1, 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("campaigns_list_{$tenantId}_{$userId}_{$params}");
            }

            // Clear templates cache
            Cache::forget("campaigns_templates_{$tenantId}_{$userId}_" . md5(serialize([])));

            Log::info('Campaigns cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams) + 1
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear campaigns cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
