import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
const echoConfig = {
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'local-app-key',
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
};

try {
    window.Echo = new Echo(echoConfig);
    window.__echoInit = { ok: true, config: echoConfig };
} catch (error) {
    console.error('Echo init failed', error);
    window.__echoInit = {
        ok: false,
        config: echoConfig,
        error: error instanceof Error ? error.message : String(error),
    };
}
