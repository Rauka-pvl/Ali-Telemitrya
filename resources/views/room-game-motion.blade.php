<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Движение {{ $roomId }}</title>
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
        .join-row{ display:grid; grid-template-columns:1fr; gap:10px; } input,button{ border-radius:10px; border:1px solid var(--border); padding:10px 12px; font-size:14px; width:100%; }
        input{ background:var(--surface); color:var(--text); min-width:0;} button{ border:none; color:#fff; font-weight:600; cursor:pointer; background: linear-gradient(180deg,#6c96ff 0%,#4f76df 100%);}
        button[disabled]{opacity:.55; cursor:not-allowed;} .mono{font-family: Menlo, Monaco, monospace; font-size:13px; line-height:1.5;}
        .players-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; } .slot-title{font-size:14px; color:var(--muted); margin-bottom:8px;} .slot-name{font-size:22px; font-weight:700;} .slot-empty{color:var(--warn);} .slot-full{color:var(--ok);}
        @media (max-width:900px){ .players-grid{grid-template-columns:1fr;} }
        @media (max-width:640px){ .container{padding:14px;} h1{font-size:26px;} .mono{font-size:12px; word-break:break-word;} }
    </style>
</head>
<body>
<div class="container">
    <h1>Режим: Движение</h1>
    <div class="subtitle">Комната: {{ $roomId }}</div>

    <div class="card">
        <div class="label">Подключение</div>
        <div class="join-row">
            <input id="nameInput" type="text" maxlength="40" placeholder="Введите имя">
            <button id="joinBtn">Подключиться</button>
            <button id="motionBtn" disabled>Разрешить движение</button>
        </div>
        <div id="joinLog" class="mono" style="margin-top: 10px; color: var(--muted);">Не подключен</div>
        <div id="motionLog" class="mono" style="margin-top: 8px; color: var(--muted);">Движение не активировано</div>
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
    const csrfToken = '{{ csrf_token() }}';
    const clientId = localStorage.getItem(`motion_client_id_${roomId}`) ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    localStorage.setItem(`motion_client_id_${roomId}`, clientId);
    let joined = false;
    let heartbeatTimer = null;
    let lastSentAt = 0;
    let motionActive = false;

    const nameInput = document.getElementById('nameInput');
    const joinBtn = document.getElementById('joinBtn');
    const motionBtn = document.getElementById('motionBtn');
    const joinLog = document.getElementById('joinLog');
    const motionLog = document.getElementById('motionLog');
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
        const response = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken}, body:JSON.stringify(payload) });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.ok === false) throw new Error(data.message ?? 'Ошибка запроса');
        return data;
    };

    if (window.Echo) {
        window.Echo.channel(`room.${roomId}`).listen('.players.updated', (event) => renderPlayers(event.players ?? []));
    }

    const onDeviceMotion = (event) => {
        if (!motionActive) return;
        const x = event.accelerationIncludingGravity?.x ?? 0;
        const y = event.accelerationIncludingGravity?.y ?? 0;
        const z = event.accelerationIncludingGravity?.z ?? 0;
        const magnitude = Math.sqrt((x * x) + (y * y) + (z * z));
        const movement = magnitude >= 5 ? Math.max(0, (magnitude - 5) * 10) : 0;
        motionLog.textContent = `magnitude=${magnitude.toFixed(2)} | movement=${movement.toFixed(2)}`;
        const now = Date.now();
        if (now - lastSentAt >= 80) {
            lastSentAt = now;
            post(`/room/${roomId}/movement`, { clientId, source:'motion', movement, magnitude, ts:now }).catch(() => {});
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
            motionBtn.disabled = false;
            if (heartbeatTimer) clearInterval(heartbeatTimer);
            heartbeatTimer = setInterval(() => { post(`/room/${roomId}/player/heartbeat`, { clientId }).catch(() => {}); }, 15000);
        } catch (error) {
            joinLog.textContent = error?.message ?? 'Не удалось подключиться';
        }
    });

    motionBtn.addEventListener('click', async () => {
        if (!joined) return;
        const ask = async (Ctor) => (typeof Ctor?.requestPermission === 'function' ? Ctor.requestPermission() : 'granted');
        try {
            const motionPermission = await ask(DeviceMotionEvent);
            const orientationPermission = await ask(DeviceOrientationEvent);
            if (motionPermission !== 'granted' || orientationPermission !== 'granted') {
                motionLog.textContent = `Нет доступа: motion=${motionPermission}, orientation=${orientationPermission}`;
                return;
            }
            motionActive = true;
            motionBtn.disabled = true;
            motionBtn.textContent = 'Движение активировано';
            window.addEventListener('devicemotion', onDeviceMotion);
        } catch (error) {
            motionLog.textContent = `Ошибка движения: ${error?.message ?? error}`;
        }
    });

    window.addEventListener('beforeunload', () => {
        window.removeEventListener('devicemotion', onDeviceMotion);
        if (!joined) return;
        fetch(`/room/${roomId}/player/leave`, {
            method:'POST', keepalive:true,
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
            body:JSON.stringify({ clientId }),
        });
    });
});
</script>
</body>
</html>
