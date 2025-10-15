<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

abstract class BaseSocialMediaService implements SocialMediaPlatformInterface
{
    protected string $platformName;
    protected string $icon;
    protected string $brandColor;
    protected int $characterLimit;
    protected bool $supportsMedia = true;
    protected bool $supportsScheduling = false;
    protected array $supportedFeatures = [];

    public function getPlatformName(): string
    {
        return $this->platformName;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getBrandColor(): string
    {
        return $this->brandColor;
    }

    public function getCharacterLimit(): int
    {
        return $this->characterLimit;
    }

    public function supportsMedia(): bool
    {
        return $this->supportsMedia;
    }

    public function supportsScheduling(): bool
    {
        return $this->supportsScheduling;
    }

    public function getSupportedFeatures(): array
    {
        return $this->supportedFeatures;
    }

    public function isAccountConnected(int $userId): bool
    {
        try {
            // Check if user has stored OAuth tokens for this platform
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            $tokenKey = $this->getTokenCacheKey($userId);
            $hasCache = false;
            try {
                $hasCache = Cache::has($tokenKey);
            } catch (\Exception $e) {
                // Cache might not be available
            }
            return $hasCache || $this->hasStoredTokens($user);
        } catch (\Exception $e) {
            // If database is not available, return false (not connected)
            return false;
        }
    }

    public function getConnectionUrl(int $userId): string
    {
        try {
            // Return OAuth connection URL - this would be implemented per platform
            return route('oauth.' . strtolower($this->platformName) . '.redirect', ['user_id' => $userId]);
        } catch (\Exception $e) {
            // If route doesn't exist, return a placeholder URL
            return '#connect-' . strtolower($this->platformName);
        }
    }

    public function validatePost(SocialMediaPost $post): void
    {
        // Basic validation - can be overridden by specific platforms
        if (strlen($post->content) > $this->getCharacterLimit()) {
            throw new \Exception("Content exceeds character limit for {$this->platformName}");
        }

        if (!$this->supportsMedia() && !empty($post->media_urls)) {
            throw new \Exception("{$this->platformName} does not support media uploads");
        }
    }

    public function schedulePost(SocialMediaPost $post): array
    {
        if (!$this->supportsScheduling()) {
            throw new \Exception("{$this->platformName} does not support native scheduling");
        }

        // Default implementation - should be overridden by platforms that support scheduling
        throw new \Exception("Scheduling not implemented for {$this->platformName}");
    }

    public function deletePost(string $externalPostId, int $userId): bool
    {
        // Default implementation - should be overridden by specific platforms
        return false;
    }

    /**
     * Get cached access token for user
     */
    protected function getAccessToken(int $userId): ?string
    {
        // First try cache
        $tokenKey = $this->getTokenCacheKey($userId);
        $cachedToken = Cache::get($tokenKey);
        
        if ($cachedToken) {
            return $cachedToken;
        }
        
        // If not in cache, get from database
        $account = \App\Models\SocialAccount::where('user_id', $userId)
                                            ->where('platform', strtolower($this->platformName))
                                            ->where('is_active', true)
                                            ->first();
        
        if (!$account || !$account->access_token) {
            return null;
        }
        
        // Decrypt and cache the token
        $token = decrypt($account->access_token);
        
        // Cache for a short time (to avoid decryption overhead)
        $expiresIn = $account->expires_at ? $account->expires_at->diffInSeconds(now()) : 3600;
        Cache::put($tokenKey, $token, max(60, $expiresIn));
        
        return $token;
    }

    /**
     * Store access token in cache
     */
    protected function storeAccessToken(int $userId, string $token, int $expiresIn = 3600): void
    {
        $tokenKey = $this->getTokenCacheKey($userId);
        Cache::put($tokenKey, $token, $expiresIn);
    }

    /**
     * Get token cache key
     */
    protected function getTokenCacheKey(int $userId): string
    {
        return "social_media_token_{$this->platformName}_{$userId}";
    }

    /**
     * Check if user has stored OAuth tokens
     */
    protected function hasStoredTokens(User $user): bool
    {
        // Check if user has an active social account for this platform
        $account = \App\Models\SocialAccount::where('user_id', $user->id)
                                            ->where('platform', strtolower($this->platformName))
                                            ->where('is_active', true)
                                            ->first();
        
        return $account !== null && !$account->isTokenExpired();
    }

    /**
     * Make authenticated API request
     */
    protected function makeApiRequest(string $method, string $url, array $data = [], int $userId = null): array
    {
        $headers = ['Content-Type' => 'application/json'];
        
        if ($userId) {
            $token = $this->getAccessToken($userId);
            if ($token) {
                $headers['Authorization'] = "Bearer {$token}";
            }
        }

        $response = Http::withHeaders($headers)->$method($url, $data);

        if (!$response->successful()) {
            throw new \Exception("API request failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Format post content for the platform
     */
    protected function formatPostContent(SocialMediaPost $post): string
    {
        $content = $post->content;
        
        // Add hashtags if they exist
        if (!empty($post->hashtags)) {
            $content .= "\n\n" . implode(' ', $post->hashtags);
        }

        // Add mentions if they exist
        if (!empty($post->mentions)) {
            $content .= "\n" . implode(' ', $post->mentions);
        }

        return trim($content);
    }
}
