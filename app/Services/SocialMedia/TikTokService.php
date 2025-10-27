<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;

class TikTokService extends BaseSocialMediaService
{
    protected string $platformName = 'TikTok';
    protected string $icon = 'fab fa-tiktok';
    protected string $brandColor = '#000000';
    protected int $characterLimit = 2200;
    protected bool $supportsMedia = true;
    protected bool $supportsScheduling = false;
    protected array $supportedFeatures = ['hashtags', 'video_upload', 'effects', 'sounds'];

    public function publishPost(SocialMediaPost $post): array
    {
        return [
            'post_id' => 'tiktok_' . uniqid(),
            'url' => 'https://tiktok.com/@user/video/simulated',
            'initial_metrics' => ['views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0]
        ];
    }

    public function getPostMetrics(string $externalPostId, int $userId): array
    {
        return ['views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'updated_at' => now()->toISOString()];
    }
}
