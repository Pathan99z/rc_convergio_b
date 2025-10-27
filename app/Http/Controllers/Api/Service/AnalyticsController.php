<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Service\Ticket;
use App\Models\Service\Survey;
use App\Models\Service\SurveyResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    /**
     * Get tickets with CSAT scores.
     */
    public function ticketsWithCsat(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;

            $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());

            $tickets = Ticket::forTenant($tenantId)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->whereHas('surveyResponses')
                ->with(['surveyResponses' => function ($query) {
                    $query->whereNotNull('overall_score')->orderBy('created_at', 'desc');
                }, 'assignee'])
                ->get()
                ->map(function ($ticket) {
                    $latestResponse = $ticket->surveyResponses->first();
                    return [
                        'ticket_id' => $ticket->id,
                        'subject' => $ticket->subject,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                        'assignee_name' => $ticket->assignee?->name,
                        'csat_score' => $latestResponse?->overall_score,
                        'feedback' => $latestResponse?->feedback,
                        'response_date' => $latestResponse?->created_at?->toISOString(),
                        'ticket_created_at' => $ticket->created_at->toISOString(),
                        'ticket_closed_at' => $ticket->closed_at?->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'tickets' => $tickets,
                    'summary' => [
                        'total_tickets' => $tickets->count(),
                        'average_csat' => $tickets->where('csat_score', '!=', null)->avg('csat_score'),
                        'date_range' => [
                            'from' => $dateFrom,
                            'to' => $dateTo
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tickets with CSAT', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CSAT data'
            ], 500);
        }
    }

    /**
     * Get CSAT trends by date range.
     */
    public function csatTrends(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;

            $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());
            $groupBy = $request->get('group_by', 'day'); // day, week, month

            $trends = SurveyResponse::whereHas('survey', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                      ->where('type', 'post_ticket');
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('overall_score')
            ->selectRaw("
                DATE(created_at) as date,
                AVG(overall_score) as average_score,
                COUNT(*) as response_count,
                SUM(CASE WHEN overall_score >= 4 THEN 1 ELSE 0 END) as satisfied_count,
                SUM(CASE WHEN overall_score <= 2 THEN 1 ELSE 0 END) as dissatisfied_count
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'average_score' => round($item->average_score, 2),
                    'response_count' => $item->response_count,
                    'satisfaction_rate' => $item->response_count > 0 ? 
                        round(($item->satisfied_count / $item->response_count) * 100, 2) : 0,
                    'dissatisfaction_rate' => $item->response_count > 0 ? 
                        round(($item->dissatisfied_count / $item->response_count) * 100, 2) : 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'trends' => $trends,
                    'summary' => [
                        'total_responses' => $trends->sum('response_count'),
                        'overall_average' => $trends->avg('average_score'),
                        'date_range' => [
                            'from' => $dateFrom,
                            'to' => $dateTo
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get CSAT trends', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CSAT trends'
            ], 500);
        }
    }

    /**
     * Get agent performance with CSAT.
     */
    public function agentPerformance(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;

            $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());

            $agentPerformance = Ticket::forTenant($tenantId)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->whereNotNull('assignee_id')
                ->whereHas('surveyResponses')
                ->with(['assignee', 'surveyResponses' => function ($query) {
                    $query->whereNotNull('overall_score')->orderBy('created_at', 'desc');
                }])
                ->get()
                ->groupBy('assignee_id')
                ->map(function ($tickets, $assigneeId) {
                    $assignee = $tickets->first()->assignee;
                    $totalTickets = $tickets->count();
                    $ticketsWithScores = $tickets->filter(function ($ticket) {
                        return $ticket->surveyResponses->isNotEmpty();
                    });
                    
                    $averageScore = $ticketsWithScores->avg(function ($ticket) {
                        return $ticket->surveyResponses->first()->overall_score;
                    });

                    $satisfiedCount = $ticketsWithScores->filter(function ($ticket) {
                        return $ticket->surveyResponses->first()->overall_score >= 4;
                    })->count();

                    return [
                        'agent_id' => $assigneeId,
                        'agent_name' => $assignee?->name ?? 'Unknown',
                        'agent_email' => $assignee?->email ?? 'Unknown',
                        'total_tickets' => $totalTickets,
                        'tickets_with_feedback' => $ticketsWithScores->count(),
                        'average_csat' => round($averageScore ?? 0, 2),
                        'satisfaction_rate' => $ticketsWithScores->count() > 0 ? 
                            round(($satisfiedCount / $ticketsWithScores->count()) * 100, 2) : 0,
                        'response_rate' => $totalTickets > 0 ? 
                            round(($ticketsWithScores->count() / $totalTickets) * 100, 2) : 0,
                    ];
                })
                ->values()
                ->sortByDesc('average_csat');

            return response()->json([
                'success' => true,
                'data' => [
                    'agents' => $agentPerformance,
                    'summary' => [
                        'total_agents' => $agentPerformance->count(),
                        'overall_average_csat' => $agentPerformance->avg('average_csat'),
                        'date_range' => [
                            'from' => $dateFrom,
                            'to' => $dateTo
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get agent performance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve agent performance data'
            ], 500);
        }
    }

    /**
     * Get overall survey summary.
     */
    public function surveySummary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id ?? $user->id;

            $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());

            // Get survey statistics
            $surveys = Survey::forTenant($tenantId)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->withCount('responses')
                ->get();

            $totalSurveys = $surveys->count();
            $activeSurveys = $surveys->where('is_active', true)->count();
            $totalResponses = $surveys->sum('responses_count');

            // Get response statistics
            $responses = SurveyResponse::whereHas('survey', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

            $csatResponses = $responses->filter(function ($response) {
                return $response->survey && $response->survey->type === 'post_ticket';
            });
            $averageCsat = $csatResponses->whereNotNull('overall_score')->avg('overall_score');

            $satisfactionRate = $csatResponses->where('overall_score', '>=', 4)->count() / 
                max($csatResponses->whereNotNull('overall_score')->count(), 1) * 100;

            return response()->json([
                'success' => true,
                'data' => [
                    'surveys' => [
                        'total_surveys' => $totalSurveys,
                        'active_surveys' => $activeSurveys,
                        'inactive_surveys' => $totalSurveys - $activeSurveys,
                    ],
                    'responses' => [
                        'total_responses' => $totalResponses,
                        'average_responses_per_survey' => $totalSurveys > 0 ? 
                            round($totalResponses / $totalSurveys, 2) : 0,
                    ],
                    'csat' => [
                        'total_csat_responses' => $csatResponses->count(),
                        'average_csat_score' => round($averageCsat ?? 0, 2),
                        'satisfaction_rate' => round($satisfactionRate, 2),
                        'satisfied_responses' => $csatResponses->where('overall_score', '>=', 4)->count(),
                        'dissatisfied_responses' => $csatResponses->where('overall_score', '<=', 2)->count(),
                    ],
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get survey summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve survey summary'
            ], 500);
        }
    }
}
