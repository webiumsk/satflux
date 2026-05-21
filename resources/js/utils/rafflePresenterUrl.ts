import type { PresenterTokenResponse } from '../store/raffles';

/**
 * Build a browser-reachable presenter URL. BTCPay may return a relative path or an
 * internal Docker hostname when Greenfield is called from Satflux backend.
 */
export function resolvePresenterUrl(
    data: PresenterTokenResponse,
    raffleId: string,
    btcPayPublicBase: string,
): string {
    const base = btcPayPublicBase.replace(/\/$/, '');
    const path = `/raffle/${raffleId}/present`;

    if (base && data.presenterUrl?.startsWith('http')) {
        try {
            const url = new URL(data.presenterUrl);
            const pub = new URL(base);
            if (url.pathname.includes(path) && url.host === pub.host) {
                return data.presenterUrl;
            }
        } catch {
            // fall through to rebuild
        }
    }

    if (!base) {
        return data.presenterUrl;
    }

    return `${base}${path}?token=${encodeURIComponent(data.token)}`;
}
