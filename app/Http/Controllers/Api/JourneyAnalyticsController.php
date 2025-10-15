<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Journey;
use App\Models\JourneyExecution;
use App\Models\JourneyStep;
use App\Models\ContactInteraction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class JourneyAnalyticsController extends Controller
{
    /**
     * Get contact journey analytics
     */
    public function getContactJourneyAnalytics($contactId)
    {
        try {
            $contact = Contact::with(['journeyExecutions.journey', 'interactions'])->findOrFail($contactId);
            
            $analytics = [
                'contact_id' => $contactId,
                'total_journeys' => $contact->journeyExecutions->count(),
                'active_journeys' => $contact->journeyExecutions->where('status', 'running')->count(),
                'completed_journeys' => $contact->journeyExecutions->where('status', 'completed')->count(),
                'total_interactions' => $contact->interactions->count(),
                'interaction_types' => $contact->interactions->groupBy('type')->map->count(),
                'journey_performance' => [],
                'engagement_timeline' => [],
                'conversion_funnel' => []
            ];

            // Journey performance metrics
            foreach ($contact->journeyExecutions as $execution) {
                $journey = $execution->journey;
                $totalSteps = $journey->steps->count();
                $completedSteps = count($execution->execution_data['completed_steps'] ?? []);
                $completionRate = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100, 2) : 0;
                
                $analytics['journey_performance'][] = [
                    'journey_id' => $journey->id,
                    'journey_name' => $journey->name,
                    'status' => $execution->status,
                    'completion_rate' => $completionRate,
                    'started_at' => $execution->started_at,
                    'completed_at' => $execution->completed_at,
                    'duration_days' => $execution->completed_at ? 
                        $execution->started_at->diffInDays($execution->completed_at) : 
                        $execution->started_at->diffInDays(now()),
                ];
            }

            // Engagement timeline (last 30 days)
            $interactions = $contact->interactions()
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at')
                ->get();

            $timeline = [];
            foreach ($interactions->groupBy(function($item) {
                return $item->created_at->format('Y-m-d');
            }) as $date => $dayInteractions) {
                $timeline[] = [
                    'date' => $date,
                    'interactions_count' => $dayInteractions->count(),
                    'interaction_types' => $dayInteractions->groupBy('type')->map->count(),
                ];
            }
            $analytics['engagement_timeline'] = $timeline;

            // Simple conversion funnel
            $analytics['conversion_funnel'] = [
                'lead_generated' => $contact->interactions->where('type', 'form_submission')->count() > 0,
                'first_contact' => $contact->interactions->where('type', 'email')->count() > 0,
                'engaged' => $contact->interactions->count() > 3,
                'qualified' => $contact->lead_score > 50,
                'opportunity_created' => $contact->deals()->count() > 0,
                'customer' => $contact->lifecycle_stage === 'customer',
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching contact journey analytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching journey analytics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get overall journey analytics overview
     */
    public function getJourneyAnalyticsOverview()
    {
        try {
            $overview = [
                'total_contacts' => Contact::count(),
                'total_journeys' => Journey::count(),
                'active_journeys' => Journey::where('is_active', true)->count(),
                'total_executions' => JourneyExecution::count(),
                'running_executions' => JourneyExecution::where('status', 'running')->count(),
                'completed_executions' => JourneyExecution::where('status', 'completed')->count(),
                'average_completion_rate' => 0,
                'top_performing_journeys' => [],
                'recent_activity' => []
            ];

            // Calculate average completion rate
            $journeyStats = Journey::with('executions')->get()->map(function($journey) {
                $totalExecutions = $journey->executions->count();
                $completedExecutions = $journey->executions->where('status', 'completed')->count();
                $completionRate = $totalExecutions > 0 ? ($completedExecutions / $totalExecutions) * 100 : 0;
                
                return [
                    'journey_id' => $journey->id,
                    'journey_name' => $journey->name,
                    'total_executions' => $totalExecutions,
                    'completed_executions' => $completedExecutions,
                    'completion_rate' => round($completionRate, 2),
                ];
            });

            $overview['average_completion_rate'] = $journeyStats->avg('completion_rate') ?? 0;
            $overview['top_performing_journeys'] = $journeyStats->sortByDesc('completion_rate')->take(5)->values();

            // Recent activity (last 7 days)
            $recentExecutions = JourneyExecution::with(['contact', 'journey'])
                ->where('created_at', '>=', now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            $overview['recent_activity'] = $recentExecutions->map(function($execution) {
                return [
                    'contact_name' => $execution->contact->full_name,
                    'contact_email' => $execution->contact->email,
                    'journey_name' => $execution->journey->name,
                    'status' => $execution->status,
                    'started_at' => $execution->started_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $overview
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching journey analytics overview: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching analytics overview',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get stage-wise analytics
     */
    public function getStageAnalytics()
    {
        try {
            $stageAnalytics = [];

            $journeys = Journey::with(['steps', 'executions'])->get();

            foreach ($journeys as $journey) {
                foreach ($journey->steps as $step) {
                    // Count how many executions reached this step
                    $reachedCount = 0;
                    $completedCount = 0;

                    foreach ($journey->executions as $execution) {
                        $executionData = $execution->execution_data ?? [];
                        $completedSteps = $executionData['completed_steps'] ?? [];
                        
                        // Check if execution reached this step
                        if ($execution->current_step_id == $step->id || in_array($step->id, $completedSteps)) {
                            $reachedCount++;
                        }

                        // Check if step was completed
                        if (in_array($step->id, $completedSteps)) {
                            $completedCount++;
                        }
                    }

                    $completionRate = $reachedCount > 0 ? round(($completedCount / $reachedCount) * 100, 2) : 0;

                    $stageAnalytics[] = [
                        'journey_id' => $journey->id,
                        'journey_name' => $journey->name,
                        'step_id' => $step->id,
                        'step_name' => $step->getDescription(),
                        'step_type' => $step->step_type,
                        'step_order' => $step->order_no,
                        'contacts_reached' => $reachedCount,
                        'contacts_completed' => $completedCount,
                        'completion_rate' => $completionRate,
                        'drop_off_rate' => round(100 - $completionRate, 2),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'stage_analytics' => $stageAnalytics,
                    'total_stages' => count($stageAnalytics),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching stage analytics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stage analytics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get drop-off analysis
     */
    public function getDropOffAnalysis()
    {
        try {
            $dropOffData = [];

            $journeys = Journey::with(['steps', 'executions'])->get();

            foreach ($journeys as $journey) {
                $steps = $journey->steps()->orderBy('order_no')->get();
                $totalExecutions = $journey->executions->count();

                if ($totalExecutions == 0) continue;

                $journeyDropOff = [
                    'journey_id' => $journey->id,
                    'journey_name' => $journey->name,
                    'total_started' => $totalExecutions,
                    'steps_analysis' => []
                ];

                foreach ($steps as $index => $step) {
                    $reachedCount = 0;
                    $completedCount = 0;

                    foreach ($journey->executions as $execution) {
                        $executionData = $execution->execution_data ?? [];
                        $completedSteps = $executionData['completed_steps'] ?? [];

                        if ($execution->current_step_id == $step->id || in_array($step->id, $completedSteps)) {
                            $reachedCount++;
                        }

                        if (in_array($step->id, $completedSteps)) {
                            $completedCount++;
                        }
                    }

                    $reachRate = round(($reachedCount / $totalExecutions) * 100, 2);
                    $completionRate = $reachedCount > 0 ? round(($completedCount / $reachedCount) * 100, 2) : 0;
                    $dropOffRate = round(100 - $completionRate, 2);

                    $journeyDropOff['steps_analysis'][] = [
                        'step_order' => $step->order_no,
                        'step_name' => $step->getDescription(),
                        'contacts_reached' => $reachedCount,
                        'contacts_completed' => $completedCount,
                        'reach_rate' => $reachRate,
                        'completion_rate' => $completionRate,
                        'drop_off_rate' => $dropOffRate,
                        'is_bottleneck' => $dropOffRate > 50, // Flag high drop-off steps
                    ];
                }

                $dropOffData[] = $journeyDropOff;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'drop_off_analysis' => $dropOffData,
                    'summary' => [
                        'total_journeys_analyzed' => count($dropOffData),
                        'bottleneck_steps' => collect($dropOffData)
                            ->flatMap(function($journey) {
                                return collect($journey['steps_analysis'])->where('is_bottleneck', true);
                            })
                            ->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching drop-off analysis: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching drop-off analysis',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get conversion rates analytics
     */
    public function getConversionRates()
    {
        try {
            $conversionData = [];

            $journeys = Journey::with(['executions'])->get();

            foreach ($journeys as $journey) {
                $totalStarted = $journey->executions->count();
                $totalCompleted = $journey->executions->where('status', 'completed')->count();
                $totalRunning = $journey->executions->where('status', 'running')->count();
                $totalFailed = $journey->executions->where('status', 'failed')->count();

                $conversionRate = $totalStarted > 0 ? round(($totalCompleted / $totalStarted) * 100, 2) : 0;
                $failureRate = $totalStarted > 0 ? round(($totalFailed / $totalStarted) * 100, 2) : 0;

                // Calculate average completion time
                $completedExecutions = $journey->executions->where('status', 'completed');
                $avgCompletionDays = 0;
                
                if ($completedExecutions->count() > 0) {
                    $totalDays = $completedExecutions->sum(function($execution) {
                        return $execution->started_at->diffInDays($execution->completed_at);
                    });
                    $avgCompletionDays = round($totalDays / $completedExecutions->count(), 1);
                }

                $conversionData[] = [
                    'journey_id' => $journey->id,
                    'journey_name' => $journey->name,
                    'total_started' => $totalStarted,
                    'total_completed' => $totalCompleted,
                    'total_running' => $totalRunning,
                    'total_failed' => $totalFailed,
                    'conversion_rate' => $conversionRate,
                    'failure_rate' => $failureRate,
                    'avg_completion_days' => $avgCompletionDays,
                    'performance_grade' => $this->getPerformanceGrade($conversionRate),
                ];
            }

            // Overall conversion metrics
            $overallMetrics = [
                'total_executions' => collect($conversionData)->sum('total_started'),
                'total_conversions' => collect($conversionData)->sum('total_completed'),
                'overall_conversion_rate' => collect($conversionData)->avg('conversion_rate') ?? 0,
                'best_performing_journey' => collect($conversionData)->sortByDesc('conversion_rate')->first(),
                'worst_performing_journey' => collect($conversionData)->sortBy('conversion_rate')->first(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'conversion_rates' => $conversionData,
                    'overall_metrics' => $overallMetrics,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching conversion rates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching conversion rates',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get performance grade based on conversion rate
     */
    private function getPerformanceGrade($conversionRate)
    {
        if ($conversionRate >= 80) return 'A';
        if ($conversionRate >= 60) return 'B';
        if ($conversionRate >= 40) return 'C';
        if ($conversionRate >= 20) return 'D';
        return 'F';
    }
}








<<<<<<< HEAD




=======
>>>>>>> 35e2766 (Add Journey, SEO, and Social Media modules with full API integration)
