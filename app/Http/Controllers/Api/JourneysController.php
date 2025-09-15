<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyExecution;
use App\Models\Contact;
use App\Services\JourneyEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JourneysController extends Controller
{
    protected JourneyEngine $journeyEngine;

    public function __construct(JourneyEngine $journeyEngine)
    {
        $this->journeyEngine = $journeyEngine;
    }

    /**
     * Get all journeys for the authenticated user's tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = Journey::where('tenant_id', $tenantId)
            ->with(['steps' => function ($query) {
                $query->orderBy('order_no');
            }]);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $journeys = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add step descriptions to each journey
        $journeys->getCollection()->transform(function ($journey) {
            $journey->steps->transform(function ($step) {
                $step->description = $step->getDescription();
                return $step;
            });
            $journey->stats = $journey->getStats();
            return $journey;
        });

        return response()->json([
            'data' => $journeys->items(),
            'meta' => [
                'current_page' => $journeys->currentPage(),
                'last_page' => $journeys->lastPage(),
                'per_page' => $journeys->perPage(),
                'total' => $journeys->total(),
            ],
            'message' => 'Journeys retrieved successfully'
        ]);
    }

    /**
     * Create a new journey.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(Journey::getAvailableStatuses()))
            ],
            'settings' => 'nullable|array',
            'steps' => 'required|array|min:1',
            'steps.*.step_type' => [
                'required',
                'string',
                Rule::in(array_keys(JourneyStep::getAvailableStepTypes()))
            ],
            'steps.*.config' => 'required|array',
            'steps.*.order_no' => 'required|integer|min:1',
            'steps.*.conditions' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $journey = Journey::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'status' => $validated['status'] ?? 'draft',
                'settings' => $validated['settings'] ?? [],
                'tenant_id' => $tenantId,
                'created_by' => $user->id,
            ]);

            // Create journey steps
            foreach ($validated['steps'] as $stepData) {
                $step = JourneyStep::create([
                    'journey_id' => $journey->id,
                    'step_type' => $stepData['step_type'],
                    'config' => $stepData['config'],
                    'order_no' => $stepData['order_no'],
                    'conditions' => $stepData['conditions'] ?? null,
                ]);

                // Validate step configuration
                if (!$step->validateConfig()) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Invalid step configuration',
                        'errors' => ['steps' => ['Step configuration is invalid']]
                    ], 422);
                }
            }

            DB::commit();

            $journey->load(['steps' => function ($query) {
                $query->orderBy('order_no');
            }]);

            // Add step descriptions
            $journey->steps->transform(function ($step) {
                $step->description = $step->getDescription();
                return $step;
            });

            return response()->json([
                'data' => $journey,
                'message' => 'Journey created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create journey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific journey with steps.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $journey = Journey::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->with(['steps' => function ($query) {
                $query->orderBy('order_no');
            }])
            ->firstOrFail();

        // Add step descriptions
        $journey->steps->transform(function ($step) {
            $step->description = $step->getDescription();
            return $step;
        });

        $journey->stats = $journey->getStats();

        return response()->json([
            'data' => $journey,
            'message' => 'Journey retrieved successfully'
        ]);
    }

    /**
     * Update a journey.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $journey = Journey::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => [
                'sometimes',
                'string',
                Rule::in(array_keys(Journey::getAvailableStatuses()))
            ],
            'settings' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $journey->update($validated);

            $journey->load(['steps' => function ($query) {
                $query->orderBy('order_no');
            }]);

            // Add step descriptions
            $journey->steps->transform(function ($step) {
                $step->description = $step->getDescription();
                return $step;
            });

            return response()->json([
                'data' => $journey,
                'message' => 'Journey updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update journey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a journey.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $journey = Journey::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $journey->delete();

            return response()->json([
                'message' => 'Journey deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete journey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run a journey for a specific contact.
     */
    public function runForContact(Request $request, $journeyId, $contactId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify journey exists and belongs to tenant
        $journey = Journey::where('id', $journeyId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Verify contact exists and belongs to tenant
        $contact = Contact::where('id', $contactId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $execution = $this->journeyEngine->startJourney($journey, $contact);

            return response()->json([
                'data' => [
                    'journey_id' => $journey->id,
                    'name' => $journey->name,
                    'contact_id' => $contact->id,
                    'execution_id' => $execution->id,
                    'status' => $execution->status,
                    'started_at' => $execution->started_at,
                    'next_step_at' => $execution->next_step_at,
                ],
                'message' => 'Journey started successfully for contact'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start journey for contact',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get journey executions.
     */
    public function getExecutions(Request $request, $journeyId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify journey exists and belongs to tenant
        $journey = Journey::where('id', $journeyId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $query = JourneyExecution::where('journey_id', $journeyId)
            ->where('tenant_id', $tenantId)
            ->with(['contact:id,first_name,last_name,email', 'currentStep:id,step_type,order_no']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $executions = $query->orderBy('started_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add execution details
        $executions->getCollection()->transform(function ($execution) {
            $execution->progress_percentage = $execution->getProgressPercentage();
            $execution->duration_minutes = $execution->getDuration();
            return $execution;
        });

        return response()->json([
            'data' => $executions->items(),
            'meta' => [
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
                'per_page' => $executions->perPage(),
                'total' => $executions->total(),
            ],
            'message' => 'Journey executions retrieved successfully'
        ]);
    }

    /**
     * Get available journey statuses.
     */
    public function getStatuses(): JsonResponse
    {
        return response()->json([
            'data' => Journey::getAvailableStatuses(),
            'message' => 'Journey statuses retrieved successfully'
        ]);
    }

    /**
     * Get available step types.
     */
    public function getStepTypes(): JsonResponse
    {
        return response()->json([
            'data' => JourneyStep::getAvailableStepTypes(),
            'message' => 'Step types retrieved successfully'
        ]);
    }

    /**
     * Get step type configuration schema.
     */
    public function getStepTypeSchema(Request $request): JsonResponse
    {
        $stepType = $request->get('step_type');
        
        if (!$stepType) {
            return response()->json([
                'message' => 'Step type is required'
            ], 400);
        }

        $schema = JourneyStep::getStepTypeConfigSchema($stepType);

        return response()->json([
            'data' => [
                'step_type' => $stepType,
                'schema' => $schema
            ],
            'message' => 'Step type schema retrieved successfully'
        ]);
    }
}
