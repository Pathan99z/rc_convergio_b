<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;

class PinterestService extends BaseSocialMediaService
{
    protected string $platformName = 'Pinterest';
    protected string $icon = 'fab fa-pinterest';
    protected string $brandColor = '#BD081C';
    protected int $characterLimit = 500;
    protected bool $supportsMedia = true;
    protected bool $supportsScheduling = true;
    protected array $supportedFeatures = ['hashtags', 'image_upload', 'scheduling', 'boards'];

    public function publishPost(SocialMediaPost $post): array
    {
        return [
            'post_id' => 'pinterest_' . uniqid(),
            'url' => 'https://pinterest.com/pin/simulated',
            'initial_metrics' => ['saves' => 0, 'clicks' => 0, 'impressions' => 0]
        ];
    }

    public function getPostMetrics(string $externalPostId, int $userId): array
    {
        return ['saves' => 0, 'clicks' => 0, 'impressions' => 0, 'updated_at' => now()->toISOString()];
    }
}
