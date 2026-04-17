<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomMotionDetected implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array{x: float, y: float, z: float, magnitude: float, ts: int} $motion
     */
    public function __construct(
        public string $roomId,
        public string $userId,
        public string $name,
        public array $motion,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel("room.{$this->roomId}")];
    }

    public function broadcastAs(): string
    {
        return 'motion.detected';
    }

    public function broadcastWith(): array
    {
        return [
            'roomId' => $this->roomId,
            'userId' => $this->userId,
            'name' => $this->name,
            'motion' => $this->motion,
        ];
    }
}
