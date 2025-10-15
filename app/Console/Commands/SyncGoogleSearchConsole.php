<?php

namespace App\Console\Commands;

use App\Models\SeoToken;
use App\Models\User;
use App\Services\SeoAnalyticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncGoogleSearchConsole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:sync {--user= : Specific user ID to sync} {--days=30 : Number of days to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync SEO data from Google Search Console for all users';

    protected $seoAnalyticsService;

    /**
     * Create a new command instance.
     */
    public function __construct(SeoAnalyticsService $seoAnalyticsService)
    {
        parent::__construct();
        $this->seoAnalyticsService = $seoAnalyticsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Google Search Console data synchronization...');

        $userId = $this->option('user');
        $days = $this->option('days');

        if ($userId) {
            // Sync specific user
            $this->syncUser($userId, $days);
        } else {
            // Sync all users with active tokens
            $this->syncAllUsers($days);
        }

        $this->info('Synchronization completed!');
        return 0;
    }

    /**
     * Sync data for a specific user
     */
    protected function syncUser($userId, $days)
    {
        try {
            $token = SeoToken::getForUser($userId);

            if (!$token) {
                $this->warn("No Google Search Console token found for user {$userId}");
                return;
            }

            if ($token->isExpired()) {
                $this->warn("Token expired for user {$userId}");
                return;
            }

            $this->info("Syncing data for user {$userId}...");

            // Sync metrics
            $this->task('Syncing metrics', function () use ($userId, $days) {
                return $this->seoAnalyticsService->syncMetricsForUser($userId, $days);
            });

            // Sync pages
            $this->task('Syncing pages', function () use ($userId) {
                return $this->seoAnalyticsService->syncPagesForUser($userId, 7);
            });

            // Generate recommendations
            $this->task('Generating recommendations', function () use ($userId) {
                return $this->seoAnalyticsService->generateRecommendations($userId);
            });

            $this->info("✓ Successfully synced data for user {$userId}");
            Log::info("SEO sync completed for user {$userId}");

        } catch (\Exception $e) {
            $this->error("Failed to sync user {$userId}: " . $e->getMessage());
            Log::error("SEO sync failed for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Sync data for all users with active tokens
     */
    protected function syncAllUsers($days)
    {
        $tokens = SeoToken::whereNotNull('access_token')->get();

        if ($tokens->isEmpty()) {
            $this->warn('No users with Google Search Console tokens found.');
            return;
        }

        $this->info("Found {$tokens->count()} users to sync");

        $bar = $this->output->createProgressBar($tokens->count());
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($tokens as $token) {
            try {
                if (!$token->isExpired()) {
                    $this->syncUser($token->user_id, $days);
                    $successCount++;
                } else {
                    $this->warn("\nSkipping user {$token->user_id} - token expired");
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("\nFailed to sync user {$token->user_id}: " . $e->getMessage());
                $failCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Sync completed: {$successCount} successful, {$failCount} failed");
    }
}
