<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use Illuminate\Support\Facades\Log;

class TwitterService extends BaseSocialMediaService
{
    protected string $platformName = 'Twitter';
    protected string $icon = 'fab fa-twitter';
    protected string $brandColor = '#1DA1F2';
    protected int $characterLimit = 280;
    protected bool $supportsMedia = true;
    protected bool $supportsScheduling = false; // Twitter API doesn't support native scheduling
    protected array $supportedFeatures = [
        'hashtags',
        'mentions',
        'media_upload',
        'threads',
        'polls'
    ];

    public function publishPost(SocialMediaPost $post): array
    {
        try {
            // Format content for Twitter
            $content = $this->formatPostContent($post);
            
            // Prepare tweet data
            $tweetData = [
                'text' => $content
            ];

            // Add media if present
            if (!empty($post->media_urls)) {
                $tweetData['media'] = [
                    'media_ids' => $this->uploadMedia($post->media_urls, $post->user_id)
                ];
            }

            // Make API call to Twitter
            $response = $this->makeApiRequest(
                'post',
                'https://api.twitter.com/2/tweets',
                $tweetData,
                $post->user_id
            );

            return [
                'post_id' => $response['data']['id'],
                'url' => "https://twitter.com/user/status/{$response['data']['id']}",
                'initial_metrics' => [
                    'likes' => 0,
                    'retweets' => 0,
                    'replies' => 0,
                    'impressions' => 0
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Twitter publish failed", [
                'post_id' => $post->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPostMetrics(string $externalPostId, int $userId): array
    {
        try {
            $response = $this->makeApiRequest(
                'get',
                "https://api.twitter.com/2/tweets/{$externalPostId}?tweet.fields=public_metrics",
                [],
                $userId
            );

            $metrics = $response['data']['public_metrics'] ?? [];

            return [
                'likes' => $metrics['like_count'] ?? 0,
                'retweets' => $metrics['retweet_count'] ?? 0,
                'replies' => $metrics['reply_count'] ?? 0,
                'quotes' => $metrics['quote_count'] ?? 0,
                'impressions' => $metrics['impression_count'] ?? 0,
                'updated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error("Twitter metrics fetch failed", [
                'post_id' => $externalPostId,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    public function deletePost(string $externalPostId, int $userId): bool
    {
        try {
            $this->makeApiRequest(
                'delete',
                "https://api.twitter.com/2/tweets/{$externalPostId}",
                [],
                $userId
            );
            return true;
        } catch (\Exception $e) {
            Log::error("Twitter delete failed", [
                'post_id' => $externalPostId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function validatePost(SocialMediaPost $post): void
    {
        parent::validatePost($post);

        // Twitter-specific validations
        if (!empty($post->hashtags) && count($post->hashtags) > 10) {
            throw new \Exception("Twitter posts can have a maximum of 10 hashtags");
        }

        if (!empty($post->media_urls) && count($post->media_urls) > 4) {
            throw new \Exception("Twitter posts can have a maximum of 4 media files");
        }
    }

    /**
     * Upload media files to Twitter
     */
    protected function uploadMedia(array $mediaUrls, int $userId): array
    {
        $mediaIds = [];
        
        foreach ($mediaUrls as $url) {
            try {
                // In a real implementation, you would:
                // 1. Download the media from the URL
                // 2. Upload it to Twitter's media endpoint
                // 3. Get the media_id back
                
                // For now, we'll simulate this
                $mediaIds[] = 'simulated_media_id_' . uniqid();
                
            } catch (\Exception $e) {
                Log::warning("Failed to upload media to Twitter", [
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $mediaIds;
    }
}
