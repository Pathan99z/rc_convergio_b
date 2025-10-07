<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class QuoteAcceptedListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(\App\Events\QuoteAccepted $event): void
    {
        try {
            // Log activity for the quote
            \App\Models\Activity::create([
                'type' => 'quote_accepted',
                'subject' => 'Quote accepted by client',
                'description' => "Quote #{$event->quote->quote_number} was accepted by the client",
                'status' => 'completed',
                'completed_at' => now(),
                'owner_id' => $event->quote->created_by,
                'tenant_id' => $event->quote->tenant_id,
                'related_type' => 'App\Models\Quote',
                'related_id' => $event->quote->id,
            ]);

            // Log activity for the deal
            \App\Models\Activity::create([
                'type' => 'deal_won',
                'subject' => 'Deal won - Quote accepted',
                'description' => "Deal '{$event->quote->deal->title}' was won when quote #{$event->quote->quote_number} was accepted",
                'status' => 'completed',
                'completed_at' => now(),
                'owner_id' => $event->quote->created_by,
                'tenant_id' => $event->quote->tenant_id,
                'related_type' => 'App\Models\Deal',
                'related_id' => $event->quote->deal_id,
            ]);

            \Illuminate\Support\Facades\Log::info('Quote accepted activity logged', [
                'quote_id' => $event->quote->id,
                'quote_number' => $event->quote->quote_number,
                'deal_id' => $event->quote->deal_id,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log quote accepted activity', [
                'quote_id' => $event->quote->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
