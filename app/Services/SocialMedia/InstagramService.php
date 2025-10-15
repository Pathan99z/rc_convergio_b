<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Log;

class InstagramService extends BaseSocialMediaService
{
    protected string $platformName = 'Instagram';
    protected string $icon = 'fab fa-instagram';
    protected string $brandColor = '#E4405F';
    protected int $characterLimit = 2200;
    protected bool $supportsMedia = true;
    protected bool $supportsScheduling = true;
    protected array $supportedFeatures = [
        'hashtags',
        'mentions',
        'media_upload',
        'location_tagging',
        'stories',
        'reels'
    ];

    public function publishPost(SocialMediaPost $post): array
    {
        try {
            // Instagram requires media
            if (empty($post->media_urls)) {
                throw new \Exception("Instagram posts require at least one media file");
            }

            $account = SocialAccount::where('user_id', $post->user_id)
                                   ->where('platform', 'instagram')
                                   ->where('is_active', true)
                                   ->first();

            if (!$account) {
                throw new \Exception("Instagram account not connected");
            }

            // Step 1: Create media container
            $containerId = $this->createMediaContainer($post, $account);
            
            // Step 2: Publish the container
            $response = $this->makeApiRequest(
                'post',
                "https://graph.instagram.com/{$account->platform_user_id}/media_publish",
                ['creation_id' => $containerId],
                $post->user_id
            );

            return [
                'post_id' => $response['id'],
                'url' => "https://www.instagram.com/p/" . $this->getInstagramPostCode($response['id']),
                'initial_metrics' => [
                    'likes' => 0,
                    'comments' => 0,
                    'views' => 0
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Instagram publish failed", [
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
                "https://graph.instagram.com/{$externalPostId}?fields=like_count,comments_count,media_type,timestamp",
                [],
                $userId
            );

            return [
                'likes' => $response['like_count'] ?? 0,
                'comments' => $response['comments_count'] ?? 0,
                'media_type' => $response['media_type'] ?? 'IMAGE',
                'updated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error("Instagram metrics fetch failed", [
                'post_id' => $externalPostId,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    public function schedulePost(SocialMediaPost $post): array
    {
        // Instagram Graph API doesn't support native scheduling
        // You would need to use a third-party scheduler or implement your own
        throw new \Exception("Instagram native scheduling not supported. Use internal scheduling instead.");
    }

    public function deletePost(string $externalPostId, int $userId): bool
    {
        try {
            $this->makeApiRequest(
                'delete',
                "https://graph.instagram.com/{$externalPostId}",
                [],
                $userId
            );
            return true;
        } catch (\Exception $e) {
            Log::error("Instagram delete failed", [
                'post_id' => $externalPostId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function validatePost(SocialMediaPost $post): void
    {
        parent::validatePost($post);

        // Instagram-specific validations
        if (empty($post->media_urls)) {
            throw new \Exception("Instagram posts require at least one media file");
        }

        if (!empty($post->hashtags) && count($post->hashtags) > 30) {
            throw new \Exception("Instagram posts can have a maximum of 30 hashtags");
        }
    }

    /**
     * Create a media container for Instagram posting
     */
    protected function createMediaContainer(SocialMediaPost $post, SocialAccount $account): string
    {
        $content = $this->formatPostContent($post);
        $mediaUrl = $post->media_urls[0]; // Use first media URL

        $containerData = [
            'image_url' => $mediaUrl,
            'caption' => $content,
        ];

        if ($post->location) {
            $containerData['location_id'] = $post->location;
        }

        $response = $this->makeApiRequest(
            'post',
            "https://graph.instagram.com/{$account->platform_user_id}/media",
            $containerData,
            $post->user_id
        );

        return $response['id'];
    }

    /**
     * Convert Instagram media ID to post code
     */
    protected function getInstagramPostCode(string $mediaId): string
    {
        // This is a simplified version
        // In production, you'd need to use Instagram's actual algorithm
        return base64_encode($mediaId);
    }
}
