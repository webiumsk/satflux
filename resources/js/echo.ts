/**
 * Laravel Echo setup for Reverb (broadcast / push notifications).
 * Only initializes when VITE_REVERB_APP_KEY is set (broadcasting enabled).
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo;
    }
}

function getCsrfToken(): string | null {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; XSRF-TOKEN=`);
    if (parts.length === 2) {
        return parts.pop()?.split(';').shift() || null;
    }
    return null;
}

const key = import.meta.env.VITE_REVERB_APP_KEY;
const host = import.meta.env.VITE_REVERB_HOST ?? import.meta.env.VITE_APP_URL?.replace(/^https?:\/\//, '') ?? 'localhost';
const port = import.meta.env.VITE_REVERB_PORT ?? (import.meta.env.VITE_REVERB_SCHEME === 'https' ? 443 : 80);
const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
const forceTLS = scheme === 'https';
const baseUrl = import.meta.env.VITE_APP_URL ?? '';

let echoInstance: Echo | null = null;

export function getEcho(): Echo | null {
    if (!key) return null;
    if (echoInstance) return echoInstance;
    window.Pusher = Pusher;
    echoInstance = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${baseUrl}/broadcasting/auth`,
        auth: {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(getCsrfToken() ? { 'X-XSRF-TOKEN': decodeURIComponent(getCsrfToken()!) } : {}),
            },
        },
        authorizer: (channel: { name: string }) => ({
            authorize: (socketId: string, callback: (a: boolean, b?: object) => void) => {
                axios
                    .post(`${baseUrl}/broadcasting/auth`, {
                        socket_id: socketId,
                        channel_name: channel.name,
                    }, {
                        withCredentials: true,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(getCsrfToken() ? { 'X-XSRF-TOKEN': decodeURIComponent(getCsrfToken()!) } : {}),
                        },
                    })
                    .then((res) => callback(false, res.data))
                    .catch((err) => callback(true, err));
            },
        }),
    });
    return echoInstance;
}

export function isBroadcastingEnabled(): boolean {
    return Boolean(key);
}
