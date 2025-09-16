<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BulkOpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BulkOpsController extends Controller
{
    protected BulkOpsService $bulkOpsService;

    public function __construct(BulkOpsService $bulkOpsService)
    {
        $this->bulkOpsService = $bulkOpsService;
    }

    // ==================== FORMS ====================

    /**
     * Bulk delete forms.
     */
    public function bulkDeleteForms(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'form_ids' => 'required|array|min:1|max:20',
            'form_ids.*' => 'integer|exists:forms,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkDeleteForms($tenantId, $validated['form_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk delete operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk delete forms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk activate forms.
     */
    public function bulkActivateForms(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'form_ids' => 'required|array|min:1|max:20',
            'form_ids.*' => 'integer|exists:forms,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkActivateForms($tenantId, $validated['form_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk activate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk activate forms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk deactivate forms.
     */
    public function bulkDeactivateForms(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'form_ids' => 'required|array|min:1|max:20',
            'form_ids.*' => 'integer|exists:forms,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkDeactivateForms($tenantId, $validated['form_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk deactivate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk deactivate forms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== LISTS ====================

    /**
     * Bulk delete lists.
     */
    public function bulkDeleteLists(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'list_ids' => 'required|array|min:1|max:20',
            'list_ids.*' => 'integer|exists:lists,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkDeleteLists($tenantId, $validated['list_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk delete operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk delete lists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk activate lists.
     */
    public function bulkActivateLists(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'list_ids' => 'required|array|min:1|max:20',
            'list_ids.*' => 'integer|exists:lists,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkActivateLists($tenantId, $validated['list_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk activate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk activate lists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk deactivate lists.
     */
    public function bulkDeactivateLists(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'list_ids' => 'required|array|min:1|max:20',
            'list_ids.*' => 'integer|exists:lists,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkDeactivateLists($tenantId, $validated['list_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk deactivate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk deactivate lists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export lists.
     */
    public function exportLists(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
            'type' => ['sometimes', Rule::in(['static', 'dynamic'])],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->bulkOpsService->exportLists($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Lists exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export lists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import lists.
     */
    public function importLists(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,json|max:10240', // 10MB max
        ]);

        try {
            $result = $this->bulkOpsService->importLists($tenantId, $validated['file'], $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Lists imported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import lists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export single list.
     */
    public function exportSingleList(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->bulkOpsService->exportSingleList($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'List exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== EVENTS ====================

    /**
     * Bulk delete events.
     */
    public function bulkDeleteEvents(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'event_ids' => 'required|array|min:1|max:20',
            'event_ids.*' => 'integer|exists:events,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkDeleteEvents($tenantId, $validated['event_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk delete operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk delete events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk cancel events.
     */
    public function bulkCancelEvents(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'event_ids' => 'required|array|min:1|max:20',
            'event_ids.*' => 'integer|exists:events,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkCancelEvents($tenantId, $validated['event_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk cancel operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk cancel events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk activate events.
     */
    public function bulkActivateEvents(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'event_ids' => 'required|array|min:1|max:20',
            'event_ids.*' => 'integer|exists:events,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkActivateEvents($tenantId, $validated['event_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk activate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk activate events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export events.
     */
    public function exportEvents(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'type' => ['sometimes', Rule::in(['webinar', 'demo', 'workshop', 'conference', 'meeting'])],
            'is_active' => 'sometimes|boolean',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->bulkOpsService->exportEvents($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Events exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import events.
     */
    public function importEvents(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,json|max:10240', // 10MB max
        ]);

        try {
            $result = $this->bulkOpsService->importEvents($tenantId, $validated['file'], $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Events imported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export single event.
     */
    public function exportSingleEvent(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->bulkOpsService->exportSingleEvent($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Event exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel single event.
     */
    public function cancelEvent(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->bulkOpsService->cancelEvent($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Event cancelled successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reschedule single event.
     */
    public function rescheduleEvent(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        try {
            $result = $this->bulkOpsService->rescheduleEvent($tenantId, $id, $validated['scheduled_at']);

            return response()->json([
                'success' => true,
                'message' => 'Event rescheduled successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reschedule event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== MEETINGS ====================

    /**
     * Bulk delete meetings.
     */
    public function bulkDeleteMeetings(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'meeting_ids' => 'required|array|min:1|max:20',
            'meeting_ids.*' => 'integer|exists:meetings,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkDeleteMeetings($tenantId, $validated['meeting_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk delete operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk delete meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk cancel meetings.
     */
    public function bulkCancelMeetings(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'meeting_ids' => 'required|array|min:1|max:20',
            'meeting_ids.*' => 'integer|exists:meetings,id',
        ]);

        try {
            $result = $this->bulkOpsService->bulkCancelMeetings($tenantId, $validated['meeting_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk cancel operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk cancel meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk reschedule meetings.
     */
    public function bulkRescheduleMeetings(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'meeting_ids' => 'required|array|min:1|max:20',
            'meeting_ids.*' => 'integer|exists:meetings,id',
            'scheduled_at' => 'required|date|after:now',
        ]);

        try {
            $result = $this->bulkOpsService->bulkRescheduleMeetings($tenantId, $validated['meeting_ids'], $validated['scheduled_at']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk reschedule operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk reschedule meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export meetings.
     */
    public function exportMeetings(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'status' => ['sometimes', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'integration_provider' => ['sometimes', Rule::in(['google', 'outlook', 'zoom', 'teams', 'manual'])],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->bulkOpsService->exportMeetings($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Meetings exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import meetings.
     */
    public function importMeetings(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,json|max:10240', // 10MB max
        ]);

        try {
            $result = $this->bulkOpsService->importMeetings($tenantId, $validated['file'], $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Meetings imported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import meetings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export single meeting.
     */
    public function exportSingleMeeting(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->bulkOpsService->exportSingleMeeting($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Meeting exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export meeting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel single meeting.
     */
    public function cancelMeeting(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->bulkOpsService->cancelMeeting($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Meeting cancelled successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel meeting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reschedule single meeting.
     */
    public function rescheduleMeeting(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        try {
            $result = $this->bulkOpsService->rescheduleMeeting($tenantId, $id, $validated['scheduled_at']);

            return response()->json([
                'success' => true,
                'message' => 'Meeting rescheduled successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reschedule meeting',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

