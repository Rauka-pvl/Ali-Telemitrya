<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>1 палка {{ $roomId }}</title>
    @vite(['resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        :root { --bg:#0b1020; --surface:#131a2e; --surface-soft:#1a2440; --text:#e8ecff; --muted:#a9b2d0; --ok:#2ec27e; --warn:#ffb020; --border:#2a365d; }
        body {
            margin:0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Inter, Arial, sans-serif;
            color:var(--text);
            background:
                radial-gradient(900px 520px at 15% 12%, rgba(105, 148, 255, 0.28), transparent 60%),
                radial-gradient(820px 520px at 85% 88%, rgba(114, 78, 255, 0.24), transparent 62%),
                radial-gradient(1000px 700px at 50% 50%, #182445 0%, #0f1831 48%, #090f20 100%);
        }
        .container { width: min(980px, 100%); padding:24px; }
        h1 { margin:0 0 8px; font-size:32px; } .subtitle{color:var(--muted); margin-bottom:20px;}
        .card{ background: linear-gradient(180deg,var(--surface) 0%, var(--surface-soft) 100%); border:1px solid var(--border); border-radius:14px; padding:14px; margin-top:12px; box-shadow:0 10px 24px rgba(3,7,18,.35);}
        .label{ font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.8px; margin-bottom:6px; }
        .join-row{ display:grid; grid-template-columns:1fr; gap:10px; }
        .mode-row{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        input,button{ border-radius:10px; border:1px solid var(--border); padding:10px 12px; font-size:14px; width:100%; }
        input{ background:var(--surface); color:var(--text); min-width:0;}
        button{ border:none; color:#fff; font-weight:600; cursor:pointer; background: linear-gradient(180deg,#6c96ff 0%,#4f76df 100%);}
        button[disabled]{opacity:.55; cursor:not-allowed;}
        .mode-btn{ background:var(--surface); color:var(--text); border:1px solid var(--border); }
        .mode-btn.active{ background:linear-gradient(180deg,#6c96ff 0%,#4f76df 100%); border-color:transparent; color:#fff; }
        .mono{font-family: Menlo, Monaco, monospace; font-size:13px; line-height:1.5;}
        .players-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .slot-title{font-size:14px; color:var(--muted); margin-bottom:8px;}
        .slot-name{font-size:22px; font-weight:700;}
        .slot-empty{color:var(--warn);} .slot-full{color:var(--ok);}
        .bar-wrap{ margin-top:10px; }
        .bar-label{ font-size:12px; color:var(--muted); margin-bottom:6px; }
        .bar-track{ width:100%; height:12px; border-radius:999px; background:rgba(255,255,255,.08); border:1px solid var(--border); overflow:hidden; }
        .bar-fill{ height:100%; width:0; border-radius:999px; transition:width 120ms linear; }
        .bar-fill-hz{ background:linear-gradient(90deg,#4f76df 0%, #73a0ff 100%); }
        .bar-fill-movement{ background:linear-gradient(90deg,#1e9f5a 0%, #2ec27e 100%); }
        .bar-ticks{ margin-top:5px; display:flex; justify-content:space-between; font-size:11px; color:var(--muted); }
        .status-badge{ margin-top:10px; display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .status-offline{ color:#ffd5d5; background:rgba(214,91,91,.22); border:1px solid rgba(214,91,91,.5); }
        .status-online{ color:#d6ffe8; background:rgba(46,194,126,.22); border:1px solid rgba(46,194,126,.5); }
        .hint{ margin-top:8px; font-size:12px; color:var(--muted); }
        @media (max-width:900px){ .players-grid{grid-template-columns:1fr;} .mode-row{grid-template-columns:1fr;} }
        @media (max-width:640px){ .container{padding:14px;} h1{font-size:26px;} .mono{font-size:12px; word-break:break-word;} }
    </style>
</head>
<body>
<div class="container">
    <h1>Режим: 1 палка</h1>
    <div class="subtitle">Комната: {{ $roomId }}</div>

    <div class="card">
        <div class="label">Подключение</div>
        <div class="join-row">
            <input id="nameInput" type="text" maxlength="40" placeholder="Введите имя">
            <button id="joinBtn">Подключиться</button>
            <div class="label" style="margin-top:4px;">Выберите ввод (только один)</div>
            <div class="mode-row">
                <button id="pickMicBtn" class="mode-btn" disabled>Микрофон</button>
                <button id="pickMotionBtn" class="mode-btn" disabled>Движение</button>
            </div>
            <button id="disconnectBtn" disabled>Отключиться</button>
        </div>
        <div id="joinLog" class="mono" style="margin-top: 10px; color: var(--muted);">Не подключен</div>
        <div id="connectionStatus" class="status-badge status-offline">Отключен</div>
        <div id="inputLog" class="mono" style="margin-top: 8px; color: var(--muted);">Ввод не выбран</div>
        <p class="hint">Можно активировать только микрофон или только движение.</p>
        <div id="hzBarWrap" class="bar-wrap" style="display:none;">
            <div class="bar-label">Шкала частоты (минимум 80 Гц)</div>
            <div class="bar-track"><div id="hzBar" class="bar-fill bar-fill-hz"></div></div>
            <div class="bar-ticks"><span>80</span><span>120</span><span>180</span><span>260+</span></div>
        </div>
        <div class="bar-wrap">
            <div class="bar-label">Шкала движения</div>
            <div class="bar-track"><div id="movementBar" class="bar-fill bar-fill-movement"></div></div>
            <div class="bar-ticks"><span>0</span><span>10</span><span>25</span><span>50+</span></div>
        </div>
    </div>

    <div class="card">
        <div class="label">Игроки</div>
        <div class="players-grid">
            <div class="card" style="margin-top:0;"><div class="slot-title">P1</div><div id="p1Name" class="slot-name slot-empty">Ожидание...</div></div>
            <div class="card" style="margin-top:0;"><div class="slot-title">P2</div><div id="p2Name" class="slot-name slot-empty">Ожидание...</div></div>
        </div>
    </div>
</div>
<script>
window.addEventListener('load', () => {
    const roomId = @json($roomId);
    const mode = 'stick';
    const csrfToken = '{{ csrf_token() }}';
    const clientId = localStorage.getItem(`stick_client_id_${roomId}`) ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    localStorage.setItem(`stick_client_id_${roomId}`, clientId);

    let joined = false;
    let heartbeatTimer = null;
    let activeInput = null;

    let stream = null;
    let audioContext = null;
    let analyser = null;
    let audioSource = null;
    let rafId = null;
    let smoothedHz = null;
    let motionActive = false;
    let lastSentAt = 0;
    let lastSentMovement = null;

    const minHz = 80;
    const maxHz = 220;
    const maxMovementMic = 50;
    const maxMovementMotion = 60;
    const silenceRmsThreshold = 0.012;
    const silencePeakThreshold = 22;
    const hzSmoothingAlpha = 0.2;
    const sendIntervalMs = 100;

    const nameInput = document.getElementById('nameInput');
    const joinBtn = document.getElementById('joinBtn');
    const pickMicBtn = document.getElementById('pickMicBtn');
    const pickMotionBtn = document.getElementById('pickMotionBtn');
    const disconnectBtn = document.getElementById('disconnectBtn');
    const joinLog = document.getElementById('joinLog');
    const connectionStatus = document.getElementById('connectionStatus');
    const inputLog = document.getElementById('inputLog');
    const hzBarWrap = document.getElementById('hzBarWrap');
    const hzBar = document.getElementById('hzBar');
    const movementBar = document.getElementById('movementBar');
    const p1Name = document.getElementById('p1Name');
    const p2Name = document.getElementById('p2Name');

    const renderPlayers = (players) => {
        const map = new Map((players ?? []).map((p) => [p.playerIndex, p.name]));
        [{idx:1, el:p1Name}, {idx:2, el:p2Name}].forEach(({idx, el}) => {
            const name = map.get(idx);
            if (name) { el.textContent = name; el.classList.remove('slot-empty'); el.classList.add('slot-full'); }
            else { el.textContent = 'Ожидание...'; el.classList.remove('slot-full'); el.classList.add('slot-empty'); }
        });
    };

    const post = async (url, payload) => {
        const response = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken}, body:JSON.stringify({ ...payload, mode }) });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.ok === false) throw new Error(data.message ?? 'Ошибка запроса');
        return data;
    };

    if (window.Echo) {
        window.Echo.channel(`room.${roomId}.${mode}`).listen('.players.updated', (event) => renderPlayers(event.players ?? []));
    }

    const syncPlayers = () => {
        post(`/room/${roomId}/player/snapshot`, {}).then((data) => renderPlayers(data.players ?? [])).catch(() => {});
    };

    const updateModeButtons = () => {
        pickMicBtn.classList.toggle('active', activeInput === 'mic');
        pickMotionBtn.classList.toggle('active', activeInput === 'motion');
    };

    const stopMic = () => {
        if (rafId) window.cancelAnimationFrame(rafId);
        rafId = null;
        if (audioSource) audioSource.disconnect();
        audioSource = null;
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
        if (audioContext) audioContext.close();
        audioContext = null;
        analyser = null;
        smoothedHz = null;
        hzBarWrap.style.display = 'none';
        hzBar.style.width = '0%';
    };

    const stopMotion = () => {
        motionActive = false;
        lastSentMovement = null;
        window.removeEventListener('devicemotion', onDeviceMotion);
    };

    const stopInput = () => {
        stopMic();
        stopMotion();
        activeInput = null;
        updateModeButtons();
        movementBar.style.width = '0%';
        inputLog.textContent = 'Ввод не выбран';
    };

    const resetUiAfterDisconnect = () => {
        joined = false;
        if (heartbeatTimer) clearInterval(heartbeatTimer);
        heartbeatTimer = null;
        stopInput();

        joinBtn.disabled = false;
        nameInput.disabled = false;
        pickMicBtn.disabled = true;
        pickMotionBtn.disabled = true;
        disconnectBtn.disabled = true;
        joinLog.textContent = 'Вы отключены';
        connectionStatus.textContent = 'Отключен';
        connectionStatus.classList.remove('status-online');
        connectionStatus.classList.add('status-offline');
    };

    const micLoop = () => {
        if (activeInput !== 'mic' || !analyser || !audioContext) return;

        const freqData = new Uint8Array(analyser.frequencyBinCount);
        const timeData = new Uint8Array(analyser.fftSize);
        analyser.getByteFrequencyData(freqData);
        analyser.getByteTimeDomainData(timeData);

        let energy = 0;
        for (let i = 0; i < timeData.length; i += 1) {
            const normalized = (timeData[i] - 128) / 128;
            energy += normalized * normalized;
        }
        const rms = Math.sqrt(energy / timeData.length);

        const minBin = Math.max(2, Math.floor((minHz * analyser.fftSize) / audioContext.sampleRate));
        const maxBin = Math.min(freqData.length - 1, Math.floor((maxHz * analyser.fftSize) / audioContext.sampleRate));
        let maxValue = -1;
        let maxIndex = minBin;
        for (let i = minBin; i <= maxBin; i += 1) {
            if (freqData[i] > maxValue) { maxValue = freqData[i]; maxIndex = i; }
        }

        const rawHz = (maxIndex * audioContext.sampleRate) / analyser.fftSize;
        const isSilent = rms < silenceRmsThreshold || maxValue < silencePeakThreshold;
        const hz = isSilent
            ? 0
            : (smoothedHz === null ? rawHz : (smoothedHz * (1 - hzSmoothingAlpha)) + (rawHz * hzSmoothingAlpha));
        smoothedHz = hz;

        let movement = 0;
        if (!isSilent && hz >= minHz) {
            const pitchPart = (hz - minHz) / 4;
            const peakPart = Math.max(0, (maxValue - 35) / 4);
            const rmsPart = Math.max(0, (rms - 0.02) * 120);
            movement = pitchPart + peakPart + rmsPart;
            if (rms > 0.08 || maxValue > 170) movement *= 1.6;
        }

        inputLog.textContent = isSilent
            ? `Микрофон | тишина | movement=0.00`
            : `Микрофон | Hz=${hz.toFixed(2)} | movement=${movement.toFixed(2)}`;

        hzBar.style.width = `${Math.max(0, Math.min(100, ((hz - minHz) / (maxHz - minHz)) * 100)).toFixed(1)}%`;
        movementBar.style.width = `${Math.max(0, Math.min(100, (movement / maxMovementMic) * 100)).toFixed(1)}%`;

        const now = Date.now();
        if (hz >= minHz && now - lastSentAt >= sendIntervalMs) {
            lastSentAt = now;
            post(`/room/${roomId}/movement`, { clientId, source:'mic', movement, hz, ts:now }).catch(() => {});
        }

        rafId = window.requestAnimationFrame(micLoop);
    };

    const onDeviceMotion = (event) => {
        if (activeInput !== 'motion' || !motionActive) return;

        const x = event.accelerationIncludingGravity?.x ?? 0;
        const y = event.accelerationIncludingGravity?.y ?? 0;
        const z = event.accelerationIncludingGravity?.z ?? 0;
        const magnitude = Math.sqrt((x * x) + (y * y) + (z * z));
        const movement = magnitude >= 5 ? Math.max(0, (magnitude - 5) * 10) : 0;

        inputLog.textContent = `Движение | magnitude=${magnitude.toFixed(2)} | movement=${movement.toFixed(2)}`;
        movementBar.style.width = `${Math.max(0, Math.min(100, (movement / maxMovementMotion) * 100)).toFixed(1)}%`;

        const now = Date.now();
        const changedEnough = lastSentMovement === null || Math.abs(movement - lastSentMovement) > 2;
        if (now - lastSentAt >= 80 && changedEnough) {
            lastSentAt = now;
            lastSentMovement = movement;
            post(`/room/${roomId}/movement`, { clientId, source:'motion', movement, magnitude, ts:now }).catch(() => {});
        }
    };

    const askPermission = async (Ctor) => (typeof Ctor?.requestPermission === 'function' ? Ctor.requestPermission() : 'granted');

    const enableMic = async () => {
        if (!joined) return;
        if (activeInput === 'mic') return;

        stopInput();
        activeInput = 'mic';
        updateModeButtons();
        lastSentAt = 0;

        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            audioSource = audioContext.createMediaStreamSource(stream);
            analyser = audioContext.createAnalyser();
            analyser.fftSize = 2048;
            analyser.smoothingTimeConstant = 0.2;
            audioSource.connect(analyser);
            hzBarWrap.style.display = 'block';
            inputLog.textContent = 'Микрофон активирован';
            micLoop();
        } catch (error) {
            stopInput();
            inputLog.textContent = `Ошибка микрофона: ${error?.message ?? error}`;
        }
    };

    const enableMotion = async () => {
        if (!joined) return;
        if (activeInput === 'motion') return;

        stopInput();
        activeInput = 'motion';
        updateModeButtons();
        lastSentAt = 0;
        lastSentMovement = null;

        try {
            const motionPermission = await askPermission(DeviceMotionEvent);
            const orientationPermission = await askPermission(DeviceOrientationEvent);
            if (motionPermission !== 'granted' || orientationPermission !== 'granted') {
                stopInput();
                inputLog.textContent = `Нет доступа: motion=${motionPermission}, orientation=${orientationPermission}`;
                return;
            }
            motionActive = true;
            window.addEventListener('devicemotion', onDeviceMotion);
            inputLog.textContent = 'Движение активировано';
        } catch (error) {
            stopInput();
            inputLog.textContent = `Ошибка движения: ${error?.message ?? error}`;
        }
    };

    joinBtn.addEventListener('click', async () => {
        const name = nameInput.value.trim();
        if (!name) return;
        try {
            const data = await post(`/room/${roomId}/player/join`, { clientId, name });
            joined = true;
            renderPlayers(data.players ?? []);
            joinLog.textContent = `Вы подключены как P${data.playerIndex}: ${name}`;
            joinBtn.disabled = true;
            nameInput.disabled = true;
            pickMicBtn.disabled = false;
            pickMotionBtn.disabled = false;
            disconnectBtn.disabled = false;
            connectionStatus.textContent = 'Подключен';
            connectionStatus.classList.remove('status-offline');
            connectionStatus.classList.add('status-online');
            if (heartbeatTimer) clearInterval(heartbeatTimer);
            heartbeatTimer = setInterval(() => { post(`/room/${roomId}/player/heartbeat`, { clientId }).catch(() => {}); }, 15000);
            syncPlayers();
        } catch (error) {
            joinLog.textContent = error?.message ?? 'Не удалось подключиться';
        }
    });

    pickMicBtn.addEventListener('click', () => { enableMic().catch(() => {}); });
    pickMotionBtn.addEventListener('click', () => { enableMotion().catch(() => {}); });

    disconnectBtn.addEventListener('click', async () => {
        if (!joined) return;
        try {
            stopInput();
            await post(`/room/${roomId}/player/leave`, { clientId });
            resetUiAfterDisconnect();
            syncPlayers();
        } catch (error) {
            joinLog.textContent = `Ошибка отключения: ${error?.message ?? error}`;
        }
    });

    window.addEventListener('beforeunload', () => {
        stopInput();
        if (!joined) return;
        fetch(`/room/${roomId}/player/leave`, {
            method:'POST', keepalive:true,
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
            body:JSON.stringify({ clientId, mode }),
        });
    });

    setInterval(syncPlayers, 8000);
    syncPlayers();
});
</script>
</body>
</html>
