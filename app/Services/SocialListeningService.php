<?php

namespace App\Services;

use App\Models\ListeningKeyword;
use App\Models\ListeningMention;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialListeningService
{
    /**
     * Create a new listening keyword
     */
    public function createKeyword(int $userId, string $keyword, array $platforms = [], array $settings = []): ListeningKeyword
    {
        return ListeningKeyword::create([
            'user_id' => $userId,
            'keyword' => $keyword,
            'platforms' => $platforms,
            'settings' => $settings,
            'is_active' => true,
            'mention_count' => 0
        ]);
    }

    /**
     * Search for mentions across all platforms
     */
    public function searchMentions(ListeningKeyword $keyword): array
    {
        $mentions = [];
        $platforms = $keyword->platforms ?? ['twitter', 'facebook', 'instagram', 'linkedin'];

        foreach ($platforms as $platform) {
            try {
                $platformMentions = $this->searchPlatformMentions($keyword, $platform);
                $mentions = array_merge($mentions, $platformMentions);
            } catch (\Exception $e) {
                Log::error("Failed to search mentions on {$platform}", [
                    'keyword_id' => $keyword->id,
                    'keyword' => $keyword->keyword,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Save new mentions to database
        foreach ($mentions as $mention) {
            $this->saveMention($keyword, $mention);
        }

        // Update keyword's last checked time and mention count
        $keyword->update([
            'last_checked_at' => now(),
            'mention_count' => $keyword->mentions()->count()
        ]);

        return $mentions;
    }

    /**
     * Search for mentions on a specific platform
     */
    protected function searchPlatformMentions(ListeningKeyword $keyword, string $platform): array
    {
        $account = SocialAccount::where('user_id', $keyword->user_id)
                               ->where('platform', $platform)
                               ->where('is_active', true)
                               ->first();

        if (!$account) {
            return [];
        }

        switch ($platform) {
            case 'twitter':
                return $this->searchTwitterMentions($keyword, $account);
            
            case 'facebook':
                return $this->searchFacebookMentions($keyword, $account);
            
            case 'instagram':
                return $this->searchInstagramMentions($keyword, $account);
            
            case 'linkedin':
                return $this->searchLinkedInMentions($keyword, $account);
            
            default:
                return [];
        }
    }

    /**
     * Search Twitter for mentions
     */
    protected function searchTwitterMentions(ListeningKeyword $keyword, SocialAccount $account): array
    {
        try {
            // Use Twitter API v2 search endpoint
            $response = Http::withToken(decrypt($account->access_token))
                           ->get('https://api.twitter.com/2/tweets/search/recent', [
                               'query' => $keyword->keyword,
                               'max_results' => 100,
                               'tweet.fields' => 'created_at,public_metrics,author_id',
                               'expansions' => 'author_id',
                               'user.fields' => 'username,name'
                           ]);

            if (!$response->successful()) {
                throw new \Exception('Twitter API error: ' . $response->body());
            }

            $data = $response->json();
            $mentions = [];

            $users = collect($data['includes']['users'] ?? [])->keyBy('id');

            foreach ($data['data'] ?? [] as $tweet) {
                $author = $users->get($tweet['author_id']);
                
                $mentions[] = [
                    'platform' => 'twitter',
                    'external_id' => $tweet['id'],
                    'content' => $tweet['text'],
                    'author_name' => $author['name'] ?? 'Unknown',
                    'author_handle' => '@' . ($author['username'] ?? 'unknown'),
                    'author_url' => 'https://twitter.com/' . ($author['username'] ?? ''),
                    'post_url' => "https://twitter.com/user/status/{$tweet['id']}",
                    'sentiment' => $this->analyzeSentiment($tweet['text']),
                    'engagement' => [
                        'likes' => $tweet['public_metrics']['like_count'] ?? 0,
                        'retweets' => $tweet['public_metrics']['retweet_count'] ?? 0,
                        'replies' => $tweet['public_metrics']['reply_count'] ?? 0,
                    ],
                    'mentioned_at' => $tweet['created_at'] ?? now()->toIso8601String()
                ];
            }

            return $mentions;

        } catch (\Exception $e) {
            Log::error('Twitter mention search failed', [
                'keyword' => $keyword->keyword,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Search Facebook for mentions (simplified demo version)
     */
    protected function searchFacebookMentions(ListeningKeyword $keyword, SocialAccount $account): array
    {
        // Note: Facebook's public search is limited. This is a simplified demo.
        // In production, you would need appropriate permissions and use Facebook's Graph API search
        return [];
    }

    /**
     * Search Instagram for mentions (simplified demo version)
     */
    protected function searchInstagramMentions(ListeningKeyword $keyword, SocialAccount $account): array
    {
        // Note: Instagram's public search is very limited. This is a simplified demo.
        // In production, you might use hashtag APIs or mention APIs with business accounts
        return [];
    }

    /**
     * Search LinkedIn for mentions (simplified demo version)
     */
    protected function searchLinkedInMentions(ListeningKeyword $keyword, SocialAccount $account): array
    {
        // Note: LinkedIn's search API is very limited. This is a simplified demo.
        // In production, you would need specific API access
        return [];
    }

    /**
     * Save mention to database
     */
    protected function saveMention(ListeningKeyword $keyword, array $mentionData): ?ListeningMention
    {
        try {
            // Check if mention already exists
            $existing = ListeningMention::where('platform', $mentionData['platform'])
                                       ->where('external_id', $mentionData['external_id'])
                                       ->first();

            if ($existing) {
                // Update engagement metrics
                $existing->update([
                    'engagement' => $mentionData['engagement']
                ]);
                return $existing;
            }

            // Create new mention
            return ListeningMention::create([
                'listening_keyword_id' => $keyword->id,
                'user_id' => $keyword->user_id,
                'platform' => $mentionData['platform'],
                'external_id' => $mentionData['external_id'],
                'content' => $mentionData['content'],
                'author_name' => $mentionData['author_name'],
                'author_handle' => $mentionData['author_handle'],
                'author_url' => $mentionData['author_url'],
                'post_url' => $mentionData['post_url'],
                'sentiment' => $mentionData['sentiment'],
                'engagement' => $mentionData['engagement'],
                'mentioned_at' => $mentionData['mentioned_at'],
                'is_read' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save mention', [
                'keyword_id' => $keyword->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Simple sentiment analysis
     */
    protected function analyzeSentiment(string $text): string
    {
        $text = strtolower($text);
        
        $positiveWords = ['good', 'great', 'excellent', 'amazing', 'love', 'best', 'awesome', 'fantastic', 'wonderful'];
        $negativeWords = ['bad', 'terrible', 'worst', 'hate', 'awful', 'horrible', 'poor', 'disappointing'];
        
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            if (str_contains($text, $word)) {
                $positiveCount++;
            }
        }
        
        foreach ($negativeWords as $word) {
            if (str_contains($text, $word)) {
                $negativeCount++;
            }
        }
        
        if ($positiveCount > $negativeCount) {
            return 'positive';
        } elseif ($negativeCount > $positiveCount) {
            return 'negative';
        }
        
        return 'neutral';
    }

    /**
     * Get mentions for a keyword
     */
    public function getMentions(int $userId, int $keywordId, array $filters = []): array
    {
        $query = ListeningMention::where('user_id', $userId)
                                 ->where('listening_keyword_id', $keywordId)
                                 ->with('keyword');

        if (isset($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }

        if (isset($filters['sentiment'])) {
            $query->where('sentiment', $filters['sentiment']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        $mentions = $query->orderBy('mentioned_at', 'desc')
                         ->limit($filters['limit'] ?? 50)
                         ->get();

        return $mentions->map(function ($mention) {
            return [
                'id' => $mention->id,
                'platform' => $mention->platform,
                'content' => $mention->content,
                'author_name' => $mention->author_name,
                'author_handle' => $mention->author_handle,
                'post_url' => $mention->post_url,
                'sentiment' => $mention->sentiment,
                'engagement' => $mention->engagement,
                'mentioned_at' => $mention->mentioned_at->toIso8601String(),
                'is_read' => $mention->is_read,
                'keyword' => $mention->keyword->keyword
            ];
        })->toArray();
    }

    /**
     * Get sentiment summary
     */
    public function getSentimentSummary(int $userId, int $keywordId = null): array
    {
        $query = ListeningMention::where('user_id', $userId);

        if ($keywordId) {
            $query->where('listening_keyword_id', $keywordId);
        }

        $total = $query->count();
        $positive = $query->clone()->where('sentiment', 'positive')->count();
        $neutral = $query->clone()->where('sentiment', 'neutral')->count();
        $negative = $query->clone()->where('sentiment', 'negative')->count();

        return [
            'total' => $total,
            'positive' => $positive,
            'positive_percentage' => $total > 0 ? round(($positive / $total) * 100, 2) : 0,
            'neutral' => $neutral,
            'neutral_percentage' => $total > 0 ? round(($neutral / $total) * 100, 2) : 0,
            'negative' => $negative,
            'negative_percentage' => $total > 0 ? round(($negative / $total) * 100, 2) : 0,
        ];
    }
}


