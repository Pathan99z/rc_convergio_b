<?php

namespace App\Listeners;

use App\Events\QuoteSent;
use App\Models\Activity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class QuoteSentListener
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
    public function handle(QuoteSent $event): void
    {
        try {
            // Log activity for the quote
            Activity::create([
                'type' => 'quote_sent',
                'subject' => 'Quote sent to client',
                'description' => "Quote #{$event->quote->quote_number} was sent to the client",
                'status' => 'completed',
                'completed_at' => now(),
                'owner_id' => $event->quote->created_by,
                'tenant_id' => $event->quote->tenant_id,
                'related_type' => 'App\Models\Quote',
                'related_id' => $event->quote->id,
            ]);

            // Log activity for the deal
            Activity::create([
                'type' => 'quote_sent',
                'subject' => 'Quote sent for deal',
                'description' => "Quote #{$event->quote->quote_number} was sent for deal: {$event->quote->deal->title}",
                'status' => 'completed',
                'completed_at' => now(),
                'owner_id' => $event->quote->created_by,
                'tenant_id' => $event->quote->tenant_id,
                'related_type' => 'App\Models\Deal',
                'related_id' => $event->quote->deal_id,
            ]);

            Log::info('Quote sent activity logged', [
                'quote_id' => $event->quote->id,
                'quote_number' => $event->quote->quote_number,
                'deal_id' => $event->quote->deal_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to log quote sent activity', [
                'quote_id' => $event->quote->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
