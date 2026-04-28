<?php

namespace App\Http\Controllers;

use App\Events\RoomMicLevelUpdated;
use App\Events\RoomMovementUpdated;
use App\Events\RoomPlayersUpdated;
use App\Events\RoomTelemetryUpdated;
use App\Models\RoomKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RoomController extends Controller
{
    private const ONLINE_TTL_SECONDS = 45;

    public function telemetry(string $roomId): View
    {
        $this->ensureRoomExistsOrAbort($roomId);

        return view('room', ['roomId' => $roomId]);
    }

    public function mic(string $roomId): View
    {
        $this->ensureRoomExistsOrAbort($roomId);

        return view('room-mic', ['roomId' => $roomId]);
    }

    public function game(string $roomId): View
    {
        $this->ensureRoomExistsOrAbort($roomId);

        return view('room-game', ['roomId' => $roomId]);
    }

    public function rooms(string $roomId): View
    {
        $this->ensureRoomExistsOrAbort($roomId);

        return view('rooms-selector', ['roomId' => $roomId]);
    }

    public function gameMic(string $roomId): View
    {
        $this->ensureRoomExistsOrAbort($roomId);

        return view('room-game-mic', ['roomId' => $roomId]);
    }

    public function gameMotion(string $roomId): View
    {
        $this->ensureRoomExistsOrAbort($roomId);

        return view('room-game-motion', ['roomId' => $roomId]);
    }

    public function player(string $roomId): View
    {
        $this->ensureRoomExistsOrAbort($roomId);

        return view('room-player', ['roomId' => $roomId]);
    }

    public function playerJoin(Request $request, string $roomId): JsonResponse
    {
        if ($response = $this->ensureRoomExistsOrJson($roomId)) {
            return $response;
        }

        $validated = $request->validate([
            'clientId' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $state = $this->activePlayersState($roomId);
        $clientId = $validated['clientId'];
        $name = trim($validated['name']);

        $existing = collect([1, 2])->first(fn (int $index) => ($state["p{$index}"]['clientId'] ?? null) === $clientId);

        if ($existing) {
            $state["p{$existing}"]['name'] = $name;
            $state["p{$existing}"]['lastSeen'] = now()->timestamp;
            $this->persistPlayersState($roomId, $state);
            $players = $this->playersPayload($state);
            broadcast(new RoomPlayersUpdated($roomId, $players));

            return response()->json([
                'ok' => true,
                'playerIndex' => $existing,
                'players' => $players,
            ]);
        }

        $freeSlot = collect([1, 2])->first(fn (int $index) => empty($state["p{$index}"]['clientId']));

        if (! $freeSlot) {
            return response()->json([
                'ok' => false,
                'message' => 'Room is full. Only first two players can join.',
            ], 409);
        }

        $state["p{$freeSlot}"] = [
            'clientId' => $clientId,
            'name' => $name,
            'lastSeen' => now()->timestamp,
        ];

        $this->persistPlayersState($roomId, $state);
        $players = $this->playersPayload($state);
        broadcast(new RoomPlayersUpdated($roomId, $players));

        return response()->json([
            'ok' => true,
            'playerIndex' => $freeSlot,
            'players' => $players,
        ]);
    }

    public function playerHeartbeat(Request $request, string $roomId): JsonResponse
    {
        if ($response = $this->ensureRoomExistsOrJson($roomId)) {
            return $response;
        }

        $validated = $request->validate([
            'clientId' => ['required', 'string', 'max:100'],
        ]);

        $state = $this->activePlayersState($roomId);
        $clientId = $validated['clientId'];
        $slot = collect([1, 2])->first(fn (int $index) => ($state["p{$index}"]['clientId'] ?? null) === $clientId);

        if (! $slot) {
            return response()->json(['ok' => false, 'message' => 'Player not found'], 404);
        }

        $state["p{$slot}"]['lastSeen'] = now()->timestamp;
        $this->persistPlayersState($roomId, $state);

        return response()->json(['ok' => true]);
    }

    public function playerLeave(Request $request, string $roomId): JsonResponse
    {
        if ($response = $this->ensureRoomExistsOrJson($roomId)) {
            return $response;
        }

        $validated = $request->validate([
            'clientId' => ['required', 'string', 'max:100'],
        ]);

        $state = $this->activePlayersState($roomId);
        $clientId = $validated['clientId'];
        $slot = collect([1, 2])->first(fn (int $index) => ($state["p{$index}"]['clientId'] ?? null) === $clientId);

        if ($slot) {
            $state["p{$slot}"] = [];
            $this->persistPlayersState($roomId, $state);
            broadcast(new RoomPlayersUpdated($roomId, $this->playersPayload($state)));
        }

        return response()->json(['ok' => true]);
    }

    public function motion(Request $request, string $roomId): JsonResponse
    {
        if ($response = $this->ensureRoomExistsOrJson($roomId)) {
            return $response;
        }

        $validated = $request->validate([
            'clientId' => ['required', 'string', 'max:100'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'z' => ['required', 'numeric'],
            'magnitude' => ['required', 'numeric', 'min:0'],
            'ts' => ['required', 'integer'],
        ]);

        $playerIndex = $this->playerIndexByClientId($roomId, $validated['clientId']);
        if (! $playerIndex) {
            return response()->json(['ok' => false, 'message' => 'Player not joined'], 403);
        }

        broadcast(new RoomTelemetryUpdated(
            $roomId,
            $playerIndex,
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

    public function micLevel(Request $request, string $roomId): JsonResponse
    {
        if ($response = $this->ensureRoomExistsOrJson($roomId)) {
            return $response;
        }

        $validated = $request->validate([
            'clientId' => ['required', 'string', 'max:100'],
            'level' => ['required', 'numeric', 'min:0'],
            'hz' => ['nullable', 'numeric', 'min:0'],
            'ts' => ['required', 'integer'],
        ]);

        $playerIndex = $this->playerIndexByClientId($roomId, $validated['clientId']);
        if (! $playerIndex) {
            return response()->json(['ok' => false, 'message' => 'Player not joined'], 403);
        }

        broadcast(new RoomMicLevelUpdated(
            $roomId,
            $playerIndex,
            (float) $validated['level'],
            isset($validated['hz']) ? (float) $validated['hz'] : null,
            (int) $validated['ts'],
        ));

        return response()->json(['ok' => true]);
    }

    public function movement(Request $request, string $roomId): JsonResponse
    {
        if ($response = $this->ensureRoomExistsOrJson($roomId)) {
            return $response;
        }

        $validated = $request->validate([
            'clientId' => ['required', 'string', 'max:100'],
            'source' => ['required', 'string', 'in:mic,motion'],
            'movement' => ['required', 'numeric', 'min:0'],
            'hz' => ['nullable', 'numeric', 'min:0'],
            'magnitude' => ['nullable', 'numeric', 'min:0'],
            'ts' => ['required', 'integer'],
        ]);

        $playerIndex = $this->playerIndexByClientId($roomId, $validated['clientId']);
        if (! $playerIndex) {
            return response()->json(['ok' => false, 'message' => 'Player not joined'], 403);
        }

        broadcast(new RoomMovementUpdated(
            $roomId,
            $playerIndex,
            $validated['source'],
            (float) $validated['movement'],
            isset($validated['hz']) ? (float) $validated['hz'] : null,
            isset($validated['magnitude']) ? (float) $validated['magnitude'] : null,
            (int) $validated['ts'],
        ));

        return response()->json(['ok' => true]);
    }

    private function playerIndexByClientId(string $roomId, string $clientId): ?int
    {
        $state = $this->activePlayersState($roomId);
        foreach ([1, 2] as $index) {
            if (($state["p{$index}"]['clientId'] ?? null) === $clientId) {
                $state["p{$index}"]['lastSeen'] = now()->timestamp;
                $this->persistPlayersState($roomId, $state);

                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{p1: array{clientId?: string, name?: string, lastSeen?: int}, p2: array{clientId?: string, name?: string, lastSeen?: int}}
     */
    private function activePlayersState(string $roomId): array
    {
        $defaults = ['p1' => [], 'p2' => []];
        $state = Cache::get($this->cacheKey($roomId), $defaults);
        $state = array_merge($defaults, is_array($state) ? $state : []);

        $cutoff = now()->subSeconds(self::ONLINE_TTL_SECONDS)->timestamp;
        foreach ([1, 2] as $index) {
            $slot = $state["p{$index}"] ?? [];
            if (! isset($slot['lastSeen']) || $slot['lastSeen'] < $cutoff) {
                $state["p{$index}"] = [];
            }
        }

        return $state;
    }

    private function persistPlayersState(string $roomId, array $state): void
    {
        Cache::put($this->cacheKey($roomId), $state, now()->addMinutes(10));
    }

    /**
     * @param array{p1: array{clientId?: string, name?: string, lastSeen?: int}, p2: array{clientId?: string, name?: string, lastSeen?: int}} $state
     * @return array<int, array{playerIndex: int, name: string}>
     */
    private function playersPayload(array $state): array
    {
        return collect([1, 2])
            ->map(function (int $index) use ($state): ?array {
                $slot = $state["p{$index}"] ?? [];
                if (empty($slot['clientId']) || empty($slot['name'])) {
                    return null;
                }

                return [
                    'playerIndex' => $index,
                    'name' => $slot['name'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function cacheKey(string $roomId): string
    {
        return "room:{$roomId}:users";
    }

    private function ensureRoomExistsOrAbort(string $roomId): void
    {
        if (! RoomKey::query()->where('room_id', $roomId)->exists()) {
            abort(404, 'Комната не найдена');
        }
    }

    private function ensureRoomExistsOrJson(string $roomId): ?JsonResponse
    {
        if (! RoomKey::query()->where('room_id', $roomId)->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'Room key not found',
            ], 404);
        }

        return null;
    }
}
