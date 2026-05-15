<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomKey extends Model
{
    protected $fillable = [
        'room_id',
        'name',
    ];

    public function roomGames(): HasMany
    {
        return $this->hasMany(RoomGame::class);
    }

    public function hasGame(string $game): bool
    {
        return $this->roomGames()->where('game', $game)->exists();
    }

    /**
     * @return list<string>
     */
    public function allowedGameSlugs(): array
    {
        return $this->roomGames()
            ->pluck('game')
            ->filter(fn (string $game) => in_array($game, RoomGame::allowedSlugs(), true))
            ->values()
            ->all();
    }
}
