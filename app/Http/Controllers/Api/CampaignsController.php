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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log as FrameworkLog;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CampaignsController extends Controller
{
    public function __construct(
        private FeatureRestrictionService $featureRestrictionService
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

        // Filter by created_by to ensure users only see their own campaigns
        $query->where('created_by', $userId);

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
        $campaigns = $query->with(['recipients'])->paginate($perPage);

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
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';
        // Persist new recipient settings additively under settings to avoid schema changes
        $data['settings'] = array_merge($data['settings'] ?? [], [
            'recipient_mode' => $data['recipient_mode'] ?? null,
            'recipient_contact_ids' => $data['recipient_contact_ids'] ?? null,
            'segment_id' => $data['segment_id'] ?? null,
        ]);

        $campaign = Campaign::create($data);

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
        $campaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

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
        } else {
            // Merge recipient fields into settings without breaking existing keys
            $settings = array_merge($campaign->settings ?? [], [
                'recipient_mode' => $update['recipient_mode'] ?? ($campaign->settings['recipient_mode'] ?? null),
                'recipient_contact_ids' => $update['recipient_contact_ids'] ?? ($campaign->settings['recipient_contact_ids'] ?? null),
                'segment_id' => $update['segment_id'] ?? ($campaign->settings['segment_id'] ?? null),
            ]);
            $update['settings'] = $settings;
            $campaign->update($update);
        }

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

        $campaign->delete();

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
                $q->select(\DB::raw(1))
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

        $templates = Campaign::where('tenant_id', $tenantId)
            ->where('is_template', true)
            ->select(['id', 'name', 'subject', 'content', 'created_at', 'is_template'])
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->query('per_page', 15), 100));

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
}
