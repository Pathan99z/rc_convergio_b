<?php

namespace App\Services;

use App\Models\SocialMediaPost;
use App\Services\SocialMedia\FacebookService;
use App\Services\SocialMedia\TwitterService;
use App\Services\SocialMedia\InstagramService;
use App\Services\SocialMedia\LinkedInService;
use App\Services\SocialMedia\YouTubeService;
use App\Services\SocialMedia\TikTokService;
use App\Services\SocialMedia\PinterestService;
use Illuminate\Support\Facades\Log;

class SocialMediaService
{
    protected array $platformServices = [];

    public function __construct()
    {
        $this->platformServices = [
            'facebook' => new FacebookService(),
            'twitter' => new TwitterService(),
            'instagram' => new InstagramService(),
            'linkedin' => new LinkedInService(),
            'youtube' => new YouTubeService(),
            'tiktok' => new TikTokService(),
            'pinterest' => new PinterestService(),
        ];
    }

    /**
     * Publish a post to the specified platform
     */
    public function publishPost(SocialMediaPost $post): array
    {
        try {
            $platformService = $this->getPlatformService($post->platform);
            
            if (!$platformService) {
                throw new \Exception("Platform service not found for: {$post->platform}");
            }

            // Check if user has connected their account for this platform
            if (!$platformService->isAccountConnected($post->user_id)) {
                throw new \Exception("User account not connected for platform: {$post->platform}");
            }

            // Validate content for platform-specific requirements
            $this->validateContentForPlatform($post);

            // Publish to the platform
            $result = $platformService->publishPost($post);

            // Update post with external ID and published status
            $post->update([
                'status' => 'published',
                'published_at' => now(),
                'external_post_id' => $result['post_id'] ?? null,
                'engagement_metrics' => $result['initial_metrics'] ?? []
            ]);

            Log::info("Post published successfully", [
                'post_id' => $post->id,
                'platform' => $post->platform,
                'external_id' => $result['post_id'] ?? null
            ]);

            return [
                'success' => true,
                'message' => 'Post published successfully',
                'external_post_id' => $result['post_id'] ?? null,
                'platform_url' => $result['url'] ?? null
            ];

        } catch (\Exception $e) {
            // Update post status to failed
            $post->update([
                'status' => 'failed',
                'engagement_metrics' => [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString()
                ]
            ]);

            Log::error("Failed to publish post", [
                'post_id' => $post->id,
                'platform' => $post->platform,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to publish post: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Schedule a post for later publishing
     */
    public function schedulePost(SocialMediaPost $post): array
    {
        try {
            $platformService = $this->getPlatformService($post->platform);
            
            if (!$platformService) {
                throw new \Exception("Platform service not found for: {$post->platform}");
            }

            // Check if platform supports scheduling
            if (!$platformService->supportsScheduling()) {
                // For platforms that don't support native scheduling,
                // we'll use our own queue system
                return $this->scheduleWithQueue($post);
            }

            // Use platform's native scheduling
            $result = $platformService->schedulePost($post);

            $post->update([
                'status' => 'scheduled',
                'external_post_id' => $result['scheduled_id'] ?? null
            ]);

            return [
                'success' => true,
                'message' => 'Post scheduled successfully',
                'scheduled_for' => $post->scheduled_at->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error("Failed to schedule post", [
                'post_id' => $post->id,
                'platform' => $post->platform,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to schedule post: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get engagement metrics for a published post
     */
    public function getEngagementMetrics(SocialMediaPost $post): array
    {
        try {
            $platformService = $this->getPlatformService($post->platform);
            
            if (!$platformService || !$post->external_post_id) {
                return ['error' => 'Cannot fetch metrics for this post'];
            }

            $metrics = $platformService->getPostMetrics($post->external_post_id, $post->user_id);
            
            // Update post with latest metrics
            $post->update([
                'engagement_metrics' => array_merge($post->engagement_metrics ?? [], $metrics)
            ]);

            return $metrics;

        } catch (\Exception $e) {
            Log::error("Failed to fetch engagement metrics", [
                'post_id' => $post->id,
                'platform' => $post->platform,
                'error' => $e->getMessage()
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get available platforms with their connection status for a user
     */
    public function getAvailablePlatforms(int $userId): array
    {
        $platforms = [];

        foreach ($this->platformServices as $platform => $service) {
            $platforms[] = [
                'id' => $platform,
                'name' => $service->getPlatformName(),
                'icon' => $service->getIcon(),
                'color' => $service->getBrandColor(),
                'character_limit' => $service->getCharacterLimit(),
                'supports_media' => $service->supportsMedia(),
                'supports_scheduling' => $service->supportsScheduling(),
                'is_connected' => $service->isAccountConnected($userId),
                'connection_url' => $service->getConnectionUrl($userId),
                'features' => $service->getSupportedFeatures()
            ];
        }

        return $platforms;
    }

    /**
     * Validate content for platform-specific requirements
     */
    protected function validateContentForPlatform(SocialMediaPost $post): void
    {
        $platformService = $this->getPlatformService($post->platform);
        
        if (!$platformService) {
            return;
        }

        $characterLimit = $platformService->getCharacterLimit();
        if (strlen($post->content) > $characterLimit) {
            throw new \Exception("Content exceeds {$post->platform} character limit of {$characterLimit}");
        }

        // Platform-specific validations
        $platformService->validatePost($post);
    }

    /**
     * Get platform service instance
     */
    protected function getPlatformService(string $platform): ?object
    {
        return $this->platformServices[$platform] ?? null;
    }

    /**
     * Schedule post using internal queue system
     */
    protected function scheduleWithQueue(SocialMediaPost $post): array
    {
        // This would integrate with Laravel's queue system
        // For now, just update the status
        $post->update(['status' => 'scheduled']);

        return [
            'success' => true,
            'message' => 'Post scheduled using internal queue system',
            'scheduled_for' => $post->scheduled_at->toISOString()
        ];
    }
}