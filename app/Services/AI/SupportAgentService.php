<?php

namespace App\Services\AI;

use App\Models\Help\Article;
use App\Models\AI\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupportAgentService
{
    private const MAX_ARTICLES = 50;
    private const TOP_SUGGESTIONS = 3;
    private const KEYWORD_WEIGHTS = [
        'title' => 3,
        'summary' => 2,
        'content' => 1,
    ];
    private const STOP_WORDS = [
        'a', 'an', 'the', 'is', 'are', 'was', 'were', 'and', 'or', 'but', 'for', 'nor', 'so', 'yet', 'at', 'by', 'in', 'of', 'on', 'to', 'with', 'can', 'i', 'my', 'me', 'you', 'your', 'he', 'she', 'it', 'we', 'they', 'what', 'where', 'when', 'why', 'how', 'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'may', 'might', 'must', 'get', 'go', 'say', 'see', 'make', 'know', 'take', 'come', 'think', 'look', 'want', 'give', 'use', 'find', 'tell', 'ask', 'work', 'seem', 'feel', 'try', 'leave', 'call', 'into', 'from', 'about', 'through', 'after', 'before', 'up', 'down', 'out', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 's', 't', 'just', 'don', 'should', 'now', 'd', 'll', 'm', 'o', 're', 've', 'y', 'ain', 'aren', 'couldn', 'didn', 'doesn', 'hadn', 'hasn', 'haven', 'isn', 'ma', 'mightn', 'mustn', 'needn', 'shan', 'shouldn', 'wasn', 'weren', 'won', 'wouldn'
    ];

    /**
     * Process a user message, find relevant articles, and provide professional AI response.
     */
    public function processMessage(string $message, int $tenantId, ?string $userEmail = null): array
    {
        try {
            $keywords = $this->extractKeywords($message);
            $intent = $this->analyzeIntent($message, $keywords);
            $suggestions = [];
            $confidence = 0.0;
            $solutions = [];

            // Handle greetings and short messages
            if ($this->isGreeting($message)) {
                return $this->generateGreetingResponse($intent);
            }

            if (!empty($keywords)) {
                $articles = $this->searchArticles($keywords, $tenantId);
                $rankedArticles = $this->rankArticles($articles, $keywords);
                $solutions = $this->extractSolutions($rankedArticles);
                $suggestions = $this->formatSuggestions(array_slice($rankedArticles->toArray(), 0, self::TOP_SUGGESTIONS));
                $confidence = $this->calculateConfidence($suggestions);
            }

            $response = $this->generateProfessionalResponse($intent, $solutions, $suggestions, $confidence);
            $this->logConversation($message, $response, $confidence, $tenantId, $userEmail);

            return $response;

        } catch (\Exception $e) {
            Log::error('SupportAgentService: Error processing message', [
                'message' => $message,
                'tenant_id' => $tenantId,
                'user_email' => $userEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->prepareErrorResponse();
        }
    }

    /**
     * Extract keywords from the message, filtering out stop words.
     */
    private function extractKeywords(string $message): array
    {
        $message = Str::lower($message);
        $words = preg_split('/[\s,.-]+/', $message, -1, PREG_SPLIT_NO_EMPTY);
        return array_diff($words, self::STOP_WORDS);
    }

    /**
     * Analyze user intent and topic from the message.
     */
    private function analyzeIntent(string $message, array $keywords): array
    {
        $message = Str::lower($message);
        
        // Common intent patterns
        $intents = [
            'login' => ['login', 'signin', 'sign-in', 'access', 'authenticate', 'password', 'credentials'],
            'password' => ['password', 'reset', 'forgot', 'change', 'update', 'new password'],
            'account' => ['account', 'profile', 'settings', 'user', 'personal', 'information'],
            'billing' => ['billing', 'payment', 'invoice', 'subscription', 'plan', 'charge'],
            'technical' => ['error', 'bug', 'issue', 'problem', 'not working', 'broken', 'slow'],
            'support' => ['help', 'support', 'assistance', 'contact', 'ticket', 'service'],
            'feature' => ['feature', 'functionality', 'how to', 'guide', 'tutorial', 'instruction']
        ];

        $detectedIntent = 'general';
        $maxMatches = 0;

        foreach ($intents as $intent => $patterns) {
            $matches = 0;
            foreach ($patterns as $pattern) {
                if (Str::contains($message, $pattern)) {
                    $matches++;
                }
            }
            if ($matches > $maxMatches) {
                $maxMatches = $matches;
                $detectedIntent = $intent;
            }
        }

        return [
            'intent' => $detectedIntent,
            'topic' => $this->getTopicFromIntent($detectedIntent),
            'keywords' => $keywords,
            'confidence' => $maxMatches > 0 ? min($maxMatches / count($intents[$detectedIntent]), 1.0) : 0.0
        ];
    }

    /**
     * Get human-readable topic from intent.
     */
    private function getTopicFromIntent(string $intent): string
    {
        $topics = [
            'login' => 'logging in',
            'password' => 'password issues',
            'account' => 'account management',
            'billing' => 'billing and payments',
            'technical' => 'technical issues',
            'support' => 'getting support',
            'feature' => 'using features',
            'general' => 'general assistance'
        ];

        return $topics[$intent] ?? 'general assistance';
    }

    /**
     * Search for relevant help articles based on keywords and tenant.
     */
    private function searchArticles(array $keywords, int $tenantId): Collection
    {
        $query = Article::query()
            ->forTenant($tenantId)
            ->published()
            ->limit(self::MAX_ARTICLES);

        foreach ($keywords as $keyword) {
            $query->orWhere(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('summary', 'like', "%{$keyword}%")
                  ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        return $query->get();
    }

    /**
     * Rank articles by relevance based on keyword frequency and weights.
     */
    private function rankArticles(Collection $articles, array $keywords): Collection
    {
        return $articles->map(function ($article) use ($keywords) {
            $score = 0;
            $text = [
                'title' => Str::lower($article->title),
                'summary' => Str::lower($article->summary ?? ''),
                'content' => Str::lower($article->content),
            ];

            foreach ($keywords as $keyword) {
                foreach (self::KEYWORD_WEIGHTS as $field => $weight) {
                    $score += Str::substrCount($text[$field], $keyword) * $weight;
                }
            }
            $article->relevance_score = $score;
            return $article;
        })
        ->sortByDesc('relevance_score')
        ->filter(fn ($article) => $article->relevance_score > 0);
    }

    /**
     * Extract solutions and steps from articles.
     */
    private function extractSolutions(Collection $articles): array
    {
        $solutions = [];

        foreach ($articles->take(3) as $article) {
            $content = $article->content;
            
            // Extract step-by-step solutions
            $steps = $this->extractStepsFromContent($content);
            
            if (!empty($steps)) {
                $solutions[] = [
                    'title' => $article->title,
                    'steps' => $steps,
                    'summary' => $article->summary,
                    'url' => $this->generateArticleUrl($article),
                    'score' => round($article->relevance_score * 10, 1)
                ];
            }
        }

        return $solutions;
    }

    /**
     * Extract step-by-step instructions from article content.
     */
    private function extractStepsFromContent(string $content): array
    {
        $steps = [];
        
        // Clean HTML content first
        $cleanContent = strip_tags($content);
        $cleanContent = html_entity_decode($cleanContent, ENT_QUOTES, 'UTF-8');
        $cleanContent = preg_replace('/\s+/', ' ', $cleanContent); // Normalize whitespace
        
        // Look for numbered lists, bullet points, or step patterns
        $patterns = [
            '/\d+\.\s*([^.\n]+(?:\.|$))/',  // 1. Step description
            '/•\s*([^.\n]+(?:\.|$))/',      // • Bullet point
            '/-\s*([^.\n]+(?:\.|$))/',      // - Dash point
            '/Step\s+\d+[:\-]\s*([^.\n]+(?:\.|$))/i', // Step 1: Description
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $cleanContent, $matches)) {
                foreach ($matches[1] as $match) {
                    $step = trim($match);
                    // Clean up the step text
                    $step = preg_replace('/\s+/', ' ', $step);
                    if (strlen($step) > 15 && strlen($step) < 200 && !preg_match('/^<[^>]+>/', $step)) {
                        $steps[] = $step;
                    }
                }
            }
        }

        // If no structured steps found, extract key sentences
        if (empty($steps)) {
            $sentences = preg_split('/[.!?]+/', $cleanContent);
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (strlen($sentence) > 25 && strlen($sentence) < 150 && !preg_match('/^<[^>]+>/', $sentence)) {
                    $steps[] = $sentence;
                }
                if (count($steps) >= 5) break; // Limit to 5 key points
            }
        }

        return array_slice($steps, 0, 5); // Return max 5 steps
    }

    /**
     * Generate professional AI response with direct solutions.
     */
    private function generateProfessionalResponse(array $intent, array $solutions, array $suggestions, float $confidence): array
    {
        if (empty($solutions)) {
            return $this->prepareFallbackResponse($intent);
        }

        // Generate a human-like, professional response
        $response = "I understand you're having trouble with " . $intent['topic'] . ". Let me help you with that.\n\n";

        // Take only the best solution and format it professionally
        $bestSolution = $solutions[0];
        $response .= "Here's how to resolve this:\n\n";
        
        foreach ($bestSolution['steps'] as $stepIndex => $step) {
            $response .= ($stepIndex + 1) . ". " . $step . "\n";
        }

        $response .= "\nTry these steps and let me know if you need any clarification or if the issue persists.";

        return [
            'message' => $response,
            'suggestions' => $suggestions, // Keep for backward compatibility
            'confidence' => $confidence,
            'intent' => $intent['intent'],
            'topic' => $intent['topic'],
            'solutions_provided' => count($solutions),
            'response_type' => 'professional_ai'
        ];
    }

    /**
     * Prepare fallback response when no solutions found.
     */
    private function prepareFallbackResponse(array $intent): array
    {
        $fallbackMessages = [
            'login' => "I understand you're having login issues. While I don't have specific solutions for your case, I'd recommend trying to reset your password first. If that doesn't work, our support team can help you get back into your account.",
            'password' => "I can help with password issues. Try using the 'Forgot Password' feature on the login page. If you're still having trouble, I can connect you with our support team for personalized assistance.",
            'account' => "I'd be happy to help with your account issue. Since this might require specific account details, I recommend reaching out to our support team who can provide personalized assistance.",
            'billing' => "For billing questions, our billing team has the most up-to-date information and can provide the best assistance. I can help you get in touch with them.",
            'technical' => "I understand you're experiencing a technical issue. To help you better, could you provide more details about what's happening? I can then connect you with our technical support team.",
            'support' => "I'm here to help! Could you tell me more about what you need assistance with? I can either provide solutions or connect you with the right support team member.",
            'feature' => "I'd love to help you with that feature. Could you provide more details about what you're trying to accomplish? I can then guide you through it or connect you with someone who can.",
            'general' => "I'm here to help! Could you provide more details about what you need assistance with? I can either provide solutions or connect you with our support team."
        ];

        $message = $fallbackMessages[$intent['intent']] ?? $fallbackMessages['general'];

        return [
            'message' => $message,
            'suggestions' => [],
            'confidence' => 0.0,
            'intent' => $intent['intent'],
            'topic' => $intent['topic'],
            'solutions_provided' => 0,
            'response_type' => 'fallback'
        ];
    }

    /**
     * Format suggestions for backward compatibility.
     */
    private function formatSuggestions(array $rankedArticles): array
    {
        return array_map(function ($article) {
            return [
                'title' => $article['title'],
                'summary' => $article['summary'],
                'url' => $this->generateArticleUrl($article),
                'score' => round($article['relevance_score'] * 10, 1),
            ];
        }, $rankedArticles);
    }

    /**
     * Generate proper article URL to prevent 404 errors.
     */
    private function generateArticleUrl($article): string
    {
        // Check if it's an array (from formatSuggestions) or object (from extractSolutions)
        $slug = is_array($article) ? $article['slug'] : $article->slug;
        $tenantId = is_array($article) ? ($article['tenant_id'] ?? 1) : ($article->tenant_id ?? 1);
        
        // Use the public help articles API endpoint with tenant parameter
        return "http://127.0.0.1:8000/api/help/articles/{$slug}?tenant={$tenantId}";
    }

    /**
     * Calculate overall confidence based on suggestion scores.
     */
    private function calculateConfidence(array $suggestions): float
    {
        if (empty($suggestions)) {
            return 0.0;
        }
        $totalScore = array_sum(array_column($suggestions, 'score'));
        $averageScore = $totalScore / count($suggestions);
        return round($averageScore, 1);
    }

    /**
     * Log the conversation to the database.
     */
    private function logConversation(string $message, array $response, float $confidence, int $tenantId, ?string $userEmail): void
    {
        try {
            Conversation::create([
                'tenant_id' => $tenantId,
                'user_email' => $userEmail,
                'message' => $message,
                'suggestions' => $response['suggestions'] ?? [],
                'confidence' => $confidence,
            ]);
        } catch (\Exception $e) {
            Log::error('SupportAgentService: Failed to log conversation', [
                'message' => $message,
                'tenant_id' => $tenantId,
                'user_email' => $userEmail,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if the message is a greeting or short message.
     */
    private function isGreeting(string $message): bool
    {
        $message = Str::lower(trim($message));
        $greetings = [
            'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening',
            'greetings', 'howdy', 'what\'s up', 'sup', 'yo', 'hiya', 'how are you',
            'how do you do', 'nice to meet you', 'good to see you'
        ];
        
        // Check for exact matches
        if (in_array($message, $greetings)) {
            return true;
        }
        
        // Check for short messages (1-2 words)
        $words = explode(' ', $message);
        if (count($words) <= 2 && strlen($message) <= 10) {
            return true;
        }
        
        return false;
    }

    /**
     * Generate a friendly greeting response.
     */
    private function generateGreetingResponse(array $intent): array
    {
        $greetings = [
            "Hi! I'm here to help you find success. What are you hoping to achieve today?",
            "Hello! I'm your AI support assistant. How can I help you with your account, billing, or any technical questions?",
            "Hi there! Welcome to our support center. What can I assist you with today?",
            "Hello! I'm here to help you with any questions or issues you might have. What would you like to know?",
            "Hi! Great to see you! I can help with login issues, password resets, account settings, billing questions, or any other support needs. What can I help you with?"
        ];
        
        $randomGreeting = $greetings[array_rand($greetings)];
        
        return [
            'message' => $randomGreeting,
            'suggestions' => [],
            'confidence' => 100.0,
            'intent' => 'greeting',
            'topic' => 'greeting',
            'solutions_provided' => 0,
            'response_type' => 'greeting'
        ];
    }

    /**
     * Process article content and generate human-like AI response.
     */
    public function processArticleContent(\App\Models\Help\Article $article, int $tenantId, ?string $userEmail = null): array
    {
        try {
            // Extract solutions from the article
            $solutions = $this->extractSolutionsFromArticle($article);
            
            // Generate a human-like response based on the article
            $response = $this->generateArticleBasedResponse($article, $solutions);
            
            // Log the conversation
            $this->logConversation(
                "User requested help with: " . $article->title,
                $response,
                95.0, // High confidence since it's from a specific article
                $tenantId,
                $userEmail
            );

            return $response;

        } catch (\Exception $e) {
            Log::error('SupportAgentService: Error processing article content', [
                'article_id' => $article->id,
                'tenant_id' => $tenantId,
                'user_email' => $userEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->prepareErrorResponse();
        }
    }

    /**
     * Extract solutions from a specific article.
     */
    private function extractSolutionsFromArticle(\App\Models\Help\Article $article): array
    {
        $steps = $this->extractStepsFromContent($article->content);
        
        if (!empty($steps)) {
            return [[
                'title' => $article->title,
                'steps' => $steps,
                'summary' => $article->summary,
                'url' => $this->generateArticleUrl($article),
                'score' => 95.0
            ]];
        }
        
        return [];
    }

    /**
     * Generate human-like response based on article content.
     */
    private function generateArticleBasedResponse(\App\Models\Help\Article $article, array $solutions): array
    {
        if (empty($solutions)) {
            return [
                'message' => "I found information about '{$article->title}'. " . $article->summary . " Let me help you with that.",
                'suggestions' => [],
                'confidence' => 95.0,
                'intent' => 'article_help',
                'topic' => $article->title,
                'solutions_provided' => 0,
                'response_type' => 'article_based'
            ];
        }

        $bestSolution = $solutions[0];
        $response = "I found the perfect solution for you! Here's how to resolve '{$article->title}':\n\n";
        
        foreach ($bestSolution['steps'] as $stepIndex => $step) {
            $response .= ($stepIndex + 1) . ". " . $step . "\n";
        }

        $response .= "\nThis should help you resolve the issue. If you need any clarification or if the problem persists, feel free to ask me more questions!";

        return [
            'message' => $response,
            'suggestions' => [],
            'confidence' => 95.0,
            'intent' => 'article_help',
            'topic' => $article->title,
            'solutions_provided' => count($solutions),
            'response_type' => 'article_based'
        ];
    }

    /**
     * Handle missing article requests professionally.
     */
    public function handleMissingArticle(string $articleSlug, int $tenantId, ?string $userEmail = null): array
    {
        try {
            // Try to find related articles based on the slug
            $relatedArticles = $this->findRelatedArticles($articleSlug, $tenantId);
            
            // Generate a professional response
            $response = $this->generateMissingArticleResponse($articleSlug, $relatedArticles);
            
            // Log the conversation
            $this->logConversation(
                "User requested help with: " . $articleSlug,
                $response,
                75.0, // Medium confidence since we're providing alternatives
                $tenantId,
                $userEmail
            );

            return $response;

        } catch (\Exception $e) {
            Log::error('SupportAgentService: Error handling missing article', [
                'article_slug' => $articleSlug,
                'tenant_id' => $tenantId,
                'user_email' => $userEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->prepareErrorResponse();
        }
    }

    /**
     * Find related articles based on the requested slug.
     */
    private function findRelatedArticles(string $articleSlug, int $tenantId): Collection
    {
        // Extract keywords from the slug
        $keywords = $this->extractKeywords($articleSlug);
        
        if (empty($keywords)) {
            return collect();
        }

        // Search for articles with similar keywords
        $articles = Article::forTenant($tenantId)
            ->published()
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'like', "%{$keyword}%")
                          ->orWhere('summary', 'like', "%{$keyword}%")
                          ->orWhere('content', 'like', "%{$keyword}%");
                }
            })
            ->limit(3)
            ->get();

        return $articles;
    }

    /**
     * Generate professional response for missing articles.
     */
    private function generateMissingArticleResponse(string $articleSlug, Collection $relatedArticles): array
    {
        $topic = str_replace('-', ' ', $articleSlug);
        $topic = ucwords($topic);

        if ($relatedArticles->isNotEmpty()) {
            $response = "I couldn't find the exact article you're looking for, but I found some related solutions that might help with {$topic}:\n\n";
            
            foreach ($relatedArticles as $index => $article) {
                $response .= ($index + 1) . ". **{$article->title}**\n";
                $response .= "   {$article->summary}\n\n";
            }
            
            $response .= "Would you like me to provide detailed steps for any of these solutions?";
            
            // Format suggestions for backward compatibility
            $suggestions = $relatedArticles->map(function ($article) {
                return [
                    'title' => $article->title,
                    'summary' => $article->summary,
                    'url' => $this->generateArticleUrl($article),
                    'score' => 80.0
                ];
            })->toArray();
            
        } else {
            $response = "I couldn't find specific information about {$topic} in our knowledge base. ";
            $response .= "However, I'm here to help! Could you tell me more about what you're trying to accomplish? ";
            $response .= "I can provide general guidance or connect you with our support team for personalized assistance.";
            
            $suggestions = [];
        }

        return [
            'message' => $response,
            'suggestions' => $suggestions,
            'confidence' => 75.0,
            'intent' => 'missing_article',
            'topic' => $topic,
            'solutions_provided' => $relatedArticles->count(),
            'response_type' => 'missing_article_help'
        ];
    }

    /**
     * Prepare error response.
     */
    private function prepareErrorResponse(): array
    {
        return [
            'message' => 'I apologize, but I encountered an error while processing your request. Please try again or create a support ticket for assistance.',
            'suggestions' => [],
            'confidence' => 0.0,
            'intent' => 'error',
            'topic' => 'system error',
            'solutions_provided' => 0,
            'response_type' => 'error'
        ];
    }
}