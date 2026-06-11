<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomGame extends Model
{
    public const KYZ = 'kyz';

    public const ARKAN = 'arkan';

    public const BAYGE = 'bayge';

    public const DRIVE = 'drive';

    public const PING = 'ping';

    public const TRAVEL = 'travel';

    public const STICK = 'stick';

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
            self::DRIVE => [
                'label' => 'Той Drive',
                'path' => 'drive',
            ],
            self::PING => [
                'label' => 'Пинг Понг',
                'path' => 'ping',
            ],
            self::TRAVEL => [
                'label' => 'Күс travel',
                'path' => 'travel',
            ],
            self::STICK => [
                'label' => '1 палка',
                'path' => 'stick',
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
