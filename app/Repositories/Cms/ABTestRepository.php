<?php

namespace App\Repositories\Cms;

use App\Models\Cms\ABTest;
use App\Models\Cms\ABTestVisitor;
use Illuminate\Database\Eloquent\Collection;

class ABTestRepository
{
    /**
     * Get all A/B tests with optional filters.
     */
    public function getTests(array $filters = []): Collection
    {
        $query = ABTest::with(['page', 'variantA', 'variantB', 'creator']);

        if (isset($filters['page_id'])) {
            $query->where('page_id', $filters['page_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get running tests.
     */
    public function getRunningTests(): Collection
    {
        return ABTest::running()
                    ->with(['page', 'variantA', 'variantB'])
                    ->get();
    }

    /**
     * Create a new A/B test.
     */
    public function create(array $data): ABTest
    {
        return ABTest::create($data);
    }

    /**
     * Update an A/B test.
     */
    public function update(ABTest $test, array $data): bool
    {
        return $test->update($data);
    }

    /**
     * Delete an A/B test.
     */
    public function delete(ABTest $test): bool
    {
        return $test->delete();
    }

    /**
     * Record a visitor for an A/B test.
     */
    public function recordVisitor(int $testId, string $visitorId, array $metadata = []): ?ABTestVisitor
    {
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
    }

    /**
     * Record a conversion.
     */
    public function recordConversion(int $testId, string $visitorId, array $conversionData = []): bool
    {
        $visitor = ABTestVisitor::where('ab_test_id', $testId)
                               ->where('visitor_id', $visitorId)
                               ->first();

        if (!$visitor || $visitor->converted) {
            return false;
        }

        $visitor->markAsConverted($conversionData);
        return true;
    }

    /**
     * Get test results.
     */
    public function getTestResults(int $testId): array
    {
        $test = ABTest::with(['visitors'])->find($testId);
        
        if (!$test) {
            return [];
        }

        return $test->getStatisticalSignificance();
    }

    /**
     * Get A/B testing statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_tests' => ABTest::count(),
            'running_tests' => ABTest::where('status', 'running')->count(),
            'completed_tests' => ABTest::where('status', 'completed')->count(),
            'total_visitors' => ABTestVisitor::count(),
            'total_conversions' => ABTestVisitor::where('converted', true)->count(),
        ];
    }
}



