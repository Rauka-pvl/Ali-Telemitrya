<?php

namespace App\Services;

use App\Models\RoomKey;
use App\Models\RoomKeyAuthLog;
use Illuminate\Support\Facades\Http;

class RoomKeyAuthService
{
    private const WINDOW_SECONDS = 3600;

    /**
     * @return array{allowed: bool, blocked: bool, message: ?string}
     */
    public function registerAuthAttempt(RoomKey $room, string $ip): array
    {
        $room->refresh();

        if ($room->isBlocked()) {
            return [
                'allowed' => false,
                'blocked' => true,
                'message' => 'Ключ заблокирован',
            ];
        }

        $location = $this->resolveLocation($ip);

        if ($this->shouldBlockByDifferentCity($room, $ip, $location)) {
            $room->update(['block' => 1]);

            $this->logAttempt($room, $ip, $location);

            return [
                'allowed' => false,
                'blocked' => true,
                'message' => 'Ключ заблокирован: авторизация из другого города',
            ];
        }

        $this->logAttempt($room, $ip, $location);

        return [
            'allowed' => true,
            'blocked' => false,
            'message' => null,
        ];
    }

    /**
     * @param array{latitude: ?float, longitude: ?float, city: ?string} $location
     */
    private function shouldBlockByDifferentCity(RoomKey $room, string $ip, array $location): bool
    {
        $currentCity = $this->normalizeCity($location['city'] ?? null);
        if ($currentCity === null) {
            return false;
        }

        $recentLogs = RoomKeyAuthLog::query()
            ->where('room_key_id', $room->id)
            ->where('created_at', '>=', now()->subSeconds(self::WINDOW_SECONDS))
            ->where('ip', '!=', $ip)
            ->whereNotNull('city')
            ->get();

        foreach ($recentLogs as $log) {
            $previousCity = $this->normalizeCity($log->city);
            if ($previousCity === null) {
                continue;
            }

            if ($previousCity !== $currentCity) {
                return true;
            }
        }

        return false;
    }

    private function normalizeCity(?string $city): ?string
    {
        if ($city === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($city));
        if ($normalized === '') {
            return null;
        }

        return $normalized;
    }

    /**
     * @param array{latitude: ?float, longitude: ?float, city: ?string} $location
     */
    private function logAttempt(RoomKey $room, string $ip, array $location): void
    {
        RoomKeyAuthLog::query()->create([
            'room_key_id' => $room->id,
            'ip' => $ip,
            'city' => $location['city'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
        ]);
    }

    /**
     * @return array{latitude: ?float, longitude: ?float, city: ?string}
     */
    private function resolveLocation(string $ip): array
    {
        if ($this->isPrivateIp($ip)) {
            return [
                'latitude' => null,
                'longitude' => null,
                'city' => null,
            ];
        }

        try {
            $response = Http::timeout(4)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,lat,lon,city,query',
                ]);

            if (! $response->successful()) {
                return ['latitude' => null, 'longitude' => null, 'city' => null];
            }

            $data = $response->json();
            if (($data['status'] ?? '') !== 'success') {
                return ['latitude' => null, 'longitude' => null, 'city' => null];
            }

            return [
                'latitude' => isset($data['lat']) ? (float) $data['lat'] : null,
                'longitude' => isset($data['lon']) ? (float) $data['lon'] : null,
                'city' => $data['city'] ?? null,
            ];
        } catch (\Throwable) {
            return ['latitude' => null, 'longitude' => null, 'city' => null];
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
