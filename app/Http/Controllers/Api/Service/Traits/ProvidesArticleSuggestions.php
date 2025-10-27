<?php

namespace App\Http\Controllers\Api\Service\Traits;

use App\Services\Help\SuggestionService;
use Illuminate\Http\Request;

trait ProvidesArticleSuggestions
{
    /**
     * Get article suggestions based on ticket content.
     */
    protected function getArticleSuggestions(Request $request, ?string $subject = null, ?string $description = null): array
    {
        try {
            $suggestionService = app(SuggestionService::class);
            
            // Get tenant ID from authenticated user or request
            $tenantId = $request->user() 
                ? ($request->user()->tenant_id ?? $request->user()->id)
                : ($request->get('tenant_id', 1));
            
            // Use provided subject/description or extract from request
            $ticketSubject = $subject ?? $request->get('subject');
            $ticketDescription = $description ?? $request->get('description');
            
            if (empty($ticketSubject) && empty($ticketDescription)) {
                return [];
            }
            
            $suggestions = $suggestionService->suggestForText(
                $ticketSubject,
                $ticketDescription,
                $tenantId,
                5 // Limit to 5 suggestions
            );
            
            return $suggestions;
            
        } catch (\Exception $e) {
            \Log::warning('Failed to get article suggestions for ticket', [
                'error' => $e->getMessage(),
                'subject' => $subject,
                'tenant_id' => $request->user()?->tenant_id ?? $request->user()?->id ?? 'unknown'
            ]);
            
            return [];
        }
    }
    
    /**
     * Enhance ticket response with article suggestions.
     */
    protected function enhanceTicketResponseWithSuggestions($ticket, array $suggestions = []): array
    {
        $response = [
            'ticket' => $ticket,
        ];
        
        if (!empty($suggestions)) {
            $response['suggested_articles'] = $suggestions;
            $response['suggestions_count'] = count($suggestions);
        }
        
        return $response;
    }
}
