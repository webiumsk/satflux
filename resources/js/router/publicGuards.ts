import { useAuthStore } from '../store/auth';
import { isPublicMarketingPath, navigateToAppPath } from '../utils/publicMarketingRoutes';

/**
 * Shared navigation guard logic for public marketing SPA.
 */
export async function runPublicRouteGuard(
    to: { meta: Record<string, unknown>; name?: string | symbol | null; fullPath: string; path: string },
    next: (value?: unknown) => void,
): Promise<void> {
    if (!isPublicMarketingPath(to.path)) {
        navigateToAppPath(to.fullPath);
        return;
    }

    const authStore = useAuthStore();

    if (
        !authStore.user
        && to.name !== 'login'
        && to.name !== 'register'
        && to.name !== 'password-reset'
    ) {
        try {
            await authStore.fetchUser();
        } catch {
            // Unauthenticated visitors stay on public routes.
        }
    }

    if (to.meta.public) {
        next();
        return;
    }

    if (to.meta.requiresGuest && authStore.isAuthenticated) {
        const redirect = typeof to.query.redirect === 'string' && to.query.redirect.trim() !== ''
            ? to.query.redirect
            : '/dashboard';
        navigateToAppPath(redirect);
        return;
    }

    next();
}
