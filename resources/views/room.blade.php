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
<h1>Room: {{ $roomId }}</h1>
<div class="row">
    <button id="motionBtn">Enable Telemetry</button>
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
        const motionBtn = document.getElementById('motionBtn');
        const permissionLog = document.getElementById('permissionLog');
        const motionLog = document.getElementById('motionLog');
        const orientationLog = document.getElementById('orientationLog');

        let motionEnabled = false;

        const onDeviceMotion = (event) => {
            if (!motionEnabled) {
                return;
            }

            const x = event.accelerationIncludingGravity?.x ?? 0;
            const y = event.accelerationIncludingGravity?.y ?? 0;
            const z = event.accelerationIncludingGravity?.z ?? 0;
            const magnitude = Math.sqrt((x * x) + (y * y) + (z * z));

            motionLog.textContent = `x=${x.toFixed(3)} | y=${y.toFixed(3)} | z=${z.toFixed(3)} | magnitude=${magnitude.toFixed(3)} | ${new Date().toLocaleTimeString()}`;
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
    });
</script>
</body>
</html>
