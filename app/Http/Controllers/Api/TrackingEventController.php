<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntentEvent;
use App\Models\VisitorIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrackingEventController extends Controller
{
    /**
     * Get specific intent event details.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            // Try to find in intent_events first
            $intentEvent = IntentEvent::where('tenant_id', $tenantId)->find($id);
            
            if ($intentEvent) {
                $intentEvent->load(['company:id,name', 'contact:id,first_name,last_name,email']);
                
                return response()->json([
                    'data' => [
                        'id' => $intentEvent->id,
                        'event_type' => $intentEvent->event_type,
                        'event_name' => $intentEvent->event_name,
                        'intent_score' => $intentEvent->intent_score,
                        'source' => $intentEvent->source,
                        'session_id' => $intentEvent->session_id,
                        'company' => $intentEvent->company,
                        'contact' => $intentEvent->contact,
                        'metadata' => $intentEvent->metadata,
                        'created_at' => $intentEvent->created_at,
                        'updated_at' => $intentEvent->updated_at,
                    ],
                    'message' => 'Intent event retrieved successfully'
                ]);
            }

            // Try to find in visitor_intents
            $visitorIntent = VisitorIntent::where('tenant_id', $tenantId)->find($id);
            
            if ($visitorIntent) {
                $visitorIntent->load(['company:id,name', 'contact:id,first_name,last_name,email']);
                
                return response()->json([
                    'data' => [
                        'id' => $visitorIntent->id,
                        'event_type' => 'visitor_intent',
                        'action' => $visitorIntent->action,
                        'intent_score' => $visitorIntent->score,
                        'page_url' => $visitorIntent->page_url,
                        'duration_seconds' => $visitorIntent->duration_seconds,
                        'session_id' => $visitorIntent->session_id,
                        'company' => $visitorIntent->company,
                        'contact' => $visitorIntent->contact,
                        'metadata' => $visitorIntent->metadata,
                        'created_at' => $visitorIntent->created_at,
                        'updated_at' => $visitorIntent->updated_at,
                    ],
                    'message' => 'Visitor intent retrieved successfully'
                ]);
            }

            return response()->json([
                'message' => 'Intent event not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve intent event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update intent event.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'intent_score' => 'sometimes|integer|min:0|max:100',
            'metadata' => 'sometimes|array',
            'event_name' => 'sometimes|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Try to update intent_events first
            $intentEvent = IntentEvent::where('tenant_id', $tenantId)->find($id);
            
            if ($intentEvent) {
                $intentEvent->update($validated);
                $intentEvent->load(['company:id,name', 'contact:id,first_name,last_name,email']);
                
                DB::commit();
                
                return response()->json([
                    'data' => [
                        'id' => $intentEvent->id,
                        'event_type' => $intentEvent->event_type,
                        'event_name' => $intentEvent->event_name,
                        'intent_score' => $intentEvent->intent_score,
                        'source' => $intentEvent->source,
                        'company' => $intentEvent->company,
                        'contact' => $intentEvent->contact,
                        'metadata' => $intentEvent->metadata,
                        'updated_at' => $intentEvent->updated_at,
                    ],
                    'message' => 'Intent event updated successfully'
                ]);
            }

            // Try to update visitor_intents
            $visitorIntent = VisitorIntent::where('tenant_id', $tenantId)->find($id);
            
            if ($visitorIntent) {
                $updateData = [];
                if (isset($validated['intent_score'])) {
                    $updateData['score'] = $validated['intent_score'];
                }
                if (isset($validated['metadata'])) {
                    $updateData['metadata'] = $validated['metadata'];
                }
                
                $visitorIntent->update($updateData);
                $visitorIntent->load(['company:id,name', 'contact:id,first_name,last_name,email']);
                
                DB::commit();
                
                return response()->json([
                    'data' => [
                        'id' => $visitorIntent->id,
                        'action' => $visitorIntent->action,
                        'intent_score' => $visitorIntent->score,
                        'page_url' => $visitorIntent->page_url,
                        'company' => $visitorIntent->company,
                        'contact' => $visitorIntent->contact,
                        'metadata' => $visitorIntent->metadata,
                        'updated_at' => $visitorIntent->updated_at,
                    ],
                    'message' => 'Visitor intent updated successfully'
                ]);
            }

            DB::rollBack();
            return response()->json([
                'message' => 'Intent event not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update intent event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete intent event.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            DB::beginTransaction();

            // Try to delete from intent_events first
            $intentEvent = IntentEvent::where('tenant_id', $tenantId)->find($id);
            
            if ($intentEvent) {
                $intentEvent->delete();
                DB::commit();
                
                return response()->json([
                    'message' => 'Intent event deleted successfully'
                ]);
            }

            // Try to delete from visitor_intents
            $visitorIntent = VisitorIntent::where('tenant_id', $tenantId)->find($id);
            
            if ($visitorIntent) {
                $visitorIntent->delete();
                DB::commit();
                
                return response()->json([
                    'message' => 'Visitor intent deleted successfully'
                ]);
            }

            DB::rollBack();
            return response()->json([
                'message' => 'Intent event not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete intent event',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
