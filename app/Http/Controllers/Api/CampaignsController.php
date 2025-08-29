<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Campaigns\UpdateCampaignRequest;
use App\Http\Requests\Campaigns\SendCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = (int) $request->header('X-Tenant-ID');
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
        $tenantId = (int) $request->header('X-Tenant-ID');
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

        $campaign = Campaign::create($data);

        return response()->json([
            'data' => new CampaignResource($campaign->load(['recipients'])),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = (int) $request->header('X-Tenant-ID');
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
        $tenantId = (int) $request->header('X-Tenant-ID');
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

        $campaign->update($request->validated());

        return response()->json([
            'data' => new CampaignResource($campaign->load(['recipients'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = (int) $request->header('X-Tenant-ID');
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

        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted successfully']);
    }

    public function send(SendCampaignRequest $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = (int) $request->header('X-Tenant-ID');
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
        $scheduleAt = $data['schedule_at'] ?? null;

        if ($scheduleAt) {
            $campaign->update([
                'status' => 'scheduled',
                'scheduled_at' => $scheduleAt,
            ]);

            SendCampaignJob::dispatch($campaign)->delay($scheduleAt);
        } else {
            $campaign->update(['status' => 'sending']);
            SendCampaignJob::dispatch($campaign);
        }

        return response()->json([
            'data' => new CampaignResource($campaign->load(['recipients'])),
            'message' => $scheduleAt ? 'Campaign scheduled successfully' : 'Campaign queued for sending',
        ]);
    }

    public function metrics(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = (int) $request->header('X-Tenant-ID');
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

        $metrics = [
            'total_recipients' => $campaign->total_recipients,
            'sent_count' => $campaign->sent_count,
            'delivered_count' => $campaign->delivered_count,
            'opened_count' => $campaign->opened_count,
            'clicked_count' => $campaign->clicked_count,
            'bounced_count' => $campaign->bounced_count,
            'delivery_rate' => $campaign->total_recipients > 0 ? 
                round(($campaign->delivered_count / $campaign->total_recipients) * 100, 2) : 0,
            'open_rate' => $campaign->delivered_count > 0 ? 
                round(($campaign->opened_count / $campaign->delivered_count) * 100, 2) : 0,
            'click_rate' => $campaign->delivered_count > 0 ? 
                round(($campaign->clicked_count / $campaign->delivered_count) * 100, 2) : 0,
            'bounce_rate' => $campaign->total_recipients > 0 ? 
                round(($campaign->bounced_count / $campaign->total_recipients) * 100, 2) : 0,
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

        $tenantId = (int) $request->header('X-Tenant-ID');
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }

        $templates = Campaign::where('tenant_id', $tenantId)
            ->where('is_template', true)
            ->select(['id', 'name', 'subject', 'content', 'created_at'])
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
        $tenantId = (int) $request->header('X-Tenant-ID');
        if ($tenantId === 0) {
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4;
            } else {
                $tenantId = 1;
            }
        }
        $originalCampaign = Campaign::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('create', Campaign::class);

        // Create new campaign with copied data
        $newCampaign = $originalCampaign->replicate();
        $newCampaign->name = $originalCampaign->name . ' (Copy)';
        $newCampaign->status = 'draft';
        $newCampaign->created_by = $request->user()->id;
        $newCampaign->sent_at = null;
        $newCampaign->scheduled_at = null;
        $newCampaign->save();

        // Copy recipients
        $recipients = DB::table('campaign_recipients')
            ->where('campaign_id', $originalCampaign->id)
            ->get()
            ->map(function ($recipient) use ($newCampaign) {
                return [
                    'campaign_id' => $newCampaign->id,
                    'email' => $recipient->email,
                    'name' => $recipient->name,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

        if (!empty($recipients)) {
            DB::table('campaign_recipients')->insert($recipients);
        }

        return response()->json([
            'data' => new CampaignResource($newCampaign->load(['recipients'])),
            'message' => 'Campaign duplicated successfully',
        ], 201);
    }
}
