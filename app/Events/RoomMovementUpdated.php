<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomMovementUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $roomId,
        public int $playerIndex,
        public string $source,
        public float $movement,
        public ?float $hz,
        public ?float $magnitude,
        public int $ts,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel("room.{$this->roomId}")];
    }

    public function broadcastAs(): string
    {
        return 'movement.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'roomId' => $this->roomId,
            'playerIndex' => $this->playerIndex,
            'source' => $this->source,
            'movement' => $this->movement,
            'hz' => $this->hz,
            'magnitude' => $this->magnitude,
            'ts' => $this->ts,
        ];
    }
}
