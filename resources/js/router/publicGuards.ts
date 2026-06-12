import { useAuthStore } from '../store/auth';
import { isPublicMarketingPath, navigateToAppPath } from '../utils/publicMarketingRoutes';

/**
 * Shared navigation guard logic for public marketing SPA.
 * Does not block render on auth API calls.
 */
export function runPublicRouteGuard(
    to: { meta: Record<string, unknown>; name?: string | symbol | null; fullPath: string; path: string },
    next: (value?: unknown) => void,
): void {
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
        void authStore.fetchUser();
    }

    if (to.meta.public) {
        next();
        return;
    }

    if (to.meta.requiresGuest && authStore.isAuthenticated) {
        navigateToAppPath('/dashboard');
        return;
    }

    next();
}
