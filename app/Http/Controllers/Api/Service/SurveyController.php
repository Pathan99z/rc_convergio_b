<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreSurveyRequest;
use App\Http\Requests\Service\UpdateSurveyRequest;
use App\Http\Requests\Service\SubmitSurveyRequest;
use App\Http\Resources\Service\SurveyResource;
use App\Http\Resources\Service\SurveyResponseResource;
use App\Models\Service\Survey;
use App\Services\Service\SurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SurveyController extends Controller
{
    public function __construct(
        private SurveyService $surveyService
    ) {}

    /**
     * Display a listing of surveys.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? $user->id;

        $filters = [
            'tenant_id' => $tenantId,
            'type' => $request->get('type'),
            'is_active' => $request->get('is_active'),
            'q' => $request->get('q'),
            'sortBy' => $request->get('sortBy'),
            'sortOrder' => $request->get('sortOrder'),
        ];

        $surveys = $this->surveyService->getSurveys($filters);

        return SurveyResource::collection($surveys);
    }

    /**
     * Store a newly created survey.
     */
    public function store(StoreSurveyRequest $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? $user->id;

        $survey = $this->surveyService->createSurvey($request->validated(), $tenantId);

        return response()->json([
            'success' => true,
            'data' => new SurveyResource($survey),
            'message' => 'Survey created successfully'
        ], 201);
    }

    /**
     * Display the specified survey.
     */
    public function show(Survey $survey): JsonResponse
    {
        $this->authorize('view', $survey);

        return response()->json([
            'success' => true,
            'data' => new SurveyResource($survey->load('questions'))
        ]);
    }

    /**
     * Update the specified survey.
     */
    public function update(UpdateSurveyRequest $request, Survey $survey): JsonResponse
    {
        $this->authorize('update', $survey);

        $updated = $this->surveyService->updateSurvey($survey, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new SurveyResource($survey->fresh()->load('questions')),
            'message' => 'Survey updated successfully'
        ]);
    }

    /**
     * Remove the specified survey.
     */
    public function destroy(Survey $survey): JsonResponse
    {
        $this->authorize('delete', $survey);

        $survey->delete();

        return response()->json([
            'success' => true,
            'message' => 'Survey deleted successfully'
        ]);
    }

    /**
     * Get survey analytics.
     */
    public function analytics(Survey $survey): JsonResponse
    {
        $this->authorize('view', $survey);

        $analytics = $this->surveyService->getSurveyAnalytics($survey->id);

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get survey responses.
     */
    public function responses(Survey $survey, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $survey);

        $perPage = min($request->get('per_page', 15), 50);
        $responses = $survey->responses()
            ->with(['contact', 'ticket'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return SurveyResponseResource::collection($responses);
    }

    /**
     * Submit a survey response (public endpoint).
     */
    public function submitResponse(SubmitSurveyRequest $request, int $surveyId): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Add request metadata
            $data['ip_address'] = $request->ip();
            $data['user_agent'] = $request->userAgent();

            $response = $this->surveyService->submitResponse(
                $data,
                $surveyId,
                $data['contact_id'] ?? null,
                $data['ticket_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => new SurveyResponseResource($response),
                'message' => 'Survey response submitted successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Survey response submission failed', [
                'survey_id' => $surveyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit survey response'
            ], 500);
        }
    }

    /**
     * Get public surveys (for embedding).
     */
    public function publicSurveys(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant');
        
        if (!$tenantId || !is_numeric($tenantId)) {
            return response()->json([
                'success' => false,
                'message' => 'Valid tenant ID is required'
            ], 400);
        }

        $surveys = $this->surveyService->getPublicSurveys((int) $tenantId);

        return response()->json([
            'success' => true,
            'data' => $surveys
        ]);
    }

    /**
     * Get active surveys only.
     */
    public function activeSurveys(Request $request): AnonymousResourceCollection
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? $user->id;

        $surveys = Survey::forTenant($tenantId)
            ->where('is_active', true)
            ->withCount('responses')
            ->orderBy('created_at', 'desc')
            ->get();

        return SurveyResource::collection($surveys);
    }

    /**
     * Get post-ticket surveys specifically.
     */
    public function postTicketSurveys(Request $request): AnonymousResourceCollection
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? $user->id;

        $surveys = Survey::forTenant($tenantId)
            ->where('type', 'post_ticket')
            ->where('is_active', true)
            ->withCount('responses')
            ->orderBy('created_at', 'desc')
            ->get();

        return SurveyResource::collection($surveys);
    }

    /**
     * Send survey reminder.
     */
    public function sendReminder(Request $request, Survey $survey): JsonResponse
    {
        $this->authorize('update', $survey);

        try {
            $ticketId = $request->input('ticket_id');
            $contactEmail = $request->input('contact_email');

            if (!$ticketId || !$contactEmail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket ID and contact email are required'
                ], 400);
            }

            // Get the ticket
            $ticket = \App\Models\Service\Ticket::forTenant($survey->tenant_id)
                ->findOrFail($ticketId);

            // Send reminder email
            $this->surveyService->sendSurveyReminder($survey, $ticket, $contactEmail);

            return response()->json([
                'success' => true,
                'message' => 'Survey reminder sent successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send survey reminder', [
                'survey_id' => $survey->id,
                'ticket_id' => $request->input('ticket_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send survey reminder'
            ], 500);
        }
    }
}