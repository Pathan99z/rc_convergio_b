<?php

namespace App\Services\AI;

use App\Models\AI\CopilotConversation;
use App\Models\Help\Article;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConvergioCopilotService
{
    private const MAX_ARTICLES = 20;
    private const TOP_SUGGESTIONS = 3;
    private const KEYWORD_WEIGHTS = [
        'title' => 3,
        'summary' => 2,
        'content' => 1,
    ];

    /**
     * Process a user query and return platform guidance.
     */
    public function processQuery(string $query, int $tenantId, ?string $currentPage = null, ?int $userId = null): array
    {
        try {
            $keywords = $this->extractKeywords($query);
            $intent = $this->analyzeIntent($query, $keywords, $currentPage);
            
            // Check if this is a greeting or general question
            if ($this->isGreeting($query)) {
                return $this->generateGreetingResponse($intent, $currentPage);
            }
            
            // Check if this is a follow-up question
            $conversationContext = $this->getConversationContext($tenantId, $userId);
            $isFollowUp = $this->isFollowUpQuestion($query, $conversationContext);
            
            $platformGuidance = $this->getPlatformGuidance($intent, $keywords);
            $helpArticles = $this->searchHelpArticles($keywords, $tenantId);
            $relatedFeatures = $this->getRelatedFeatures($intent['feature'] ?? null);
            $quickActions = $this->getQuickActions($intent['feature'] ?? null, $currentPage);

            $response = $this->generateNaturalResponse($intent, $platformGuidance, $helpArticles, $relatedFeatures, $quickActions, $isFollowUp, $conversationContext);
            
            // Log the conversation
            $this->logConversation($query, $response, $tenantId, $userId);

            return $response;

        } catch (\Exception $e) {
            Log::error('ConvergioCopilotService: Error processing query', [
                'query' => $query,
                'tenant_id' => $tenantId,
                'current_page' => $currentPage,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->prepareErrorResponse();
        }
    }

    /**
     * Extract keywords from the query.
     */
    private function extractKeywords(string $query): array
    {
        $query = Str::lower($query);
        $words = preg_split('/[\s,.-]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'and', 'or', 'but', 'for', 'nor', 'so', 'yet', 'at', 'by', 'in', 'of', 'on', 'to', 'with', 'can', 'i', 'my', 'me', 'you', 'your', 'he', 'she', 'it', 'we', 'they', 'what', 'where', 'when', 'why', 'how', 'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'may', 'might', 'must', 'get', 'go', 'say', 'see', 'make', 'know', 'take', 'come', 'think', 'look', 'want', 'give', 'use', 'find', 'tell', 'ask', 'work', 'seem', 'feel', 'try', 'leave', 'call', 'into', 'from', 'about', 'through', 'after', 'before', 'up', 'down', 'out', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 's', 't', 'just', 'don', 'should', 'now', 'd', 'll', 'm', 'o', 're', 've', 'y', 'ain', 'aren', 'couldn', 'didn', 'doesn', 'hadn', 'hasn', 'haven', 'isn', 'ma', 'mightn', 'mustn', 'needn', 'shan', 'shouldn', 'wasn', 'weren', 'won', 'wouldn'];
        
        return array_diff($words, $stopWords);
    }

    /**
     * Analyze user intent and determine which feature they're asking about.
     */
    private function analyzeIntent(string $query, array $keywords, ?string $currentPage = null): array
    {
        $query = Str::lower($query);
        
        // Platform features mapping
        $features = config('copilot.features', []);
        $detectedFeature = null;
        $maxMatches = 0;
        $confidence = 0.0;

        foreach ($features as $featureKey => $feature) {
            $matches = 0;
            $patterns = array_merge(
                $feature['keywords'] ?? [],
                [$featureKey, $feature['name']]
            );
            
            foreach ($patterns as $pattern) {
                if (Str::contains($query, Str::lower($pattern))) {
                    $matches++;
                }
            }
            
            if ($matches > $maxMatches) {
                $maxMatches = $matches;
                $detectedFeature = $featureKey;
                $confidence = min($matches / count($patterns), 1.0);
            }
        }

        // Determine action type
        $action = $this->determineAction($query, $keywords);

        return [
            'feature' => $detectedFeature,
            'action' => $action,
            'confidence' => $confidence,
            'current_page' => $currentPage,
            'keywords' => $keywords
        ];
    }

    /**
     * Determine what action the user wants to perform.
     */
    private function determineAction(string $query, array $keywords): string
    {
        $query = Str::lower($query);
        
        $actionPatterns = [
            'create' => ['create', 'add', 'new', 'make', 'generate'],
            'update' => ['update', 'edit', 'modify', 'change', 'alter'],
            'delete' => ['delete', 'remove', 'trash', 'destroy'],
            'view' => ['view', 'see', 'show', 'display', 'list'],
            'search' => ['search', 'find', 'look', 'locate'],
            'help' => ['help', 'how', 'guide', 'tutorial', 'support'],
            'navigate' => ['go', 'navigate', 'access', 'open']
        ];

        foreach ($actionPatterns as $action => $patterns) {
            foreach ($patterns as $pattern) {
                if (Str::contains($query, $pattern)) {
                    return $action;
                }
            }
        }

        return 'help';
    }

    /**
     * Get platform guidance based on intent.
     */
    private function getPlatformGuidance(array $intent, array $keywords): array
    {
        $features = config('copilot.features', []);
        $featureKey = $intent['feature'];
        $action = $intent['action'];

        if (!$featureKey || !isset($features[$featureKey])) {
            return $this->getGeneralGuidance();
        }

        $feature = $features[$featureKey];
        
        return [
            'feature_name' => $feature['name'],
            'description' => $feature['description'],
            'steps' => $feature['steps'][$action] ?? $feature['steps']['default'] ?? [],
            'navigation' => $feature['navigation'],
            'api_endpoints' => $feature['api_endpoints'] ?? [],
            'related_features' => $feature['related_features'] ?? []
        ];
    }

    /**
     * Search for relevant help articles.
     */
    private function searchHelpArticles(array $keywords, int $tenantId): Collection
    {
        if (empty($keywords)) {
            return collect();
        }

        try {
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
        } catch (\Exception $e) {
            // If help articles don't exist or have issues, return empty collection
            Log::warning('ConvergioCopilotService: Help articles search failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);
            return collect();
        }
    }

    /**
     * Get related features for a given feature.
     */
    private function getRelatedFeatures(?string $featureKey): array
    {
        if (!$featureKey) {
            return [];
        }

        $features = config('copilot.features', []);
        return $features[$featureKey]['related_features'] ?? [];
    }

    /**
     * Generate the final response.
     */
    private function generateResponse(array $intent, array $platformGuidance, Collection $helpArticles, array $relatedFeatures): array
    {
        $message = $this->generateMessage($intent, $platformGuidance);
        $steps = $platformGuidance['steps'] ?? [];
        $navigation = $platformGuidance['navigation'] ?? '';
        
        // Format help articles
        $helpSuggestions = $helpArticles->take(self::TOP_SUGGESTIONS)->map(function ($article) {
            return [
                'title' => $article->title,
                'summary' => $article->summary,
                'url' => route('help.article', $article->slug),
                'relevance_score' => 0.8
            ];
        })->toArray();

        return [
            'message' => $message,
            'steps' => $steps,
            'navigation' => $navigation,
            'feature_name' => $platformGuidance['feature_name'] ?? null,
            'description' => $platformGuidance['description'] ?? null,
            'help_articles' => $helpSuggestions,
            'related_features' => $relatedFeatures,
            'confidence' => $intent['confidence'],
            'action' => $intent['action'],
            'feature' => $intent['feature']
        ];
    }

    /**
     * Generate a natural language message.
     */
    private function generateMessage(array $intent, array $platformGuidance): string
    {
        $featureName = $platformGuidance['feature_name'] ?? 'this feature';
        $action = $intent['action'];

        $messages = [
            'create' => "I'll help you create a new {$featureName}. Here's how to do it:",
            'update' => "I'll guide you through updating {$featureName}. Follow these steps:",
            'delete' => "I'll help you with {$featureName} deletion. Here's the process:",
            'view' => "I'll show you how to view {$featureName}. Here are the steps:",
            'search' => "I'll help you search for {$featureName}. Here's how:",
            'navigate' => "I'll guide you to {$featureName}. Here's the path:",
            'help' => "I'll help you with {$featureName}. Here's what you need to know:"
        ];

        return $messages[$action] ?? "I'll help you with {$featureName}. Here's what you need to know:";
    }

    /**
     * Get general guidance when no specific feature is detected.
     */
    private function getGeneralGuidance(): array
    {
        return [
            'feature_name' => 'RC Convergio Platform',
            'description' => 'Your comprehensive CRM and business management platform',
            'steps' => [
                'Navigate to the main dashboard',
                'Use the sidebar menu to access different sections',
                'Click on any feature to get started'
            ],
            'navigation' => 'Use the main navigation menu to access all features',
            'related_features' => ['contacts', 'deals', 'campaigns', 'companies', 'tasks']
        ];
    }

    /**
     * Log the conversation.
     */
    private function logConversation(string $query, array $response, int $tenantId, ?int $userId): void
    {
        try {
            CopilotConversation::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'query' => $query,
                'response' => $response,
                'confidence' => $response['confidence'] ?? 0.0,
                'feature' => $response['feature'] ?? null,
                'action' => $response['action'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log copilot conversation', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'user_id' => $userId
            ]);
        }
    }

    /**
     * Check if the query is a greeting or general question.
     */
    private function isGreeting(string $query): bool
    {
        $query = strtolower(trim($query));
        $greetings = [
            'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening',
            'start', 'help', 'what can you do', 'what do you do', 'who are you',
            'introduction', 'welcome', 'begin', 'get started', 'how are you'
        ];
        
        foreach ($greetings as $greeting) {
            if (strpos($query, $greeting) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate greeting response.
     */
    private function generateGreetingResponse(array $intent, ?string $currentPage = null): array
    {
        $greetings = [
            "Hi! I'm your RC Convergio AI assistant. I can help you with contacts, deals, campaigns, tasks, and much more! What would you like to do today?",
            "Hello! I'm here to help you navigate RC Convergio. I can guide you through creating contacts, managing deals, setting up campaigns, and organizing your tasks. How can I assist you?",
            "Hey there! I'm your AI assistant for RC Convergio. I know all about the platform and can help you with any task. What would you like to work on?",
            "Welcome! I'm your RC Convergio helper. I can assist you with contacts, deals, email campaigns, task management, and more. What can I help you with today?"
        ];
        
        $randomGreeting = $greetings[array_rand($greetings)];
        
        $suggestions = $this->getContextualSuggestions($currentPage);
        
        return [
            'message' => $randomGreeting,
            'steps' => [],
            'navigation' => '',
            'feature_name' => null,
            'description' => null,
            'help_articles' => [],
            'related_features' => [],
            'suggestions' => $suggestions,
            'quick_actions' => $this->getQuickActions(null, $currentPage),
            'confidence' => 100.0,
            'action' => 'greeting',
            'feature' => null,
            'type' => 'greeting'
        ];
    }

    /**
     * Get conversation context from recent conversations.
     */
    private function getConversationContext(int $tenantId, ?int $userId): array
    {
        try {
            $recentConversations = CopilotConversation::where('tenant_id', $tenantId)
                ->when($userId, function ($query) use ($userId) {
                    return $query->where('user_id', $userId);
                })
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get(['feature', 'action', 'query', 'created_at']);
                
            return $recentConversations->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if this is a follow-up question.
     */
    private function isFollowUpQuestion(string $query, array $conversationContext): bool
    {
        if (empty($conversationContext)) {
            return false;
        }
        
        $query = strtolower($query);
        $followUpIndicators = [
            'next', 'then', 'after', 'also', 'what about', 'how about',
            'can i', 'should i', 'do i need to', 'what else', 'anything else'
        ];
        
        foreach ($followUpIndicators as $indicator) {
            if (strpos($query, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get contextual suggestions based on current page.
     */
    private function getContextualSuggestions(?string $currentPage = null): array
    {
        $suggestions = [
            'How do I create a contact?',
            'How do I create a deal?',
            'How do I send an email campaign?',
            'Show me available features'
        ];
        
        if ($currentPage) {
            $pageSuggestions = [
                '/dashboard' => [
                    'How do I create a contact?',
                    'How do I create a deal?',
                    'Show me my recent activities'
                ],
                '/contacts' => [
                    'How do I create a new contact?',
                    'How do I import contacts?',
                    'How do I search for contacts?'
                ],
                '/deals' => [
                    'How do I create a new deal?',
                    'How do I move a deal to the next stage?',
                    'How do I close a deal?'
                ],
                '/campaigns' => [
                    'How do I create an email campaign?',
                    'How do I send a campaign?',
                    'How do I track campaign performance?'
                ]
            ];
            
            if (isset($pageSuggestions[$currentPage])) {
                return $pageSuggestions[$currentPage];
            }
        }
        
        return $suggestions;
    }

    /**
     * Get quick actions based on feature and current page.
     */
    private function getQuickActions(?string $feature, ?string $currentPage = null): array
    {
        $actions = [];
        
        if ($feature) {
            $featureActions = [
                'contacts' => [
                    'Create another contact',
                    'View all contacts',
                    'Import contacts',
                    'Create a company'
                ],
                'deals' => [
                    'Create another deal',
                    'View pipeline',
                    'Create a quote',
                    'Schedule a meeting'
                ],
                'campaigns' => [
                    'Create another campaign',
                    'View campaign performance',
                    'Create an email template',
                    'Manage contact lists'
                ],
                'tasks' => [
                    'Create another task',
                    'View my tasks',
                    'Create a follow-up task',
                    'Set a reminder'
                ]
            ];
            
            $actions = $featureActions[$feature] ?? [];
        }
        
        // Add general quick actions
        $actions = array_merge($actions, [
            'Show me the dashboard',
            'What can I do here?',
            'Get help with this page'
        ]);
        
        return array_unique($actions);
    }

    /**
     * Generate natural, conversational response.
     */
    private function generateNaturalResponse(array $intent, array $platformGuidance, Collection $helpArticles, array $relatedFeatures, array $quickActions, bool $isFollowUp, array $conversationContext): array
    {
        $message = $this->generateNaturalMessage($intent, $platformGuidance, $isFollowUp, $conversationContext);
        $steps = $platformGuidance['steps'] ?? [];
        $navigation = $platformGuidance['navigation'] ?? '';
        
        // Format help articles
        $helpSuggestions = $helpArticles->take(self::TOP_SUGGESTIONS)->map(function ($article) {
            return [
                'title' => $article->title,
                'summary' => $article->summary,
                'url' => route('help.article', $article->slug),
                'relevance_score' => 0.8
            ];
        })->toArray();

        // Add follow-up suggestions
        $followUpSuggestions = $this->getFollowUpSuggestions($intent['feature'] ?? null, $intent['action'] ?? null);

        return [
            'message' => $message,
            'steps' => $steps,
            'navigation' => $navigation,
            'feature_name' => $platformGuidance['feature_name'] ?? null,
            'description' => $platformGuidance['description'] ?? null,
            'help_articles' => $helpSuggestions,
            'related_features' => $relatedFeatures,
            'suggestions' => $followUpSuggestions,
            'quick_actions' => $quickActions,
            'confidence' => $intent['confidence'],
            'action' => $intent['action'],
            'feature' => $intent['feature'],
            'type' => $isFollowUp ? 'follow_up' : 'response'
        ];
    }

    /**
     * Generate natural, conversational message.
     */
    private function generateNaturalMessage(array $intent, array $platformGuidance, bool $isFollowUp, array $conversationContext): string
    {
        $featureName = $platformGuidance['feature_name'] ?? 'this feature';
        $action = $intent['action'];
        
        if ($isFollowUp) {
            $messages = [
                'create' => "Great! Let me help you with {$featureName}. Here's how to do it:",
                'update' => "Perfect! I'll guide you through updating {$featureName}. Follow these steps:",
                'delete' => "Sure! Here's how to handle {$featureName} deletion:",
                'view' => "Absolutely! Let me show you how to view {$featureName}:",
                'search' => "Of course! Here's how to search for {$featureName}:",
                'navigate' => "I'll help you get to {$featureName}. Here's the way:",
                'help' => "Happy to help! Here's what you need to know about {$featureName}:"
            ];
        } else {
            $messages = [
                'create' => "I'll help you create a new {$featureName}! Here's how to do it step by step:",
                'update' => "I'll guide you through updating {$featureName}. Follow these steps:",
                'delete' => "I'll help you with {$featureName} deletion. Here's the process:",
                'view' => "I'll show you how to view {$featureName}. Here are the steps:",
                'search' => "I'll help you search for {$featureName}. Here's how:",
                'navigate' => "I'll guide you to {$featureName}. Here's the path:",
                'help' => "I'll help you with {$featureName}. Here's what you need to know:"
            ];
        }

        $baseMessage = $messages[$action] ?? "I'll help you with {$featureName}. Here's what you need to know:";
        
        // Add contextual information
        if (!empty($conversationContext)) {
            $lastFeature = $conversationContext[0]['feature'] ?? null;
            if ($lastFeature && $lastFeature !== $intent['feature']) {
                $baseMessage .= " Since you were just working with " . ucfirst($lastFeature) . ", this will be a great next step!";
            }
        }
        
        return $baseMessage;
    }

    /**
     * Get follow-up suggestions based on feature and action.
     */
    private function getFollowUpSuggestions(?string $feature, ?string $action): array
    {
        if (!$feature) {
            return [
                'What can I help you with?',
                'Show me available features',
                'How do I get started?'
            ];
        }
        
        $suggestions = [
            'contacts' => [
                'How do I create a company for this contact?',
                'How do I start a deal with this contact?',
                'How do I add this contact to an email campaign?',
                'How do I create a follow-up task?'
            ],
            'deals' => [
                'How do I create a quote for this deal?',
                'How do I move this deal to the next stage?',
                'How do I schedule a meeting?',
                'How do I create a follow-up task?'
            ],
            'campaigns' => [
                'How do I track campaign performance?',
                'How do I create another campaign?',
                'How do I manage my contact lists?',
                'How do I create an email template?'
            ],
            'tasks' => [
                'How do I create another task?',
                'How do I view all my tasks?',
                'How do I set a reminder?',
                'How do I assign a task to someone?'
            ]
        ];
        
        return $suggestions[$feature] ?? [
            'What else can I help you with?',
            'Show me related features',
            'How do I get more help?'
        ];
    }

    /**
     * Prepare error response.
     */
    private function prepareErrorResponse(): array
    {
        return [
            'message' => 'I apologize, but I encountered an error processing your request. Please try again or contact support if the issue persists.',
            'steps' => [],
            'navigation' => '',
            'feature_name' => null,
            'description' => null,
            'help_articles' => [],
            'related_features' => [],
            'suggestions' => ['Try asking again', 'Show me available features', 'Get help'],
            'quick_actions' => ['Start over', 'Get help', 'Show features'],
            'confidence' => 0.0,
            'action' => 'help',
            'feature' => null,
            'type' => 'error'
        ];
    }

    /**
     * Get all available features for the platform.
     */
    public function getAvailableFeatures(): array
    {
        $features = config('copilot.features', []);
        
        return array_map(function ($feature, $key) {
            return [
                'key' => $key,
                'name' => $feature['name'],
                'description' => $feature['description'],
                'navigation' => $feature['navigation'],
                'actions' => array_keys($feature['steps'] ?? [])
            ];
        }, $features, array_keys($features));
    }

    /**
     * Get user's conversation history.
     */
    public function getConversationHistory(int $tenantId, ?int $userId = null, int $limit = 10): Collection
    {
        $query = CopilotConversation::where('tenant_id', $tenantId);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return $query->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get()
                   ->map(function ($conversation) {
                       return [
                           'id' => $conversation->id,
                           'query' => $conversation->query,
                           'feature' => $conversation->feature,
                           'action' => $conversation->action,
                           'confidence' => $conversation->confidence,
                           'created_at' => $conversation->created_at->toISOString()
                       ];
                   });
    }
}
