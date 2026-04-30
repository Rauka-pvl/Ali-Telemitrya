<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Админ комнаты {{ $roomId }}</title>
    @vite(['resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        :root { --bg:#0b1020; --surface:#131a2e; --surface-soft:#1a2440; --text:#e8ecff; --muted:#a9b2d0; --ok:#2ec27e; --warn:#ffb020; --danger:#d65b5b; --border:#2a365d; }
        body {
            margin:0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            font-family: Inter, Arial, sans-serif;
            color:var(--text);
            background:
                radial-gradient(900px 520px at 15% 12%, rgba(105, 148, 255, 0.28), transparent 60%),
                radial-gradient(820px 520px at 85% 88%, rgba(114, 78, 255, 0.24), transparent 62%),
                radial-gradient(1000px 700px at 50% 50%, #182445 0%, #0f1831 48%, #090f20 100%);
        }
        .container { width:min(980px,100%); padding:24px; }
        h1 { margin:0 0 8px; font-size:32px; }
        .subtitle { color:var(--muted); margin-bottom:18px; }
        .card { background: linear-gradient(180deg,var(--surface) 0%, var(--surface-soft) 100%); border:1px solid var(--border); border-radius:14px; padding:14px; margin-top:12px; box-shadow:0 10px 24px rgba(3,7,18,.35); }
        .label { font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.8px; margin-bottom:8px; }
        .mode-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .slot-title { font-size:14px; color:var(--muted); margin-bottom:8px; }
        .slot-name { font-size:22px; font-weight:700; margin-bottom:10px; }
        .slot-empty { color:var(--warn); }
        .slot-full { color:var(--ok); }
        button { width:100%; border:none; border-radius:10px; padding:10px 12px; font-size:14px; color:#fff; font-weight:700; cursor:pointer; background:linear-gradient(180deg,var(--danger) 0%, #b84343 100%); }
        .mono { font-family: Menlo, Monaco, monospace; font-size:13px; line-height:1.5; color:var(--muted); margin-top:10px; }
        @media (max-width:900px){ .mode-grid{grid-template-columns:1fr;} }
        @media (max-width:640px){ .container{padding:14px;} h1{font-size:26px;} .slot-name{font-size:20px;} }
    </style>
</head>
<body>
<div class="container">
    <h1>Админ-панель комнаты</h1>
    <div class="subtitle">Комната: {{ $roomId }}</div>

    <div class="card">
        <div class="label">Режим: Микрофон</div>
        <div class="mode-grid">
            <div class="card" style="margin-top:0;">
                <div class="slot-title">P1</div>
                <div id="micP1Name" class="slot-name slot-empty">Ожидание...</div>
                <button id="kickMicP1">Выгнать P1 (Mic)</button>
            </div>
            <div class="card" style="margin-top:0;">
                <div class="slot-title">P2</div>
                <div id="micP2Name" class="slot-name slot-empty">Ожидание...</div>
                <button id="kickMicP2">Выгнать P2 (Mic)</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="label">Режим: Движение</div>
        <div class="mode-grid">
            <div class="card" style="margin-top:0;">
                <div class="slot-title">P1</div>
                <div id="motionP1Name" class="slot-name slot-empty">Ожидание...</div>
                <button id="kickMotionP1">Выгнать P1 (Motion)</button>
            </div>
            <div class="card" style="margin-top:0;">
                <div class="slot-title">P2</div>
                <div id="motionP2Name" class="slot-name slot-empty">Ожидание...</div>
                <button id="kickMotionP2">Выгнать P2 (Motion)</button>
            </div>
        </div>
    </div>

    <div id="adminLog" class="mono">Готово к управлению слотами.</div>
</div>

<script>
window.addEventListener('load', () => {
    const roomId = @json($roomId);
    const csrfToken = '{{ csrf_token() }}';
    const adminLog = document.getElementById('adminLog');

    const slots = {
        mic: { 1: document.getElementById('micP1Name'), 2: document.getElementById('micP2Name') },
        motion: { 1: document.getElementById('motionP1Name'), 2: document.getElementById('motionP2Name') },
    };

    const post = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.ok === false) {
            throw new Error(data.message ?? 'Ошибка запроса');
        }
        return data;
    };

    const renderPlayers = (mode, players) => {
        const map = new Map((players ?? []).map((p) => [p.playerIndex, p.name]));
        [1, 2].forEach((idx) => {
            const el = slots[mode][idx];
            const name = map.get(idx);
            if (name) {
                el.textContent = name;
                el.classList.remove('slot-empty');
                el.classList.add('slot-full');
            } else {
                el.textContent = 'Ожидание...';
                el.classList.remove('slot-full');
                el.classList.add('slot-empty');
            }
        });
    };

    const syncMode = (mode) => {
        post(`/room/${roomId}/player/snapshot`, { mode })
            .then((data) => renderPlayers(mode, data.players ?? []))
            .catch((error) => { adminLog.textContent = `Ошибка sync ${mode}: ${error?.message ?? error}`; });
    };

    const kick = (mode, playerIndex) => {
        post(`/room/${roomId}/player/kick`, { mode, playerIndex })
            .then((data) => {
                renderPlayers(mode, data.players ?? []);
                adminLog.textContent = `Игрок P${playerIndex} выгнан из режима ${mode}.`;
            })
            .catch((error) => { adminLog.textContent = `Ошибка kick: ${error?.message ?? error}`; });
    };

    document.getElementById('kickMicP1').addEventListener('click', () => kick('mic', 1));
    document.getElementById('kickMicP2').addEventListener('click', () => kick('mic', 2));
    document.getElementById('kickMotionP1').addEventListener('click', () => kick('motion', 1));
    document.getElementById('kickMotionP2').addEventListener('click', () => kick('motion', 2));

    if (window.Echo) {
        window.Echo.channel(`room.${roomId}.mic`)
            .listen('.players.updated', (event) => renderPlayers('mic', event.players ?? []));
        window.Echo.channel(`room.${roomId}.motion`)
            .listen('.players.updated', (event) => renderPlayers('motion', event.players ?? []));
    }

    setInterval(() => {
        syncMode('mic');
        syncMode('motion');
    }, 8000);

    syncMode('mic');
    syncMode('motion');
});
</script>
</body>
</html>
