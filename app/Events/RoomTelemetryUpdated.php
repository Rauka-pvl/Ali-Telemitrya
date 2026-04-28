<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomTelemetryUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array{x: float, y: float, z: float, magnitude: float, ts: int} $motion
     */
    public function __construct(
        public string $roomId,
        public int $playerIndex,
        public array $motion,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel("room.{$this->roomId}")];
    }

    public function broadcastAs(): string
    {
        return 'telemetry.motion';
    }

    public function broadcastWith(): array
    {
        return [
            'roomId' => $this->roomId,
            'playerIndex' => $this->playerIndex,
            'motion' => $this->motion,
        ];
    }
}
