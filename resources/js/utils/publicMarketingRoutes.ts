/** Paths served by the lightweight public SPA entry (must match PublicSpaRoutes.php). */

const EXACT = new Set([
    '',
    'pricing',
    'login',
    'register',
    'password/reset',
    'support',
    'documentation',
    'faq',
    'success',
    'billing/success',
]);

const PREFIXES = ['legal/', 'documentation/', 'auth/verify-email/'];

export function normalizePath(path: string): string {
    return path.replace(/^\/+|\/+$/g, '');
}

export function isPublicMarketingPath(path: string): boolean {
    const normalized = normalizePath(path);
    if (EXACT.has(normalized)) {
        return true;
    }
    return PREFIXES.some(
        (prefix) => normalized === prefix.replace(/\/$/, '') || normalized.startsWith(prefix),
    );
}

/** Full page load into the authenticated app bundle. */
export function navigateToAppPath(path: string): void {
    window.location.assign(path.startsWith('/') ? path : `/${path}`);
}
