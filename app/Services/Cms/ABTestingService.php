<?php

namespace App\Services\Cms;

use App\Models\Cms\ABTest;
use App\Models\Cms\ABTestVisitor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ABTestingService
{
    /**
     * Create a new A/B test.
     */
    public function createTest(array $data): ABTest
    {
        return ABTest::create($data);
    }

    /**
     * Get the variant to show for a visitor.
     */
    public function getVariantForVisitor(int $testId, string $visitorId): string
    {
        $test = ABTest::find($testId);
        
        if (!$test || !$test->isRunning()) {
            return 'a'; // Default to original
        }

        return $test->getVariantForVisitor($visitorId);
    }

    /**
     * Record a visitor for an A/B test.
     */
    public function recordVisitor(int $testId, string $visitorId, array $metadata = []): ?ABTestVisitor
    {
        try {
            $test = ABTest::find($testId);
            
            if (!$test || !$test->isRunning()) {
                return null;
            }

            // Check if visitor already recorded
            $existingVisitor = ABTestVisitor::where('ab_test_id', $testId)
                                          ->where('visitor_id', $visitorId)
                                          ->first();

            if ($existingVisitor) {
                return $existingVisitor;
            }

            $variant = $test->getVariantForVisitor($visitorId);

            return ABTestVisitor::create([
                'ab_test_id' => $testId,
                'visitor_id' => $visitorId,
                'variant_shown' => $variant,
                'ip_address' => $metadata['ip_address'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? null,
                'referrer' => $metadata['referrer'] ?? null,
                'visited_at' => now()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to record A/B test visitor', [
                'test_id' => $testId,
                'visitor_id' => $visitorId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Record a conversion for an A/B test.
     */
    public function recordConversion(int $testId, string $visitorId, array $conversionData = []): bool
    {
        try {
            $visitor = ABTestVisitor::where('ab_test_id', $testId)
                                   ->where('visitor_id', $visitorId)
                                   ->first();

            if (!$visitor || $visitor->converted) {
                return false;
            }

            $visitor->markAsConverted($conversionData);
            
            // Update test performance data
            $this->updateTestPerformance($testId);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to record A/B test conversion', [
                'test_id' => $testId,
                'visitor_id' => $visitorId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get A/B test results with statistical analysis.
     */
    public function getTestResults(int $testId): array
    {
        try {
            $test = ABTest::with(['visitors'])->find($testId);
            
            if (!$test) {
                return ['error' => 'Test not found'];
            }

            $results = $test->getStatisticalSignificance();
            
            return [
                'test_id' => $test->id,
                'test_name' => $test->name,
                'status' => $test->status,
                'duration_days' => $test->started_at ? $test->started_at->diffInDays($test->ended_at ?? now()) : 0,
                'results' => $results,
                'performance_summary' => $this->getPerformanceSummary($test)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get A/B test results', [
                'test_id' => $testId,
                'error' => $e->getMessage()
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Check if a test has reached statistical significance.
     */
    public function hasStatisticalSignificance(int $testId): bool
    {
        $test = ABTest::find($testId);
        
        if (!$test) {
            return false;
        }

        $results = $test->getStatisticalSignificance();
        return $results['is_significant'];
    }

    /**
     * Auto-optimize traffic split based on performance.
     */
    public function autoOptimize(int $testId): array
    {
        try {
            $test = ABTest::find($testId);
            
            if (!$test || !$test->isRunning()) {
                return ['success' => false, 'message' => 'Test not found or not running'];
            }

            $results = $test->getStatisticalSignificance();
            
            if ($results['is_significant'] && $results['improvement'] > 10) {
                // If variant B is significantly better, increase its traffic
                $newSplit = min(80, $test->traffic_split + 10);
                $test->update(['traffic_split' => $newSplit]);
                
                return [
                    'success' => true,
                    'message' => 'Traffic split optimized',
                    'new_split' => $newSplit,
                    'improvement' => $results['improvement']
                ];
            }

            return [
                'success' => true,
                'message' => 'No optimization needed',
                'current_split' => $test->traffic_split
            ];

        } catch (\Exception $e) {
            Log::error('Failed to auto-optimize A/B test', [
                'test_id' => $testId,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message' => 'Optimization failed'];
        }
    }

    /**
     * Update test performance data.
     */
    protected function updateTestPerformance(int $testId): void
    {
        try {
            $test = ABTest::find($testId);
            
            if (!$test) {
                return;
            }

            $performanceData = $test->performance_data ?? [];
            $performanceData['last_conversion'] = now()->toIso8601String();
            $performanceData['total_conversions'] = $test->visitors()->where('converted', true)->count();
            $performanceData['conversion_rate_a'] = $test->getConversionRate('a');
            $performanceData['conversion_rate_b'] = $test->getConversionRate('b');

            $test->update(['performance_data' => $performanceData]);

        } catch (\Exception $e) {
            Log::warning('Failed to update test performance data', [
                'test_id' => $testId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get performance summary for a test.
     */
    protected function getPerformanceSummary(ABTest $test): array
    {
        $totalVisitors = $test->visitors()->count();
        $totalConversions = $test->visitors()->where('converted', true)->count();
        
        return [
            'total_visitors' => $totalVisitors,
            'total_conversions' => $totalConversions,
            'overall_conversion_rate' => $totalVisitors > 0 ? ($totalConversions / $totalVisitors) * 100 : 0,
            'variant_a_visitors' => $test->visitors()->where('variant_shown', 'a')->count(),
            'variant_b_visitors' => $test->visitors()->where('variant_shown', 'b')->count(),
            'variant_a_conversions' => $test->visitors()->where('variant_shown', 'a')->where('converted', true)->count(),
            'variant_b_conversions' => $test->visitors()->where('variant_shown', 'b')->where('converted', true)->count(),
        ];
    }
}



