<?php

namespace App\Traits\Help;

use App\Services\Help\SuggestionService;

trait ProvidesArticleSuggestions
{
    /**
     * Get article suggestions for ticket creation.
     */
    protected function getArticleSuggestions(string $subject, string $description, int $tenantId): array
    {
        try {
            $suggestionService = app(SuggestionService::class);
            
            return $suggestionService->suggestForTicket($subject, $description, $tenantId);
        } catch (\Exception $e) {
            \Log::error('Error getting article suggestions for ticket', [
                'subject' => $subject,
                'description' => $description,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }
}
