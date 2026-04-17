<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Room {{ $roomId }}</title>
    @vite(['resources/js/app.js'])
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; max-width: 760px; }
        h1 { margin-bottom: 8px; }
        .row { margin: 12px 0; }
        button { padding: 8px 12px; margin-right: 8px; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 12px; margin-top: 12px; }
        .mono { font-family: Menlo, Monaco, monospace; font-size: 13px; }
        ul { margin: 8px 0 0; padding-left: 20px; }
    </style>
</head>
<body>
<h1>Realtime Room: {{ $roomId }}</h1>
<div class="row">
    <label>
        Name:
        <input id="nameInput" type="text" maxlength="40" placeholder="Your name">
    </label>
    <button id="joinBtn">Join Room</button>
    <button id="motionBtn" disabled>Enable Motion</button>
</div>

<div class="card">
    <strong>Online users</strong>
    <ul id="usersList"></ul>
</div>

<div class="card">
    <strong>Last motion event</strong>
    <div id="motionLog" class="mono">No data yet</div>
</div>

<script>
    window.addEventListener('load', () => {
        const roomId = @json($roomId);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const nameInput = document.getElementById('nameInput');
        const joinBtn = document.getElementById('joinBtn');
        const motionBtn = document.getElementById('motionBtn');
        const usersList = document.getElementById('usersList');
        const motionLog = document.getElementById('motionLog');

        const createUserId = () => {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }

            return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        };

        const userId = localStorage.getItem('room_user_id') ?? createUserId();
        localStorage.setItem('room_user_id', userId);

        let joined = false;
        let currentName = '';
        let lastMotionSentAt = 0;
        let heartbeatTimer = null;
        let motionEnabled = false;

        const renderUsers = (users) => {
            usersList.innerHTML = '';

            users.forEach((user) => {
                const li = document.createElement('li');
                li.textContent = `${user.name} (${user.userId === userId ? 'you' : user.userId})`;
                usersList.appendChild(li);
            });
        };

        const postJson = async (url, payload) => {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(await response.text());
            }

            return response.json();
        };

        const sendHeartbeat = async () => {
            if (!joined) {
                return;
            }

            await postJson(`/room/${roomId}/heartbeat`, {
                userId,
                name: currentName,
            });
        };

        const sendMotion = async (x, y, z, magnitude) => {
            await postJson(`/room/${roomId}/motion`, {
                userId,
                name: currentName,
                x,
                y,
                z,
                magnitude,
                ts: Date.now(),
            });
        };

        const onDeviceMotion = (event) => {
            if (!joined || !motionEnabled) {
                return;
            }

            const now = Date.now();
            if (now - lastMotionSentAt < 100) {
                return;
            }
            lastMotionSentAt = now;

            const x = event.accelerationIncludingGravity?.x ?? 0;
            const y = event.accelerationIncludingGravity?.y ?? 0;
            const z = event.accelerationIncludingGravity?.z ?? 0;
            const magnitude = Math.sqrt((x * x) + (y * y) + (z * z));

            sendMotion(x, y, z, magnitude).catch((error) => {
                console.error('Motion send failed', error);
            });
        };

        const enableMotion = async () => {
            if (!joined) {
                return;
            }

            const requestPermissionIfNeeded = async (EventCtor) => {
                if (typeof EventCtor === 'undefined'
                    || typeof EventCtor.requestPermission !== 'function') {
                    return 'granted';
                }

                return EventCtor.requestPermission();
            };

            const motionPermission = await requestPermissionIfNeeded(DeviceMotionEvent);
            const orientationPermission = await requestPermissionIfNeeded(DeviceOrientationEvent);

            if (motionPermission !== 'granted' || orientationPermission !== 'granted') {
                motionLog.textContent = `Permission denied. motion=${motionPermission}, orientation=${orientationPermission}`;
                alert('Доступ к движениям/ориентации не выдан. Проверь Safari settings и HTTPS.');
                return;
            }

            if (!motionEnabled) {
                window.addEventListener('devicemotion', onDeviceMotion);
                motionEnabled = true;
                motionBtn.textContent = 'Motion & Orientation Enabled';
                motionLog.textContent = 'Motion/orientation permission granted.';
            }
        };

        const joinRoom = async () => {
            const name = nameInput.value.trim() || `Guest-${userId.slice(0, 6)}`;

            const response = await postJson(`/room/${roomId}/join`, {
                userId,
                name,
            });

            currentName = name;
            joined = true;
            motionBtn.disabled = false;
            renderUsers(response.users ?? []);

            if (heartbeatTimer) {
                clearInterval(heartbeatTimer);
            }
            heartbeatTimer = setInterval(sendHeartbeat, 15000);
        };

        if (!window.Echo) {
            const initDetails = window.__echoInit
                ? JSON.stringify(window.__echoInit)
                : 'No init details from app.js';
            motionLog.textContent = `Realtime client not initialized (window.Echo is undefined). ${initDetails}`;
            return;
        }

        window.Echo.channel(`room.${roomId}`)
            .listen('.presence.updated', (event) => {
                renderUsers(event.users ?? []);
            })
            .listen('.motion.detected', (event) => {
                const m = event.motion;
                motionLog.textContent = `${event.name} | mag=${m.magnitude.toFixed(3)} | x=${m.x.toFixed(2)} y=${m.y.toFixed(2)} z=${m.z.toFixed(2)} | ${new Date(m.ts).toLocaleTimeString()}`;
            });

        joinBtn.addEventListener('click', () => {
            joinRoom().catch((error) => {
                console.error('Join failed', error);
                alert('Join failed. See console for details.');
            });
        });

        motionBtn.addEventListener('click', () => {
            enableMotion().catch((error) => {
                console.error('Permission error', error);
            });
        });

        window.addEventListener('beforeunload', () => {
            if (!joined) {
                return;
            }

            fetch(`/room/${roomId}/leave`, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ userId }),
            });
        });
    });
</script>
</body>
</html>
