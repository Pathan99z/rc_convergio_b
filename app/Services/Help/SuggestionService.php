<?php

namespace App\Services\Help;

use Illuminate\Support\Facades\Log;

class SuggestionService
{
    public function __construct(
        private ArticleService $articleService
    ) {}

    /**
     * Suggest articles for the given text (subject and description).
     */
    public function suggestForText(string $subject, string $description, int $tenantId, int $limit = 3): array
    {
        try {
            // Combine subject and description for better search results
            $combinedText = trim($subject . ' ' . $description);
            
            if (empty($combinedText)) {
                return [];
            }

            $suggestions = $this->articleService->searchRelatedArticles(
                $combinedText,
                $limit,
                $tenantId
            );

            Log::info('Article suggestions generated', [
                'subject' => $subject,
                'tenant_id' => $tenantId,
                'suggestions_count' => count($suggestions),
            ]);

            return $suggestions;

        } catch (\Exception $e) {
            Log::error('Error generating article suggestions', [
                'subject' => $subject,
                'description' => $description,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Suggest articles for ticket creation.
     */
    public function suggestForTicket(string $subject, string $description, int $tenantId): array
    {
        return $this->suggestForText($subject, $description, $tenantId, 5);
    }
}
