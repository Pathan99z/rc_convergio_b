<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\Contact;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class QuoteMailer
{
    /**
     * Send quote via email to a contact.
     */
    public function sendQuoteEmail(Quote $quote, Contact $contact, ?string $customMessage = null): bool
    {
        try {
            $quote->load(['deal.company', 'items', 'creator']);

            Mail::to($contact->email)
                ->send(new \App\Mail\QuoteMail($quote, $contact, $customMessage));

            Log::info('Quote email sent', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send quote email', [
                'quote_id' => $quote->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send quote acceptance notification.
     */
    public function sendAcceptanceNotification(Quote $quote): bool
    {
        try {
            $quote->load(['deal.company', 'creator']);

            // Send to quote creator
            Mail::to($quote->creator->email)
                ->send(new \App\Mail\QuoteAcceptedMail($quote));

            Log::info('Quote acceptance notification sent', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'creator_email' => $quote->creator->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send quote acceptance notification', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send quote rejection notification.
     */
    public function sendRejectionNotification(Quote $quote): bool
    {
        try {
            $quote->load(['deal.company', 'creator']);

            // Send to quote creator
            Mail::to($quote->creator->email)
                ->send(new \App\Mail\QuoteRejectedMail($quote));

            Log::info('Quote rejection notification sent', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'creator_email' => $quote->creator->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send quote rejection notification', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send quote expiration reminder.
     */
    public function sendExpirationReminder(Quote $quote, Contact $contact): bool
    {
        try {
            $quote->load(['deal.company', 'items', 'creator']);

            Mail::to($contact->email)
                ->send(new \App\Mail\QuoteExpirationReminderMail($quote, $contact));

            Log::info('Quote expiration reminder sent', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send quote expiration reminder', [
                'quote_id' => $quote->id,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

