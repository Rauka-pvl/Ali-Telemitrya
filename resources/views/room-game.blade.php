<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Комната {{ $roomId }}</title>
    @vite(['resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        :root {
            color-scheme: light dark;
            --bg: #0b1020;
            --surface: #131a2e;
            --surface-soft: #1a2440;
            --text: #e8ecff;
            --muted: #a9b2d0;
            --accent: #5b8cff;
            --ok: #2ec27e;
            --warn: #ffb020;
            --border: #2a365d;
        }
        body {
            font-family: Inter, Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(900px 520px at 15% 12%, rgba(105, 148, 255, 0.28), transparent 60%),
                radial-gradient(820px 520px at 85% 88%, rgba(114, 78, 255, 0.24), transparent 62%),
                radial-gradient(1000px 700px at 50% 50%, #182445 0%, #0f1831 48%, #090f20 100%);
            color: var(--text);
        }
        .container { width: min(980px, 100%); padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 32px; letter-spacing: 0.3px; }
        .subtitle { color: var(--muted); margin-bottom: 20px; }
        .join-row { display: grid; grid-template-columns: 1fr; gap: 10px; }
        input, button {
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 10px 12px;
            font-size: 14px;
            width: 100%;
        }
        input {
            background: var(--surface);
            color: var(--text);
            min-width: 0;
        }
        button {
            background: linear-gradient(180deg, #6c96ff 0%, #4f76df 100%);
            color: #fff;
            cursor: pointer;
            border: none;
            font-weight: 600;
        }
        button[disabled] { opacity: 0.55; cursor: not-allowed; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .card {
            background: linear-gradient(180deg, var(--surface) 0%, var(--surface-soft) 100%);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
            margin-top: 12px;
            box-shadow: 0 10px 24px rgba(3, 7, 18, 0.35);
        }
        .label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
        .mono { font-family: Menlo, Monaco, monospace; font-size: 13px; line-height: 1.5; }
        .players-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .slot-title { font-size: 14px; color: var(--muted); margin-bottom: 8px; }
        .slot-name { font-size: 22px; font-weight: 700; }
        .slot-empty { color: var(--warn); }
        .slot-full { color: var(--ok); }
        .hz-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
        .hz-track {
            width: 100%;
            height: 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border);
            overflow: hidden;
            position: relative;
        }
        .hz-fill {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: linear-gradient(90deg, #4f76df 0%, #73a0ff 100%);
            transition: width 120ms linear;
        }
        .hz-fill.zone-low {
            background: linear-gradient(90deg, #1e9f5a 0%, #2ec27e 100%);
        }
        .hz-fill.zone-mid {
            background: linear-gradient(90deg, #d89a10 0%, #ffb020 100%);
        }
        .hz-fill.zone-high {
            background: linear-gradient(90deg, #d63a47 0%, #f66151 100%);
        }
        .hz-threshold {
            margin-top: 6px;
            font-size: 12px;
            color: var(--muted);
        }
        .hz-value {
            margin-top: 8px;
            font-size: 13px;
            color: var(--text);
        }

        @media (max-width: 900px) {
            .grid,
            .players-grid,
            .hz-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 14px;
            }
            h1 {
                font-size: 26px;
            }
            .subtitle {
                font-size: 14px;
                margin-bottom: 14px;
            }
            .card {
                padding: 12px;
                border-radius: 12px;
                margin-top: 10px;
            }
            .slot-name {
                font-size: 20px;
            }
            .mono {
                font-size: 12px;
                word-break: break-word;
            }
        }
    </style>
</head>
<body>
<div class="container">
<h1>Лобби комнаты</h1>
<div class="subtitle">Комната: {{ $roomId }}</div>

<div class="card">
    <div class="label">Подключение</div>
    <div class="join-row">
        <input id="nameInput" type="text" maxlength="40" placeholder="Введите имя">
        <button id="joinBtn">Подключиться</button>
    </div>
    <div id="joinLog" class="mono" style="margin-top: 10px; color: var(--muted);">Не подключен</div>
</div>

<div class="card">
    <div class="label">Игроки</div>
    <div class="players-grid">
        <div class="card" style="margin-top: 0;">
            <div class="slot-title">P1</div>
            <div id="p1Name" class="slot-name slot-empty">Ожидание...</div>
        </div>
        <div class="card" style="margin-top: 0;">
            <div class="slot-title">P2</div>
            <div id="p2Name" class="slot-name slot-empty">Ожидание...</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="label">Шкала микрофона (Гц)</div>
    <div class="hz-threshold">Минимальный порог: 40 Гц</div>
    <div class="hz-grid">
        <div class="card" style="margin-top: 0;">
            <div class="slot-title">P1</div>
            <div class="hz-track"><div id="p1HzFill" class="hz-fill"></div></div>
            <div id="p1HzValue" class="hz-value">Нет данных</div>
        </div>
        <div class="card" style="margin-top: 0;">
            <div class="slot-title">P2</div>
            <div class="hz-track"><div id="p2HzFill" class="hz-fill"></div></div>
            <div id="p2HzValue" class="hz-value">Нет данных</div>
        </div>
    </div>
</div>
</div>

<script>
    window.addEventListener('load', () => {
        const roomId = @json($roomId);
        const csrfToken = '{{ csrf_token() }}';
        const clientId = localStorage.getItem(`game_client_id_${roomId}`) ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        localStorage.setItem(`game_client_id_${roomId}`, clientId);
        let joined = false;
        let heartbeatTimer = null;

        const nameInput = document.getElementById('nameInput');
        const joinBtn = document.getElementById('joinBtn');
        const joinLog = document.getElementById('joinLog');
        const p1Name = document.getElementById('p1Name');
        const p2Name = document.getElementById('p2Name');
        const p1HzFill = document.getElementById('p1HzFill');
        const p2HzFill = document.getElementById('p2HzFill');
        const p1HzValue = document.getElementById('p1HzValue');
        const p2HzValue = document.getElementById('p2HzValue');

        if (!window.Echo) {
            joinLog.textContent = 'Ошибка подключения сокета. Проверьте Reverb и Vite.';
            return;
        }

        const nameElements = { 1: p1Name, 2: p2Name };
        const hzFillElements = { 1: p1HzFill, 2: p2HzFill };
        const hzValueElements = { 1: p1HzValue, 2: p2HzValue };
        const minHz = 40;
        const maxHz = 240;

        const renderPlayers = (players) => {
            const map = new Map((players ?? []).map((p) => [p.playerIndex, p.name]));
            [1, 2].forEach((idx) => {
                const el = nameElements[idx];
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

        const updateHzBar = (playerIndex, hz) => {
            const fill = hzFillElements[playerIndex];
            const value = hzValueElements[playerIndex];
            if (!fill || !value) {
                return;
            }

            if (typeof hz !== 'number' || Number.isNaN(hz)) {
                fill.style.width = '0%';
                value.textContent = 'Нет данных';
                return;
            }

            const normalized = Math.max(0, Math.min(1, (hz - minHz) / (maxHz - minHz)));
            fill.style.width = `${(normalized * 100).toFixed(1)}%`;
            fill.classList.remove('zone-low', 'zone-mid', 'zone-high');

            if (hz >= 140) {
                fill.classList.add('zone-high');
            } else if (hz >= 80) {
                fill.classList.add('zone-mid');
            } else if (hz >= minHz) {
                fill.classList.add('zone-low');
            }

            value.textContent = hz < minHz
                ? `${hz.toFixed(1)} Гц (ниже порога)`
                : `${hz.toFixed(1)} Гц`;
        };

        const post = async (url, payload) => {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.ok === false) {
                throw new Error(data.message ?? 'Request failed');
            }
            return data;
        };

        window.Echo.channel(`room.${roomId}`)
            .listen('.players.updated', (event) => {
                renderPlayers(event.players ?? []);
            })
            .listen('.movement.updated', (event) => {
                if (event.source !== 'mic') {
                    return;
                }

                updateHzBar(event.playerIndex, Number(event.hz));
            });

        joinBtn.addEventListener('click', async () => {
            const name = nameInput.value.trim();
            if (!name) {
                joinLog.textContent = 'Сначала введите имя.';
                return;
            }

            try {
                const data = await post(`/room/${roomId}/player/join`, { clientId, name });
                joined = true;
                renderPlayers(data.players ?? []);
                joinLog.textContent = `Вы подключены как P${data.playerIndex}: ${name}`;
                joinBtn.disabled = true;
                nameInput.disabled = true;

                if (heartbeatTimer) clearInterval(heartbeatTimer);
                heartbeatTimer = setInterval(() => {
                    post(`/room/${roomId}/player/heartbeat`, { clientId }).catch(() => {});
                }, 15000);
            } catch (error) {
                joinLog.textContent = error?.message ?? 'Не удалось подключиться';
            }
        });

        window.addEventListener('beforeunload', () => {
            if (!joined) return;
            fetch(`/room/${roomId}/player/leave`, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ clientId }),
            });
        });
    });
</script>
</body>
</html>
