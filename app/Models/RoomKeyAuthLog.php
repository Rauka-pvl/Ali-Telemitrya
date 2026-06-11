<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomKeyAuthLog extends Model
{
    protected $fillable = [
        'room_key_id',
        'ip',
        'city',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function roomKey(): BelongsTo
    {
        return $this->belongsTo(RoomKey::class);
    }
}
