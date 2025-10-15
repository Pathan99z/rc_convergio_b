<?php

namespace App\Console\Commands;

use App\Models\SocialMediaPost;
use App\Services\SocialMediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledSocialMediaPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'social-media:publish-scheduled
                            {--limit=50 : Maximum number of posts to publish in this run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish scheduled social media posts that are due';

    protected SocialMediaService $socialMediaService;

    /**
     * Create a new command instance.
     */
    public function __construct(SocialMediaService $socialMediaService)
    {
        parent::__construct();
        $this->socialMediaService = $socialMediaService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting to publish scheduled social media posts...');

        $limit = (int) $this->option('limit');

        // Get scheduled posts that are due (scheduled_at is in the past or now)
        $posts = SocialMediaPost::where('status', 'scheduled')
                                ->whereNotNull('scheduled_at')
                                ->where('scheduled_at', '<=', now())
                                ->limit($limit)
                                ->get();

        if ($posts->isEmpty()) {
            $this->info('No scheduled posts to publish at this time.');
            return Command::SUCCESS;
        }

        $this->info("Found {$posts->count()} posts to publish.");

        $successCount = 0;
        $failureCount = 0;

        foreach ($posts as $post) {
            try {
                $this->line("Publishing post ID: {$post->id} for user: {$post->user_id} on {$post->platform}");

                $result = $this->socialMediaService->publishPost($post);

                if ($result['success']) {
                    $successCount++;
                    $this->info("✓ Successfully published post ID: {$post->id}");
                } else {
                    $failureCount++;
                    $this->error("✗ Failed to publish post ID: {$post->id} - {$result['message']}");
                }

            } catch (\Exception $e) {
                $failureCount++;
                $this->error("✗ Exception publishing post ID: {$post->id} - {$e->getMessage()}");
                
                Log::error('Failed to publish scheduled post', [
                    'post_id' => $post->id,
                    'user_id' => $post->user_id,
                    'platform' => $post->platform,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("Publishing complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Posts Processed', $posts->count()],
                ['Successfully Published', $successCount],
                ['Failed', $failureCount],
            ]
        );

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}


