<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;

interface SocialMediaPlatformInterface
{
    /**
     * Get the platform name
     */
    public function getPlatformName(): string;

    /**
     * Get the platform icon class
     */
    public function getIcon(): string;

    /**
     * Get the platform brand color
     */
    public function getBrandColor(): string;

    /**
     * Get the character limit for posts
     */
    public function getCharacterLimit(): int;

    /**
     * Check if the platform supports media uploads
     */
    public function supportsMedia(): bool;

    /**
     * Check if the platform supports native scheduling
     */
    public function supportsScheduling(): bool;

    /**
     * Get supported features for this platform
     */
    public function getSupportedFeatures(): array;

    /**
     * Check if user has connected their account
     */
    public function isAccountConnected(int $userId): bool;

    /**
     * Get the connection URL for OAuth
     */
    public function getConnectionUrl(int $userId): string;

    /**
     * Validate post content for platform-specific requirements
     */
    public function validatePost(SocialMediaPost $post): void;

    /**
     * Publish a post to the platform
     */
    public function publishPost(SocialMediaPost $post): array;

    /**
     * Schedule a post on the platform (if supported)
     */
    public function schedulePost(SocialMediaPost $post): array;

    /**
     * Get engagement metrics for a post
     */
    public function getPostMetrics(string $externalPostId, int $userId): array;

    /**
     * Delete a post from the platform
     */
    public function deletePost(string $externalPostId, int $userId): bool;
}
