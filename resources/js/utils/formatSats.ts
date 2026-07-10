/**
 * Locale-aware sats formatting: 99350 -> "99 350 sats" (sk) / "99,350 sats" (en).
 * Falls back to the runtime default locale when none is given.
 */
export function formatSats(sats: number, locale?: string): string {
    const value = Number.isFinite(sats) ? Math.trunc(sats) : 0;
    return `${value.toLocaleString(locale)} sats`;
}
