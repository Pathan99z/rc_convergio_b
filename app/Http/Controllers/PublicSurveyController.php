<?php

namespace App\Http\Controllers;

use App\Models\Service\Survey;
use App\Models\Service\SurveyResponse;
use App\Models\Service\Ticket;
use App\Services\Service\SurveyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicSurveyController extends Controller
{
    public function __construct(
        private SurveyService $surveyService
    ) {}

    /**
     * Display the public survey form
     */
    public function show(Request $request, int $surveyId)
    {
        try {
            // Get tenant_id from query parameter
            $tenantId = $request->query('tenant');
            if (!$tenantId || !is_numeric($tenantId)) {
                abort(400, 'Tenant ID is required');
            }

            // Get the survey
            $survey = Survey::forTenant($tenantId)
                ->where('id', $surveyId)
                ->where('is_active', true)
                ->with('questions')
                ->firstOrFail();

            // Get ticket information if provided
            $ticket = null;
            if ($request->has('ticket')) {
                $ticketId = $request->query('ticket');
                $ticket = Ticket::forTenant($tenantId)
                    ->where('id', $ticketId)
                    ->first();
            }

            return view('public.survey', compact('survey', 'ticket', 'tenantId'));

        } catch (\Exception $e) {
            Log::error('PublicSurveyController: Error showing survey', [
                'survey_id' => $surveyId,
                'tenant_id' => $request->query('tenant'),
                'error' => $e->getMessage(),
            ]);

            abort(404, 'Survey not found');
        }
    }

    /**
     * Submit the survey response
     */
    public function submit(Request $request, int $surveyId)
    {
        try {
            // Get tenant_id from query parameter
            $tenantId = $request->query('tenant');
            if (!$tenantId || !is_numeric($tenantId)) {
                return response()->json([
                    'message' => 'Tenant ID is required'
                ], 400);
            }

            // Get the survey
            $survey = Survey::forTenant($tenantId)
                ->where('id', $surveyId)
                ->where('is_active', true)
                ->with('questions')
                ->firstOrFail();

            // Validate the request
            $validator = Validator::make($request->all(), [
                'responses' => 'required|array|min:1',
                'responses.*.question_id' => 'required|integer|exists:survey_questions,id',
                'responses.*.answer' => 'nullable|string|max:2000',
                'ticket_id' => 'nullable|integer|exists:tickets,id',
                'respondent_email' => 'nullable|email|max:255',
                'feedback' => 'nullable|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare response data
            $responseData = [
                'responses' => $request->input('responses'),
                'ticket_id' => $request->input('ticket_id'),
                'respondent_email' => $request->input('respondent_email'),
                'feedback' => $request->input('feedback'),
            ];

            // Submit the response
            $response = $this->surveyService->submitResponse(
                $responseData,
                $survey->id,
                null, // contactId
                $responseData['ticket_id']
            );

            Log::info('Public survey response submitted', [
                'survey_id' => $surveyId,
                'response_id' => $response->id,
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'message' => 'Thank you for your feedback!',
                'success' => true,
                'response_id' => $response->id
            ], 201);

        } catch (\Exception $e) {
            Log::error('PublicSurveyController: Error submitting survey', [
                'survey_id' => $surveyId,
                'tenant_id' => $request->query('tenant'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred while submitting your response. Please try again.',
                'success' => false
            ], 500);
        }
    }
}
