<?php

namespace App\Events\Cms;

use App\Models\Cms\Page;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PagePublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Page $page;

    /**
     * Create a new event instance.
     */
    public function __construct(Page $page)
    {
        $this->page = $page;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('cms.pages.' . $this->page->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'page_id' => $this->page->id,
            'title' => $this->page->title,
            'slug' => $this->page->slug,
            'status' => $this->page->status,
            'published_at' => $this->page->published_at?->toIso8601String(),
            'url' => $this->page->url,
        ];
    }
}



