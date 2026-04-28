<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomMicLevelUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $roomId,
        public int $playerIndex,
        public float $level,
        public ?float $hz,
        public int $ts,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel("room.{$this->roomId}")];
    }

    public function broadcastAs(): string
    {
        return 'telemetry.mic';
    }

    public function broadcastWith(): array
    {
        return [
            'roomId' => $this->roomId,
            'playerIndex' => $this->playerIndex,
            'level' => $this->level,
            'hz' => $this->hz,
            'ts' => $this->ts,
        ];
    }
}
