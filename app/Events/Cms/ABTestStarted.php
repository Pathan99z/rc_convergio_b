<?php

namespace App\Events\Cms;

use App\Models\Cms\ABTest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ABTestStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ABTest $abTest;

    /**
     * Create a new event instance.
     */
    public function __construct(ABTest $abTest)
    {
        $this->abTest = $abTest;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('cms.abtests.' . $this->abTest->id),
        ];
    }
}



