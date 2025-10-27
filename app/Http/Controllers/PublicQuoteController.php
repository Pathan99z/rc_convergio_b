<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\Deal;
use App\Events\QuoteAccepted;
use App\Events\QuoteRejected;
use App\Services\QuoteMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PublicQuoteController extends Controller
{
    public function __construct(private QuoteMailer $quoteMailer) {}

    /**
     * Show quote details (public view).
     */
    public function show(string $uuid): JsonResponse
    {
        $quote = Quote::with(['deal.company', 'deal.contact', 'items', 'creator'])
            ->where('uuid', $uuid)
            ->where('status', 'sent')
            ->where('valid_until', '>=', now())
            ->first();

        if (!$quote) {
            return response()->json([
                'message' => 'Quote not found or expired'
            ], 404);
        }

        return response()->json([
            'data' => [
                'quote_number' => $quote->quote_number,
                'status' => $quote->status,
                'valid_until' => $quote->valid_until->format('Y-m-d'),
                'created_at' => $quote->created_at->format('Y-m-d'),
                'company' => $quote->deal->company->name ?? null,
                'contact' => $quote->deal->contact ? 
                    $quote->deal->contact->first_name . ' ' . $quote->deal->contact->last_name : null,
                'email' => $quote->deal->contact->email ?? null,
                'phone' => $quote->deal->contact->phone ?? null,
                'items' => $quote->items->map(function ($item) {
                    return [
                        'name' => $item->name,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => number_format($item->unit_price, 2),
                        'discount' => number_format($item->discount, 2),
                        'tax_rate' => number_format($item->tax_rate, 2),
                        'total' => number_format($item->total, 2),
                    ];
                }),
                'totals' => [
                    'subtotal' => number_format($quote->subtotal, 2),
                    'tax' => number_format($quote->tax, 2),
                    'discount' => number_format($quote->discount, 2),
                    'total' => number_format($quote->total, 2),
                    'currency' => $quote->currency,
                ],
                'can_accept' => $quote->status === 'sent' && $quote->valid_until >= now(),
                'can_reject' => $quote->status === 'sent' && $quote->valid_until >= now(),
            ]
        ]);
    }

    /**
     * Accept a quote.
     */
    public function accept(string $uuid): JsonResponse
    {
        $quote = Quote::with(['deal', 'deal.company', 'deal.contact', 'creator'])
            ->where('uuid', $uuid)
            ->where('status', 'sent')
            ->where('valid_until', '>=', now())
            ->first();

        if (!$quote) {
            return response()->json([
                'message' => 'Quote not found, expired, or already processed'
            ], 404);
        }

        try {
            DB::transaction(function () use ($quote) {
                // Update quote status
                $quote->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);

                // Update linked deal if it exists
                if ($quote->deal) {
                    $quote->deal->update([
                        'status' => 'won',
                        'closed_date' => now(),
                    ]);
                }

                // Fire event
                event(new QuoteAccepted($quote));

                // Send acceptance emails
                $this->quoteMailer->sendAcceptanceNotification($quote);
            });

            return response()->json([
                'message' => 'Quote accepted successfully',
                'status' => 'accepted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to accept quote: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a quote.
     */
    public function reject(string $uuid): JsonResponse
    {
        $quote = Quote::with(['deal', 'deal.company', 'deal.contact', 'creator'])
            ->where('uuid', $uuid)
            ->where('status', 'sent')
            ->where('valid_until', '>=', now())
            ->first();

        if (!$quote) {
            return response()->json([
                'message' => 'Quote not found, expired, or already processed'
            ], 404);
        }

        try {
            DB::transaction(function () use ($quote) {
                // Update quote status
                $quote->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                ]);

                // Fire event
                event(new QuoteRejected($quote));

                // Send rejection email
                $this->quoteMailer->sendRejectionNotification($quote);
            });

            return response()->json([
                'message' => 'Quote rejected successfully',
                'status' => 'rejected'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject quote: ' . $e->getMessage()
            ], 500);
        }
    }
}

