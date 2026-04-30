<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomPlayersUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<int, array{playerIndex: int, name: string}> $players
     */
    public function __construct(
        public string $roomId,
        public string $mode,
        public array $players,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel("room.{$this->roomId}.{$this->mode}")];
    }

    public function broadcastAs(): string
    {
        return 'players.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'roomId' => $this->roomId,
            'mode' => $this->mode,
            'players' => $this->players,
        ];
    }
}
