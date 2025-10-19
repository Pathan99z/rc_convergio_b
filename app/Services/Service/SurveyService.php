<?php

namespace App\Services\Service;

use App\Models\Service\Survey;
use App\Models\Service\SurveyQuestion;
use App\Models\Service\SurveyResponse;
use App\Models\Contact;
use App\Models\Service\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SurveyService
{
    /**
     * Get paginated surveys with filters
     */
    public function getSurveys(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Survey::query()
            ->forTenant($filters['tenant_id'] ?? null)
            ->with(['questions', 'responses'])
            ->withCount('responses');

        // Apply filters
        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['q'])) {
            $searchTerm = $filters['q'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new survey
     */
    public function createSurvey(array $data, int $tenantId): Survey
    {
        return DB::transaction(function () use ($data, $tenantId) {
            // Create the survey
            $survey = Survey::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? 'general',
                'is_active' => $data['is_active'] ?? true,
                'auto_send' => $data['auto_send'] ?? false,
                'settings' => $data['settings'] ?? null,
            ]);

            // Create questions if provided
            if (!empty($data['questions'])) {
                foreach ($data['questions'] as $index => $questionData) {
                    SurveyQuestion::create([
                        'survey_id' => $survey->id,
                        'question' => $questionData['question'],
                        'type' => $questionData['type'] ?? 'text',
                        'options' => $questionData['options'] ?? null,
                        'is_required' => $questionData['is_required'] ?? false,
                        'order' => $index + 1,
                    ]);
                }
            }

            Log::info('Survey created', [
                'survey_id' => $survey->id,
                'tenant_id' => $tenantId,
                'name' => $survey->name,
                'type' => $survey->type,
            ]);

            return $survey->load('questions');
        });
    }

    /**
     * Update a survey
     */
    public function updateSurvey(Survey $survey, array $data): bool
    {
        return DB::transaction(function () use ($survey, $data) {
            $updated = $survey->update($data);

            // Update questions if provided
            if (isset($data['questions'])) {
                // Delete existing questions
                $survey->questions()->delete();

                // Create new questions
                foreach ($data['questions'] as $index => $questionData) {
                    SurveyQuestion::create([
                        'survey_id' => $survey->id,
                        'question' => $questionData['question'],
                        'type' => $questionData['type'] ?? 'text',
                        'options' => $questionData['options'] ?? null,
                        'is_required' => $questionData['is_required'] ?? false,
                        'order' => $index + 1,
                    ]);
                }
            }

            Log::info('Survey updated', [
                'survey_id' => $survey->id,
                'changes' => $data,
            ]);

            return $updated;
        });
    }

    /**
     * Submit a survey response
     */
    public function submitResponse(array $data, int $surveyId, ?int $contactId = null, ?int $ticketId = null): SurveyResponse
    {
        return DB::transaction(function () use ($data, $surveyId, $contactId, $ticketId) {
            $survey = Survey::findOrFail($surveyId);

            // Calculate overall score based on survey type
            $overallScore = $this->calculateOverallScore($survey, $data['responses']);

            $response = SurveyResponse::create([
                'survey_id' => $surveyId,
                'contact_id' => $contactId,
                'ticket_id' => $ticketId,
                'respondent_email' => $data['respondent_email'] ?? null,
                'responses' => $data['responses'],
                'overall_score' => $overallScore,
                'feedback' => $data['feedback'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ]);

            Log::info('Survey response submitted', [
                'response_id' => $response->id,
                'survey_id' => $surveyId,
                'contact_id' => $contactId,
                'ticket_id' => $ticketId,
                'overall_score' => $overallScore,
            ]);

            return $response;
        });
    }

    /**
     * Get survey analytics
     */
    public function getSurveyAnalytics(int $surveyId): array
    {
        $survey = Survey::findOrFail($surveyId);
        $responses = $survey->responses()->withScores();

        $totalResponses = $responses->count();
        $averageScore = $responses->avg('overall_score') ?? 0;

        // Get score distribution
        $scoreDistribution = [];
        if ($survey->type === 'nps') {
            // NPS calculation: % Promoters (9-10) - % Detractors (0-6)
            $promoters = $responses->where('overall_score', '>=', 9)->count();
            $detractors = $responses->where('overall_score', '<=', 6)->count();
            $nps = $totalResponses > 0 ? (($promoters - $detractors) / $totalResponses) * 100 : 0;
            
            $scoreDistribution = [
                'promoters' => $promoters,
                'passives' => $responses->whereBetween('overall_score', [7, 8])->count(),
                'detractors' => $detractors,
                'nps_score' => round($nps, 1),
            ];
        } else {
            // CSAT or general rating distribution
            for ($i = 1; $i <= 5; $i++) {
                $scoreDistribution[$i] = $responses->where('overall_score', $i)->count();
            }
        }

        return [
            'total_responses' => $totalResponses,
            'average_score' => round($averageScore, 2),
            'score_distribution' => $scoreDistribution,
            'response_rate' => $this->calculateResponseRate($survey),
        ];
    }

    /**
     * Calculate overall score based on survey type and responses
     */
    private function calculateOverallScore(Survey $survey, array $responses): ?float
    {
        if ($survey->type === 'nps') {
            // For NPS, look for rating question (0-10 scale)
            foreach ($responses as $response) {
                $answer = is_array($response) ? $response['answer'] : $response;
                if (is_numeric($answer) && $answer >= 0 && $answer <= 10) {
                    return (float) $answer;
                }
            }
        } elseif ($survey->type === 'csat' || $survey->type === 'post_ticket') {
            // For CSAT and post-ticket, look for rating question (1-5 scale)
            foreach ($responses as $response) {
                $answer = is_array($response) ? $response['answer'] : $response;
                if (is_numeric($answer) && $answer >= 1 && $answer <= 5) {
                    return (float) $answer;
                }
            }
        }

        return null;
    }

    /**
     * Calculate response rate (if applicable)
     */
    private function calculateResponseRate(Survey $survey): ?float
    {
        // This would need to be implemented based on how surveys are sent
        // For now, return null as we don't have sending mechanism yet
        return null;
    }

    /**
     * Get surveys for public access
     */
    public function getPublicSurveys(int $tenantId): array
    {
        return Survey::forTenant($tenantId)
            ->active()
            ->with('questions')
            ->get()
            ->toArray();
    }

    /**
     * Send survey reminder
     */
    public function sendSurveyReminder(Survey $survey, Ticket $ticket, string $contactEmail): void
    {
        try {
            // Use the existing TicketMailerService to send the reminder
            $ticketMailerService = app(\App\Services\Service\TicketMailerService::class);
            $ticketMailerService->sendPostTicketSurvey($ticket, $survey, $contactEmail);

            Log::info('Survey reminder sent successfully', [
                'survey_id' => $survey->id,
                'ticket_id' => $ticket->id,
                'contact_email' => $contactEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send survey reminder', [
                'survey_id' => $survey->id,
                'ticket_id' => $ticket->id,
                'contact_email' => $contactEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
