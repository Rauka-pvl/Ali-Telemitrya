<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Room {{ $roomId }}</title>
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
<h1>Telemetry Room: {{ $roomId }}</h1>
<div class="row">
    <input id="nameInput" type="text" maxlength="40" placeholder="Your name">
    <button id="joinBtn">Join as Player</button>
    <button id="motionBtn">Enable Telemetry</button>
</div>

<div class="card">
    <strong>Player status</strong>
    <div id="playerLog" class="mono">Not joined</div>
</div>

<div class="card">
    <strong>Permission status</strong>
    <div id="permissionLog" class="mono">Not requested</div>
</div>

<div class="card">
    <strong>Motion telemetry</strong>
    <div id="motionLog" class="mono">No data yet</div>
</div>

<div class="card">
    <strong>Orientation telemetry</strong>
    <div id="orientationLog" class="mono">No data yet</div>
</div>

<script>
    window.addEventListener('load', () => {
        const roomId = @json($roomId);
        const clientId = localStorage.getItem(`tele_client_id_${roomId}`) ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        localStorage.setItem(`tele_client_id_${roomId}`, clientId);
        let playerIndex = null;

        const nameInput = document.getElementById('nameInput');
        const joinBtn = document.getElementById('joinBtn');
        const playerLog = document.getElementById('playerLog');
        const motionBtn = document.getElementById('motionBtn');
        const permissionLog = document.getElementById('permissionLog');
        const motionLog = document.getElementById('motionLog');
        const orientationLog = document.getElementById('orientationLog');

        let motionEnabled = false;
        let lastSentAt = 0;
        let heartbeatTimer = null;

        const sendMotion = async (x, y, z, magnitude) => {
            if (!playerIndex) return;
            await fetch(`/room/${roomId}/motion`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    clientId,
                    x,
                    y,
                    z,
                    magnitude,
                    ts: Date.now(),
                }),
            });
        };

        const onDeviceMotion = (event) => {
            if (!motionEnabled) {
                return;
            }

            const x = event.accelerationIncludingGravity?.x ?? 0;
            const y = event.accelerationIncludingGravity?.y ?? 0;
            const z = event.accelerationIncludingGravity?.z ?? 0;
            const magnitude = Math.sqrt((x * x) + (y * y) + (z * z));

            motionLog.textContent = `x=${x.toFixed(3)} | y=${y.toFixed(3)} | z=${z.toFixed(3)} | magnitude=${magnitude.toFixed(3)} | ${new Date().toLocaleTimeString()}`;

            const now = Date.now();
            if (now - lastSentAt >= 80) {
                lastSentAt = now;
                sendMotion(x, y, z, magnitude).catch((error) => {
                    console.error('Motion send failed', error);
                });
            }
        };

        const onDeviceOrientation = (event) => {
            if (!motionEnabled) {
                return;
            }

            const alpha = event.alpha ?? 0;
            const beta = event.beta ?? 0;
            const gamma = event.gamma ?? 0;

            orientationLog.textContent = `alpha=${alpha.toFixed(2)} | beta=${beta.toFixed(2)} | gamma=${gamma.toFixed(2)} | ${new Date().toLocaleTimeString()}`;
        };

        const enableMotion = async () => {
            if (!playerIndex) {
                alert('Join first');
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
                permissionLog.textContent = `Denied: motion=${motionPermission}, orientation=${orientationPermission}`;
                alert('Доступ к движениям/ориентации не выдан. Проверь Safari settings и HTTPS.');
                return;
            }

            if (!motionEnabled) {
                window.addEventListener('devicemotion', onDeviceMotion);
                window.addEventListener('deviceorientation', onDeviceOrientation);
                motionEnabled = true;
                motionBtn.textContent = 'Telemetry Enabled';
                permissionLog.textContent = 'Granted';
            }
        };

        motionBtn.addEventListener('click', () => {
            enableMotion().catch((error) => {
                console.error('Permission error', error);
                permissionLog.textContent = `Error: ${error?.message ?? error}`;
            });
        });

        joinBtn.addEventListener('click', async () => {
            const name = nameInput.value.trim();
            if (!name) {
                alert('Введите имя');
                return;
            }

            try {
                const response = await fetch(`/room/${roomId}/player/join`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ clientId, name }),
                });

                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message ?? 'Join failed');
                }

                playerIndex = data.playerIndex;
                playerLog.textContent = `Connected as P${playerIndex}: ${name}`;
                joinBtn.disabled = true;
                nameInput.disabled = true;

                if (heartbeatTimer) clearInterval(heartbeatTimer);
                heartbeatTimer = setInterval(() => {
                    fetch(`/room/${roomId}/player/heartbeat`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ clientId }),
                    }).catch(() => {});
                }, 15000);
            } catch (error) {
                playerLog.textContent = `Join error: ${error?.message ?? error}`;
            }
        });

        window.addEventListener('beforeunload', () => {
            if (!playerIndex) return;
            fetch(`/room/${roomId}/player/leave`, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ clientId }),
            });
        });
    });
</script>
</body>
</html>
