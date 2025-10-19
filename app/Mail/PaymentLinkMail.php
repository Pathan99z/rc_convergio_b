<?php

namespace App\Mail;

use App\Models\Commerce\CommercePaymentLink;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CommercePaymentLink $paymentLink;
    public ?Quote $quote;
    public string $customerName;
    public string $customerEmail;
    public bool $isTestMode;

    /**
     * Create a new message instance.
     */
    public function __construct(
        CommercePaymentLink $paymentLink,
        string $customerName,
        string $customerEmail,
        ?Quote $quote = null
    ) {
        $this->paymentLink = $paymentLink;
        $this->quote = $quote;
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
        $this->isTestMode = str_contains($paymentLink->stripe_session_id, 'test_session_');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->quote 
            ? "Payment Link for Quote {$this->quote->quote_number}"
            : "Payment Link - Invoice";

        if ($this->isTestMode) {
            $subject = "[TEST] " . $subject;
        }

        return new Envelope(
            subject: $subject,
            from: config('mail.from.address', 'noreply@example.com'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-link',
            with: [
                'paymentUrl' => $this->paymentLink->url,
                'amount' => $this->paymentLink->amount ?? ($this->quote->total ?? $this->quote->total_amount ?? 0),
                'currency' => $this->paymentLink->currency ?? $this->quote->currency ?? 'USD',
                'customerName' => $this->customerName,
                'customerEmail' => $this->customerEmail,
                'quote' => $this->quote,
                'expiresAt' => $this->paymentLink->expires_at,
                'isTestMode' => $this->isTestMode,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
