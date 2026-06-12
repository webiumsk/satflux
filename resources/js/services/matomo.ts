import { getCookieConsent } from '../composables/useCookieConsent';

declare global {
    interface Window {
        _paq?: unknown[][];
    }
}

let matomoLoaded = false;

function getMatomoConfig(): { url: string; siteId: string } | null {
    const url = document.querySelector('meta[name="satflux-matomo-url"]')?.getAttribute('content')?.trim();
    const siteId = document.querySelector('meta[name="satflux-matomo-site-id"]')?.getAttribute('content')?.trim();
    if (!url || !siteId) {
        return null;
    }
    return { url, siteId };
}

/** Load Matomo only after analytics cookie consent. */
export function loadMatomoIfConsented(): void {
    if (matomoLoaded || getCookieConsent() !== 'all') {
        return;
    }

    const config = getMatomoConfig();
    if (!config) {
        return;
    }

    matomoLoaded = true;
    const trackerBase = config.url.replace(/\/$/, '') + '/';
    const _paq = (window._paq = window._paq || []);
    _paq.push(['setTrackerUrl', trackerBase + 'matomo.php']);
    _paq.push(['setSiteId', config.siteId]);
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);

    const script = document.createElement('script');
    script.async = true;
    script.src = trackerBase + 'matomo.js';
    document.head.appendChild(script);
}

/** Called when user accepts all cookies in the banner. */
export function onAnalyticsConsentGranted(): void {
    loadMatomoIfConsented();
}
