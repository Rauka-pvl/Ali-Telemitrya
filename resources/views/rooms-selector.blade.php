<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Выбор режима {{ $roomId }}</title>
    <style>
        :root { --bg:#0b1020; --surface:#131a2e; --surface-soft:#1a2440; --text:#e8ecff; --muted:#a9b2d0; --border:#2a365d; }
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
        .container { width: min(720px, 100%); padding: 24px; }
        .card { background: linear-gradient(180deg, var(--surface) 0%, var(--surface-soft) 100%); border:1px solid var(--border); border-radius:14px; padding:18px; margin-top:12px; }
        h1 { margin: 0 0 8px; font-size: 32px; }
        .subtitle { color: var(--muted); margin-bottom: 16px; }
        .buttons { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        a { text-decoration:none; display:block; text-align:center; padding:14px 16px; border-radius:10px; color:#fff; font-weight:700; background: linear-gradient(180deg, #6c96ff 0%, #4f76df 100%); }
        @media (max-width:640px){ .container{padding:14px;} h1{font-size:26px;} .buttons{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="container">
    <h1>Выбор режима</h1>
    <div class="subtitle">Комната: {{ $roomId }}</div>
    <div class="card">
        <div class="buttons">
            <a href="/room/game/mic/{{ $roomId }}">Микрофон</a>
            <a href="/room/game/motion/{{ $roomId }}">Движение</a>
        </div>
    </div>
</div>
</body>
</html>
