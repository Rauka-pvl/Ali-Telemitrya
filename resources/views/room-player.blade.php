<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Player Room {{ $roomId }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; max-width: 760px; }
        h1 { margin-bottom: 8px; }
        .row { margin: 12px 0; display: flex; gap: 8px; flex-wrap: wrap; }
        input, button { padding: 8px 12px; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 12px; margin-top: 12px; }
        .mono { font-family: Menlo, Monaco, monospace; font-size: 13px; }
    </style>
</head>
<body>
<h1>Player Room: {{ $roomId }}</h1>
<div class="row">
    <input id="nameInput" type="text" maxlength="40" placeholder="Your name">
    <button id="joinBtn">Join</button>
</div>
<div class="row">
    <button id="modeMicBtn" disabled>Use Microphone</button>
    <button id="modeMotionBtn" disabled>Use Motion</button>
</div>

<div class="card">
    <strong>Player</strong>
    <div id="playerLog" class="mono">Not joined</div>
</div>
<div class="card">
    <strong>Mode</strong>
    <div id="modeLog" class="mono">Select source after join</div>
</div>
<div class="card">
    <strong>Movement</strong>
    <div id="movementLog" class="mono">No data yet</div>
</div>

<script>
window.addEventListener('load', () => {
    const roomId = @json($roomId);
    const csrfToken = '{{ csrf_token() }}';
    const clientId = localStorage.getItem(`player_client_id_${roomId}`) ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    localStorage.setItem(`player_client_id_${roomId}`, clientId);

    const nameInput = document.getElementById('nameInput');
    const joinBtn = document.getElementById('joinBtn');
    const modeMicBtn = document.getElementById('modeMicBtn');
    const modeMotionBtn = document.getElementById('modeMotionBtn');
    const playerLog = document.getElementById('playerLog');
    const modeLog = document.getElementById('modeLog');
    const movementLog = document.getElementById('movementLog');

    let joined = false;
    let playerIndex = null;
    let heartbeatTimer = null;
    let activeMode = null;
    let rafId = null;
    let stream = null;
    let audioContext = null;
    let analyser = null;
    let source = null;
    let lastSentAt = 0;

    const post = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message ?? 'Request failed');
        return data;
    };

    const sendMovement = async ({ sourceType, movement, hz = null, magnitude = null }) => {
        if (!joined) return;
        await post(`/room/${roomId}/movement`, {
            clientId,
            source: sourceType,
            movement,
            hz,
            magnitude,
            ts: Date.now(),
        });
    };

    const stopAllSensors = () => {
        window.removeEventListener('devicemotion', onDeviceMotion);
        if (rafId) window.cancelAnimationFrame(rafId);
        rafId = null;
        if (source) source.disconnect();
        source = null;
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
        if (audioContext) audioContext.close();
        audioContext = null;
        analyser = null;
    };

    const onDeviceMotion = (event) => {
        if (activeMode !== 'motion') return;
        const x = event.accelerationIncludingGravity?.x ?? 0;
        const y = event.accelerationIncludingGravity?.y ?? 0;
        const z = event.accelerationIncludingGravity?.z ?? 0;
        const magnitude = Math.sqrt((x * x) + (y * y) + (z * z));
        const movement = magnitude >= 5 ? Math.max(0, (magnitude - 5) * 10) : 0;
        movementLog.textContent = `mode=motion | magnitude=${magnitude.toFixed(2)} | movement=${movement.toFixed(2)}`;

        const now = Date.now();
        if (now - lastSentAt >= 80) {
            lastSentAt = now;
            sendMovement({ sourceType: 'motion', movement, magnitude }).catch(() => {});
        }
    };

    const micLoop = () => {
        if (activeMode !== 'mic' || !analyser || !audioContext) return;
        const data = new Uint8Array(analyser.frequencyBinCount);
        analyser.getByteFrequencyData(data);

        let maxValue = -1;
        let maxIndex = 0;
        for (let i = 2; i < data.length; i += 1) {
            if (data[i] > maxValue) {
                maxValue = data[i];
                maxIndex = i;
            }
        }
        const hz = (maxIndex * audioContext.sampleRate) / analyser.fftSize;
        const movement = hz > 40 ? (hz - 40) / 5 : 0;
        movementLog.textContent = `mode=mic | hz=${hz.toFixed(2)} | movement=${movement.toFixed(2)}`;

        const now = Date.now();
        if (now - lastSentAt >= 100) {
            lastSentAt = now;
            sendMovement({ sourceType: 'mic', movement, hz }).catch(() => {});
        }

        rafId = window.requestAnimationFrame(micLoop);
    };

    const enableMotionMode = async () => {
        if (!joined) return;
        stopAllSensors();
        activeMode = 'motion';
        modeLog.textContent = 'Motion mode active (threshold magnitude >= 5)';
        const ask = async (Ctor) => (typeof Ctor?.requestPermission === 'function' ? Ctor.requestPermission() : 'granted');
        const motionPerm = await ask(DeviceMotionEvent);
        const orientPerm = await ask(DeviceOrientationEvent);
        if (motionPerm !== 'granted' || orientPerm !== 'granted') {
            modeLog.textContent = `Permission denied: motion=${motionPerm}, orientation=${orientPerm}`;
            activeMode = null;
            return;
        }
        window.addEventListener('devicemotion', onDeviceMotion);
    };

    const enableMicMode = async () => {
        if (!joined) return;
        stopAllSensors();
        activeMode = 'mic';
        modeLog.textContent = 'Microphone mode active (threshold > 40 Hz)';
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        source = audioContext.createMediaStreamSource(stream);
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 2048;
        analyser.smoothingTimeConstant = 0.2;
        source.connect(analyser);
        micLoop();
    };

    joinBtn.addEventListener('click', async () => {
        const name = nameInput.value.trim();
        if (!name) return;
        try {
            const data = await post(`/room/${roomId}/player/join`, { clientId, name });
            playerIndex = data.playerIndex;
            joined = true;
            playerLog.textContent = `P${playerIndex}: ${name}`;
            modeMicBtn.disabled = false;
            modeMotionBtn.disabled = false;
            joinBtn.disabled = true;
            nameInput.disabled = true;

            if (heartbeatTimer) clearInterval(heartbeatTimer);
            heartbeatTimer = setInterval(() => {
                post(`/room/${roomId}/player/heartbeat`, { clientId }).catch(() => {});
            }, 15000);
        } catch (error) {
            playerLog.textContent = `Join error: ${error?.message ?? error}`;
        }
    });

    modeMicBtn.addEventListener('click', () => {
        enableMicMode().catch((error) => {
            modeLog.textContent = `Mic error: ${error?.message ?? error}`;
        });
    });

    modeMotionBtn.addEventListener('click', () => {
        enableMotionMode().catch((error) => {
            modeLog.textContent = `Motion error: ${error?.message ?? error}`;
        });
    });

    window.addEventListener('beforeunload', () => {
        stopAllSensors();
        if (!joined) return;
        fetch(`/room/${roomId}/player/leave`, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ clientId }),
        });
    });
});
</script>
</body>
</html>
