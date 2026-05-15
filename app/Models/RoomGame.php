<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomGame extends Model
{
    public const KYZ = 'kyz';

    public const ARKAN = 'arkan';

    public const BAYGE = 'bayge';

    protected $table = 'room_game';

    protected $fillable = [
        'room_key_id',
        'game',
    ];

    /**
     * @return array<string, array{label: string, path: string}>
     */
    public static function catalog(): array
    {
        return [
            self::KYZ => [
                'label' => 'Кыз куу',
                'path' => 'mic',
            ],
            self::ARKAN => [
                'label' => 'Аркан тарту',
                'path' => 'motion',
            ],
            self::BAYGE => [
                'label' => 'Байге',
                'path' => 'bayge',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedSlugs(): array
    {
        return array_keys(self::catalog());
    }

    public function roomKey(): BelongsTo
    {
        return $this->belongsTo(RoomKey::class);
    }
}
