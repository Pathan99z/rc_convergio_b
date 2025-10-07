<?php

namespace App\Mail;

use App\Models\Quote;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class QuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Quote $quote,
        public Contact $contact,
        public ?string $customMessage = null
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Quote #' . $this->quote->quote_number . ' - ' . $this->quote->deal->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.quote',
            with: [
                'quote' => $this->quote,
                'contact' => $this->contact,
                'customMessage' => $this->customMessage,
                'companyName' => $this->quote->deal->company->name ?? 'Your Company',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Attach PDF if available
        if ($this->quote->pdf_path && Storage::disk('public')->exists($this->quote->pdf_path)) {
            $attachments[] = Attachment::fromStorageDisk('public', $this->quote->pdf_path)
                ->as('quote-' . $this->quote->quote_number . '.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
