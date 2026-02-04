<?php

namespace App\Mail;

use App\Models\Contact;
use App\Models\Collateral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CollateralMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Contact $contact,
        public Collection $collaterals,
        public ?string $message = null
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $productNames = $this->collaterals->pluck('product.name')->unique()->implode(', ');
        $subject = 'Product Collateral: ' . $productNames;

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.collaterals.send',
            with: [
                'contact' => $this->contact,
                'collaterals' => $this->collaterals,
                'message' => $this->message,
                'products' => $this->collaterals->pluck('product')->unique(),
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

        foreach ($this->collaterals as $collateral) {
            if (Storage::exists($collateral->file_path)) {
                $extension = pathinfo($collateral->file_path, PATHINFO_EXTENSION);
                $filename = $collateral->name . '.' . $extension;
                
                $attachments[] = Attachment::fromStorage($collateral->file_path)
                    ->as($filename)
                    ->withMime($collateral->file_type);
            }
        }

        return $attachments;
    }
}




