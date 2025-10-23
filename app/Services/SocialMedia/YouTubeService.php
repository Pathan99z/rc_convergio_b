<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;

class YouTubeService extends BaseSocialMediaService
{
    protected string $platformName = 'YouTube';
    protected string $icon = 'fab fa-youtube';
    protected string $brandColor = '#FF0000';
    protected int $characterLimit = 5000;
    protected bool $supportsMedia = true;
    protected bool $supportsScheduling = true;
    protected array $supportedFeatures = ['hashtags', 'video_upload', 'scheduling', 'thumbnails'];

    public function publishPost(SocialMediaPost $post): array
    {
        return [
            'post_id' => 'youtube_' . uniqid(),
            'url' => 'https://youtube.com/watch?v=simulated',
            'initial_metrics' => ['views' => 0, 'likes' => 0, 'comments' => 0]
        ];
    }

    public function getPostMetrics(string $externalPostId, int $userId): array
    {
        return ['views' => 0, 'likes' => 0, 'comments' => 0, 'updated_at' => now()->toISOString()];
    }
}
