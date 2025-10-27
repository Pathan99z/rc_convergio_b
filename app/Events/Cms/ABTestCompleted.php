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

class ABTestCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ABTest $abTest;
    public array $results;

    /**
     * Create a new event instance.
     */
    public function __construct(ABTest $abTest, array $results = [])
    {
        $this->abTest = $abTest;
        $this->results = $results;
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

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'test_id' => $this->abTest->id,
            'test_name' => $this->abTest->name,
            'results' => $this->results,
            'completed_at' => now()->toIso8601String(),
        ];
    }
}



