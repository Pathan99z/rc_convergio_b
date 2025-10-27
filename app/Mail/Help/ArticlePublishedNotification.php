<?php

namespace App\Mail\Help;

use App\Models\Help\Article;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArticlePublishedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Article $article,
        public User $recipient,
        public string $action = 'published'
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->action) {
            'published' => "New Article Published: {$this->article->title}",
            'updated' => "Article Updated: {$this->article->title}",
            'archived' => "Article Archived: {$this->article->title}",
            default => "Article Notification: {$this->article->title}"
        };

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
            view: 'emails.help.article-notification',
            with: [
                'article' => $this->article,
                'recipient' => $this->recipient,
                'action' => $this->action,
                'articleUrl' => url("/api/help/articles/{$this->article->slug}?tenant={$this->article->tenant_id}"),
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
