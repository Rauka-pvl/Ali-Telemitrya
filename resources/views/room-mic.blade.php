<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mic Room {{ $roomId }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; max-width: 760px; }
        h1 { margin-bottom: 8px; }
        .row { margin: 12px 0; }
        button { padding: 8px 12px; margin-right: 8px; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 12px; margin-top: 12px; }
        .mono { font-family: Menlo, Monaco, monospace; font-size: 13px; }
    </style>
</head>
<body>
<h1>Mic Room: {{ $roomId }}</h1>
<div class="row">
    <input id="nameInput" type="text" maxlength="40" placeholder="Your name">
    <button id="joinBtn">Join as Player</button>
    <button id="micBtn">Enable Microphone</button>
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
    <strong>Estimated dominant frequency</strong>
    <div id="hzLog" class="mono">No data yet</div>
</div>

<div class="card">
    <strong>Top-3 peaks</strong>
    <div id="peaksLog" class="mono">No data yet</div>
</div>

<div class="card">
    <strong>Spectrum</strong>
    <canvas id="spectrumCanvas" width="700" height="220" style="width: 100%; border: 1px solid #ddd;"></canvas>
</div>

<script>
    window.addEventListener('load', () => {
        const roomId = @json($roomId);
        const clientId = localStorage.getItem(`mic_client_id_${roomId}`) ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        localStorage.setItem(`mic_client_id_${roomId}`, clientId);
        let playerIndex = null;

        const nameInput = document.getElementById('nameInput');
        const joinBtn = document.getElementById('joinBtn');
        const playerLog = document.getElementById('playerLog');
        const micBtn = document.getElementById('micBtn');
        const permissionLog = document.getElementById('permissionLog');
        const hzLog = document.getElementById('hzLog');
        const peaksLog = document.getElementById('peaksLog');
        const spectrumCanvas = document.getElementById('spectrumCanvas');
        const ctx = spectrumCanvas.getContext('2d');

        let audioContext = null;
        let analyser = null;
        let source = null;
        let stream = null;
        let animationFrameId = null;
        let smoothedHz = null;
        let lastSentAt = 0;
        let heartbeatTimer = null;

        const SILENCE_THRESHOLD = 20;
        const SMOOTHING_ALPHA = 0.2;

        const hzFromBin = (binIndex, sampleRate, fftSize) => {
            return (binIndex * sampleRate) / fftSize;
        };

        const getTopPeaks = (spectrum, sampleRate, fftSize, limit = 3) => {
            const peaks = [];
            const startBin = 2;

            for (let i = startBin; i < spectrum.length - 1; i += 1) {
                const prev = spectrum[i - 1];
                const current = spectrum[i];
                const next = spectrum[i + 1];

                if (current > prev && current > next && current >= SILENCE_THRESHOLD) {
                    peaks.push({
                        bin: i,
                        value: current,
                        hz: hzFromBin(i, sampleRate, fftSize),
                    });
                }
            }

            peaks.sort((a, b) => b.value - a.value);
            return peaks.slice(0, limit);
        };

        const drawSpectrum = (spectrum) => {
            if (!ctx) {
                return;
            }

            const width = spectrumCanvas.width;
            const height = spectrumCanvas.height;
            const barWidth = width / spectrum.length;

            ctx.clearRect(0, 0, width, height);
            ctx.fillStyle = '#f8fafc';
            ctx.fillRect(0, 0, width, height);

            for (let i = 0; i < spectrum.length; i += 1) {
                const value = spectrum[i] / 255;
                const barHeight = value * height;
                ctx.fillStyle = value > 0.4 ? '#2563eb' : '#60a5fa';
                ctx.fillRect(i * barWidth, height - barHeight, Math.max(1, barWidth), barHeight);
            }
        };

        const renderLoop = () => {
            if (!analyser || !audioContext) {
                return;
            }

            const data = new Uint8Array(analyser.frequencyBinCount);
            analyser.getByteFrequencyData(data);

            drawSpectrum(data);

            const peaks = getTopPeaks(data, audioContext.sampleRate, analyser.fftSize, 3);

            if (peaks.length === 0) {
                hzLog.textContent = `dominant=silence | ${new Date().toLocaleTimeString()}`;
                peaksLog.textContent = 'No significant peaks';
                smoothedHz = null;
            } else {
                const dominant = peaks[0].hz;
                smoothedHz = smoothedHz === null
                    ? dominant
                    : (smoothedHz * (1 - SMOOTHING_ALPHA)) + (dominant * SMOOTHING_ALPHA);

                hzLog.textContent = `dominant=${dominant.toFixed(2)} Hz | smooth=${smoothedHz.toFixed(2)} Hz | ${new Date().toLocaleTimeString()}`;
                peaksLog.textContent = peaks
                    .map((peak, index) => `#${index + 1}: ${peak.hz.toFixed(2)} Hz (amp=${peak.value})`)
                    .join(' | ');
            }

            const dominantHz = peaks.length > 0 ? peaks[0].hz : null;
            const dominantAmplitude = peaks.length > 0 ? peaks[0].value : 0;
            const normalizedLevel = Math.min(1, dominantAmplitude / 255);
            const now = Date.now();

            if (playerIndex && now - lastSentAt >= 100) {
                lastSentAt = now;

                fetch(`/room/${roomId}/mic-level`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        clientId,
                        level: normalizedLevel,
                        hz: dominantHz,
                        ts: now,
                    }),
                }).catch((error) => {
                    console.error('Mic level send failed', error);
                });
            }

            animationFrameId = window.requestAnimationFrame(renderLoop);
        };

        const enableMic = async () => {
            if (!playerIndex) {
                alert('Join first');
                return;
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                permissionLog.textContent = 'Microphone API is not supported in this browser.';
                return;
            }

            stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: false,
                    noiseSuppression: false,
                    autoGainControl: false,
                },
            });

            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            source = audioContext.createMediaStreamSource(stream);
            analyser = audioContext.createAnalyser();
            analyser.fftSize = 2048;
            analyser.smoothingTimeConstant = 0.2;
            source.connect(analyser);

            permissionLog.textContent = 'Granted';
            micBtn.textContent = 'Microphone Enabled';

            if (animationFrameId) {
                window.cancelAnimationFrame(animationFrameId);
            }
            renderLoop();
        };

        micBtn.addEventListener('click', () => {
            enableMic().catch((error) => {
                console.error('Microphone permission error', error);
                permissionLog.textContent = `Denied/Error: ${error?.message ?? error}`;
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
            if (animationFrameId) {
                window.cancelAnimationFrame(animationFrameId);
            }

            if (source) {
                source.disconnect();
            }

            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
            }

            if (audioContext) {
                audioContext.close();
            }

            if (playerIndex) {
                fetch(`/room/${roomId}/player/leave`, {
                    method: 'POST',
                    keepalive: true,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ clientId }),
                });
            }
        });
    });
</script>
</body>
</html>
