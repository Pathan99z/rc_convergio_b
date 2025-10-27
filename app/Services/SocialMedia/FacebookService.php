<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use Illuminate\Support\Facades\Log;

class FacebookService extends BaseSocialMediaService
{
    protected string $platformName = 'Facebook';
    protected string $icon = 'fab fa-facebook';
    protected string $brandColor = '#1877F2';
    protected int $characterLimit = 63206;
    protected bool $supportsMedia = true;
    protected bool $supportsScheduling = true;
    protected array $supportedFeatures = [
        'hashtags',
        'mentions',
        'media_upload',
        'scheduling',
        'location_tagging',
        'audience_targeting'
    ];

    public function publishPost(SocialMediaPost $post): array
    {
        try {
            $content = $this->formatPostContent($post);
            
            $postData = [
                'message' => $content
            ];

            // Add media if present
            if (!empty($post->media_urls)) {
                $postData['attached_media'] = $this->prepareMediaForFacebook($post->media_urls, $post->user_id);
            }

            // Add location if present
            if ($post->location) {
                $postData['place'] = $post->location;
            }

            $response = $this->makeApiRequest(
                'post',
                'https://graph.facebook.com/v18.0/me/feed',
                $postData,
                $post->user_id
            );

            return [
                'post_id' => $response['id'],
                'url' => "https://facebook.com/{$response['id']}",
                'initial_metrics' => [
                    'likes' => 0,
                    'comments' => 0,
                    'shares' => 0,
                    'reactions' => 0
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Facebook publish failed", [
                'post_id' => $post->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function schedulePost(SocialMediaPost $post): array
    {
        try {
            $content = $this->formatPostContent($post);
            
            $postData = [
                'message' => $content,
                'published' => false,
                'scheduled_publish_time' => $post->scheduled_at->timestamp
            ];

            if (!empty($post->media_urls)) {
                $postData['attached_media'] = $this->prepareMediaForFacebook($post->media_urls, $post->user_id);
            }

            $response = $this->makeApiRequest(
                'post',
                'https://graph.facebook.com/v18.0/me/feed',
                $postData,
                $post->user_id
            );

            return [
                'scheduled_id' => $response['id'],
                'scheduled_for' => $post->scheduled_at->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error("Facebook scheduling failed", [
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
                "https://graph.facebook.com/v18.0/{$externalPostId}?fields=likes.summary(true),comments.summary(true),shares,reactions.summary(true)",
                [],
                $userId
            );

            return [
                'likes' => $response['likes']['summary']['total_count'] ?? 0,
                'comments' => $response['comments']['summary']['total_count'] ?? 0,
                'shares' => $response['shares']['count'] ?? 0,
                'reactions' => $response['reactions']['summary']['total_count'] ?? 0,
                'updated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error("Facebook metrics fetch failed", [
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
                "https://graph.facebook.com/v18.0/{$externalPostId}",
                [],
                $userId
            );
            return true;
        } catch (\Exception $e) {
            Log::error("Facebook delete failed", [
                'post_id' => $externalPostId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Prepare media for Facebook posting
     */
    protected function prepareMediaForFacebook(array $mediaUrls, int $userId): array
    {
        $attachedMedia = [];
        
        foreach ($mediaUrls as $url) {
            try {
                // Upload media to Facebook and get media ID
                $mediaId = $this->uploadMediaToFacebook($url, $userId);
                $attachedMedia[] = ['media_fbid' => $mediaId];
            } catch (\Exception $e) {
                Log::warning("Failed to upload media to Facebook", [
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $attachedMedia;
    }

    /**
     * Upload media to Facebook
     */
    protected function uploadMediaToFacebook(string $url, int $userId): string
    {
        // In a real implementation, you would:
        // 1. Download the media from the URL
        // 2. Upload it to Facebook's media endpoint
        // 3. Return the media ID
        
        // For now, simulate this
        return 'simulated_fb_media_id_' . uniqid();
    }
}
