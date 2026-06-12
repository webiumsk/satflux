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

const LEGAL_SLUGS = new Set(['terms', 'privacy', 'imprint', 'dpa']);

export function normalizePath(path: string): string {
    return path.replace(/^\/+|\/+$/g, '');
}

function matchesLegal(normalized: string): boolean {
    const match = normalized.match(/^legal\/([^/]+)$/);
    return match !== null && LEGAL_SLUGS.has(match[1]);
}

function matchesDocumentation(normalized: string): boolean {
    return normalized === 'documentation' || /^documentation\/[^/]+$/.test(normalized);
}

function matchesVerifyEmail(normalized: string): boolean {
    const parts = normalized.split('/');
    return (
        parts.length === 4
        && parts[0] === 'auth'
        && parts[1] === 'verify-email'
        && parts[2] !== ''
        && parts[3] !== ''
    );
}

export function isPublicMarketingPath(path: string): boolean {
    const normalized = normalizePath(path);
    if (EXACT.has(normalized)) {
        return true;
    }
    return matchesLegal(normalized)
        || matchesDocumentation(normalized)
        || matchesVerifyEmail(normalized);
}

/** Full page load into the authenticated app bundle. */
export function navigateToAppPath(path: string): void {
    const normalized = `/${normalizePath(path)}`;
    window.location.assign(normalized);
}
