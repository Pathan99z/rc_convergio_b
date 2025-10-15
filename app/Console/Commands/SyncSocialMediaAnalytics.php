<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SocialMediaAnalyticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSocialMediaAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social-media:sync-analytics
                            {--user= : Specific user ID to sync analytics for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync social media analytics from all connected platforms';

    protected SocialMediaAnalyticsService $analyticsService;

    /**
     * Create a new command instance.
     */
    public function __construct(SocialMediaAnalyticsService $analyticsService)
    {
        parent::__construct();
        $this->analyticsService = $analyticsService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting social media analytics sync...');

        $userId = $this->option('user');

        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User not found: {$userId}");
                return Command::FAILURE;
            }
            $users = collect([$user]);
        } else {
            // Sync for all users with social accounts
            $users = User::whereHas('socialAccounts')->get();
        }

        if ($users->isEmpty()) {
            $this->info('No users with social accounts found.');
            return Command::SUCCESS;
        }

        $this->info("Syncing analytics for {$users->count()} user(s)...");

        $successCount = 0;
        $failureCount = 0;

        foreach ($users as $user) {
            try {
                $this->line("Syncing analytics for user: {$user->name} (ID: {$user->id})");

                $results = $this->analyticsService->fetchAllAnalytics($user->id);

                foreach ($results as $platform => $result) {
                    if ($result['success']) {
                        $successCount++;
                        $this->info("  ✓ {$platform}: Success");
                    } else {
                        $failureCount++;
                        $this->warn("  ✗ {$platform}: Failed - {$result['error']}");
                    }
                }

            } catch (\Exception $e) {
                $failureCount++;
                $this->error("✗ Failed to sync analytics for user: {$user->id} - {$e->getMessage()}");
                
                Log::error('Failed to sync user analytics', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("Analytics sync complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Users Processed', $users->count()],
                ['Successful Platform Syncs', $successCount],
                ['Failed Platform Syncs', $failureCount],
            ]
        );

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}


