const STORAGE_KEY = 'satflux_cookie_consent_v1';

export type CookieConsentLevel = 'essential' | 'all';

export function hasCookieConsent(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) !== null;
  } catch {
    return true;
  }
}

export function getCookieConsent(): CookieConsentLevel | null {
  try {
    const value = localStorage.getItem(STORAGE_KEY);
    return value === 'essential' || value === 'all' ? value : null;
  } catch {
    return null;
  }
}

export function setCookieConsent(level: CookieConsentLevel): void {
  try {
    localStorage.setItem(STORAGE_KEY, level);
  } catch {
    // ignore
  }
}
