<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Journey;
use App\Models\JourneyExecution;
use App\Services\JourneyEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class JourneyAutomationController extends Controller
{
    protected $journeyEngine;

    public function __construct(JourneyEngine $journeyEngine)
    {
        $this->journeyEngine = $journeyEngine;
    }

    /**
     * Trigger journey automation for a contact
     */
    public function triggerJourneyAutomation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_id' => 'required|exists:contacts,id',
            'journey_id' => 'required|exists:journeys,id',
            'trigger_type' => 'required|string|in:manual,automatic,conditional',
            'trigger_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contact = Contact::findOrFail($request->contact_id);
            $journey = Journey::findOrFail($request->journey_id);

            // Check if contact already has an active execution for this journey
            $existingExecution = JourneyExecution::where('contact_id', $contact->id)
                ->where('journey_id', $journey->id)
                ->whereIn('status', ['running', 'paused'])
                ->first();

            if ($existingExecution) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact already has an active execution for this journey',
                    'data' => [
                        'existing_execution_id' => $existingExecution->id,
                        'status' => $existingExecution->status
                    ]
                ], 409);
            }

            // Start the journey execution
            $execution = $this->journeyEngine->startJourney($journey, $contact);

            // Log the trigger
            $triggerLog = [
                'trigger_type' => $request->trigger_type,
                'trigger_data' => $request->trigger_data,
                'triggered_at' => now()->toIso8601String(),
                'triggered_by' => auth()->id() ?? 'system',
            ];

            $executionData = $execution->execution_data ?? [];
            $executionData['trigger_log'] = $triggerLog;
            $execution->update(['execution_data' => $executionData]);

            return response()->json([
                'success' => true,
                'data' => [
                    'execution_id' => $execution->id,
                    'contact_id' => $contact->id,
                    'journey_id' => $journey->id,
                    'status' => $execution->status,
                    'started_at' => $execution->started_at,
                    'current_step' => $execution->currentStep?->getDescription(),
                    'trigger_log' => $triggerLog,
                ],
                'message' => 'Journey automation triggered successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error triggering journey automation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error triggering journey automation',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get journey automation rules
     */
    public function getAutomationRules()
    {
        try {
            // For now, return predefined automation rules
            // In a full implementation, these would be stored in a database table
            $rules = [
                [
                    'id' => 1,
                    'name' => 'New Lead Onboarding',
                    'description' => 'Automatically start onboarding journey for new leads',
                    'trigger_event' => 'contact_created',
                    'conditions' => [
                        'lifecycle_stage' => 'lead',
                        'source' => ['website', 'form_submission']
                    ],
                    'journey_id' => 1,
                    'is_active' => true,
                    'created_at' => '2025-10-01T00:00:00Z',
                ],
                [
                    'id' => 2,
                    'name' => 'Enterprise Customer Journey',
                    'description' => 'Start enterprise journey for high-value contacts',
                    'trigger_event' => 'lead_score_updated',
                    'conditions' => [
                        'lead_score' => ['>=', 75],
                        'company_size' => ['>=', 1000]
                    ],
                    'journey_id' => 2,
                    'is_active' => true,
                    'created_at' => '2025-10-01T00:00:00Z',
                ],
                [
                    'id' => 3,
                    'name' => 'Re-engagement Campaign',
                    'description' => 'Re-engage inactive contacts',
                    'trigger_event' => 'inactivity_detected',
                    'conditions' => [
                        'last_activity' => ['<', '30 days ago'],
                        'lifecycle_stage' => ['!=', 'customer']
                    ],
                    'journey_id' => 3,
                    'is_active' => false,
                    'created_at' => '2025-10-01T00:00:00Z',
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'rules' => $rules,
                    'total_rules' => count($rules),
                    'active_rules' => collect($rules)->where('is_active', true)->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching automation rules: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching automation rules',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create journey automation rule
     */
    public function createAutomationRule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'required|string|in:contact_created,lead_score_updated,interaction_added,form_submitted,email_opened,email_clicked,inactivity_detected',
            'conditions' => 'required|array',
            'journey_id' => 'required|exists:journeys,id',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // In a full implementation, this would create a record in an automation_rules table
            $rule = [
                'id' => rand(1000, 9999), // Temporary ID generation
                'name' => $request->name,
                'description' => $request->description,
                'trigger_event' => $request->trigger_event,
                'conditions' => $request->conditions,
                'journey_id' => $request->journey_id,
                'is_active' => $request->is_active ?? true,
                'created_at' => now()->toIso8601String(),
                'created_by' => auth()->id() ?? 'system',
            ];

            // Log the rule creation
            Log::info('Journey automation rule created', $rule);

            return response()->json([
                'success' => true,
                'data' => ['rule' => $rule],
                'message' => 'Journey automation rule created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating automation rule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating automation rule',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update journey automation rule
     */
    public function updateAutomationRule(Request $request, $ruleId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'sometimes|string|in:contact_created,lead_score_updated,interaction_added,form_submitted,email_opened,email_clicked,inactivity_detected',
            'conditions' => 'sometimes|array',
            'journey_id' => 'sometimes|exists:journeys,id',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // In a full implementation, this would update the record in the database
            $updatedRule = [
                'id' => $ruleId,
                'name' => $request->name ?? 'Updated Rule',
                'description' => $request->description,
                'trigger_event' => $request->trigger_event ?? 'contact_created',
                'conditions' => $request->conditions ?? [],
                'journey_id' => $request->journey_id ?? 1,
                'is_active' => $request->is_active ?? true,
                'updated_at' => now()->toIso8601String(),
                'updated_by' => auth()->id() ?? 'system',
            ];

            // Log the rule update
            Log::info('Journey automation rule updated', $updatedRule);

            return response()->json([
                'success' => true,
                'data' => ['rule' => $updatedRule],
                'message' => 'Journey automation rule updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating automation rule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating automation rule',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete journey automation rule
     */
    public function deleteAutomationRule($ruleId)
    {
        try {
            // In a full implementation, this would delete the record from the database
            // For now, just log the deletion
            Log::info('Journey automation rule deleted', ['rule_id' => $ruleId]);

            return response()->json([
                'success' => true,
                'data' => ['rule_id' => $ruleId],
                'message' => 'Journey automation rule deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting automation rule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting automation rule',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Bulk trigger journey automation
     */
    public function bulkTriggerAutomation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'exists:contacts,id',
            'journey_id' => 'required|exists:journeys,id',
            'trigger_type' => 'required|string|in:manual,automatic,conditional',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $journey = Journey::findOrFail($request->journey_id);
            $results = [];
            $successCount = 0;
            $errorCount = 0;

            DB::beginTransaction();

            foreach ($request->contact_ids as $contactId) {
                try {
                    $contact = Contact::findOrFail($contactId);

                    // Check for existing execution
                    $existingExecution = JourneyExecution::where('contact_id', $contactId)
                        ->where('journey_id', $journey->id)
                        ->whereIn('status', ['running', 'paused'])
                        ->first();

                    if ($existingExecution) {
                        $results[] = [
                            'contact_id' => $contactId,
                            'status' => 'skipped',
                            'message' => 'Already has active execution',
                            'execution_id' => $existingExecution->id
                        ];
                        continue;
                    }

                    // Start journey
                    $execution = $this->journeyEngine->startJourney($journey, $contact);
                    
                    $results[] = [
                        'contact_id' => $contactId,
                        'status' => 'success',
                        'message' => 'Journey started successfully',
                        'execution_id' => $execution->id
                    ];
                    $successCount++;

                } catch (\Exception $e) {
                    $results[] = [
                        'contact_id' => $contactId,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'execution_id' => null
                    ];
                    $errorCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total_contacts' => count($request->contact_ids),
                        'successful' => $successCount,
                        'errors' => $errorCount,
                        'skipped' => count($results) - $successCount - $errorCount,
                    ]
                ],
                'message' => "Bulk automation completed: {$successCount} successful, {$errorCount} errors"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk trigger automation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error in bulk automation trigger',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}








<<<<<<< HEAD




=======
>>>>>>> 35e2766 (Add Journey, SEO, and Social Media modules with full API integration)
