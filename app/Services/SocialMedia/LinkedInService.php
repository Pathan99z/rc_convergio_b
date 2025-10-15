<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Log;

class LinkedInService extends BaseSocialMediaService
{
    protected string $platformName = 'LinkedIn';
    protected string $icon = 'fab fa-linkedin';
    protected string $brandColor = '#0A66C2';
    protected int $characterLimit = 3000;
    protected bool $supportsMedia = true;
    protected bool $supportsScheduling = false;
    protected array $supportedFeatures = [
        'hashtags',
        'mentions',
        'media_upload',
        'articles',
        'polls',
        'documents'
    ];

    public function publishPost(SocialMediaPost $post): array
    {
        try {
            $account = SocialAccount::where('user_id', $post->user_id)
                                   ->where('platform', 'linkedin')
                                   ->where('is_active', true)
                                   ->first();

            if (!$account) {
                throw new \Exception("LinkedIn account not connected");
            }

            $content = $this->formatPostContent($post);
            
            // Prepare post data for LinkedIn Share API
            $shareData = [
                'author' => 'urn:li:person:' . $account->platform_user_id,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $content
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            // Add media if present
            if (!empty($post->media_urls)) {
                $shareData['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
                $shareData['specificContent']['com.linkedin.ugc.ShareContent']['media'] = 
                    $this->prepareMediaForLinkedIn($post->media_urls, $post->user_id);
            }

            $response = $this->makeApiRequest(
                'post',
                'https://api.linkedin.com/v2/ugcPosts',
                $shareData,
                $post->user_id
            );

            $postId = $response['id'] ?? uniqid();

        return [
                'post_id' => $postId,
                'url' => "https://www.linkedin.com/feed/update/{$postId}",
                'initial_metrics' => [
                    'likes' => 0,
                    'comments' => 0,
                    'shares' => 0,
                    'impressions' => 0
                ]
            ];

        } catch (\Exception $e) {
            Log::error("LinkedIn publish failed", [
                'post_id' => $post->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPostMetrics(string $externalPostId, int $userId): array
    {
        try {
            // LinkedIn's analytics API requires additional permissions
            // This is a simplified version
            $response = $this->makeApiRequest(
                'get',
                "https://api.linkedin.com/v2/socialActions/{$externalPostId}",
                [],
                $userId
            );

            return [
                'likes' => $response['likesSummary']['totalLikes'] ?? 0,
                'comments' => $response['commentsSummary']['totalComments'] ?? 0,
                'shares' => $response['sharesSummary']['totalShares'] ?? 0,
                'updated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error("LinkedIn metrics fetch failed", [
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
                "https://api.linkedin.com/v2/ugcPosts/{$externalPostId}",
                [],
                $userId
            );
            return true;
        } catch (\Exception $e) {
            Log::error("LinkedIn delete failed", [
                'post_id' => $externalPostId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function validatePost(SocialMediaPost $post): void
    {
        parent::validatePost($post);

        // LinkedIn-specific validations
        if (!empty($post->hashtags) && count($post->hashtags) > 3) {
            Log::warning("LinkedIn recommends using no more than 3 hashtags for optimal engagement");
        }

        if (!empty($post->media_urls) && count($post->media_urls) > 9) {
            throw new \Exception("LinkedIn posts can have a maximum of 9 images");
        }
    }

    /**
     * Prepare media for LinkedIn posting
     */
    protected function prepareMediaForLinkedIn(array $mediaUrls, int $userId): array
    {
        $media = [];
        
        foreach ($mediaUrls as $url) {
            try {
                // In a real implementation:
                // 1. Register upload with LinkedIn
                // 2. Upload the image binary
                // 3. Get the asset URN
                
                // Simulate media preparation
                $media[] = [
                    'status' => 'READY',
                    'description' => [
                        'text' => ''
                    ],
                    'media' => 'urn:li:digitalmediaAsset:' . uniqid(),
                    'title' => [
                        'text' => ''
                    ]
                ];
                
            } catch (\Exception $e) {
                Log::warning("Failed to prepare media for LinkedIn", [
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $media;
    }
}
