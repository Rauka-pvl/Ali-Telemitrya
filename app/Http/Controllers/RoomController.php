<?php

namespace App\Http\Controllers;

use App\Events\RoomMotionDetected;
use App\Events\RoomPresenceUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RoomController extends Controller
{
    private const ONLINE_TTL_SECONDS = 45;

    public function telemetry(string $roomId): View
    {
        return view('room', ['roomId' => $roomId]);
    }

    public function mic(string $roomId): View
    {
        return view('room-mic', ['roomId' => $roomId]);
    }

    public function show(string $roomId): View
    {
        return view('room', ['roomId' => $roomId]);
    }

    public function join(Request $request, string $roomId): JsonResponse
    {
        $validated = $request->validate([
            'userId' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $users = $this->upsertUser($roomId, $validated['userId'], $validated['name']);
        broadcast(new RoomPresenceUpdated($roomId, $users));

        return response()->json(['users' => $users]);
    }

    public function heartbeat(Request $request, string $roomId): JsonResponse
    {
        $validated = $request->validate([
            'userId' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $users = $this->upsertUser($roomId, $validated['userId'], $validated['name']);
        broadcast(new RoomPresenceUpdated($roomId, $users));

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, string $roomId): JsonResponse
    {
        $validated = $request->validate([
            'userId' => ['required', 'string', 'max:100'],
        ]);

        $users = $this->deleteUser($roomId, $validated['userId']);
        broadcast(new RoomPresenceUpdated($roomId, $users));

        return response()->json(['ok' => true]);
    }

    public function motion(Request $request, string $roomId): JsonResponse
    {
        $validated = $request->validate([
            'userId' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'z' => ['required', 'numeric'],
            'magnitude' => ['required', 'numeric', 'min:0'],
            'ts' => ['required', 'integer'],
        ]);

        $users = $this->upsertUser($roomId, $validated['userId'], $validated['name']);
        broadcast(new RoomPresenceUpdated($roomId, $users));

        broadcast(new RoomMotionDetected(
            $roomId,
            $validated['userId'],
            $validated['name'],
            [
                'x' => (float) $validated['x'],
                'y' => (float) $validated['y'],
                'z' => (float) $validated['z'],
                'magnitude' => (float) $validated['magnitude'],
                'ts' => (int) $validated['ts'],
            ]
        ));

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<int, array{userId: string, name: string}>
     */
    private function upsertUser(string $roomId, string $userId, string $name): array
    {
        $users = $this->activeUsers($roomId);
        $users[$userId] = [
            'userId' => $userId,
            'name' => $name,
            'lastSeen' => now()->timestamp,
        ];

        return $this->persistUsers($roomId, $users);
    }

    /**
     * @return array<int, array{userId: string, name: string}>
     */
    private function deleteUser(string $roomId, string $userId): array
    {
        $users = $this->activeUsers($roomId);
        unset($users[$userId]);

        return $this->persistUsers($roomId, $users);
    }

    /**
     * @return array<string, array{userId: string, name: string, lastSeen: int}>
     */
    private function activeUsers(string $roomId): array
    {
        $users = Cache::get($this->cacheKey($roomId), []);
        $cutoff = now()->subSeconds(self::ONLINE_TTL_SECONDS)->timestamp;

        return collect($users)
            ->filter(fn(array $user): bool => ($user['lastSeen'] ?? 0) >= $cutoff)
            ->mapWithKeys(fn(array $user): array => [$user['userId'] => $user])
            ->all();
    }

    /**
     * @param array<string, array{userId: string, name: string, lastSeen: int}> $users
     * @return array<int, array{userId: string, name: string}>
     */
    private function persistUsers(string $roomId, array $users): array
    {
        Cache::put($this->cacheKey($roomId), $users, now()->addMinutes(10));

        return collect($users)
            ->map(fn(array $user): array => [
                'userId' => $user['userId'],
                'name' => $user['name'],
            ])
            ->values()
            ->all();
    }

    private function cacheKey(string $roomId): string
    {
        return "room:{$roomId}:users";
    }
}
