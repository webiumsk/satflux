import axios from 'axios';

let csrfReadyPromise: Promise<boolean> | null = null;

/** Fetch Sanctum CSRF cookie once per page load. */
export function ensureCsrfCookie(): Promise<boolean> {
    if (!csrfReadyPromise) {
        csrfReadyPromise = axios
            .get('/sanctum/csrf-cookie', { withCredentials: true })
            .then(() => true)
            .catch(() => false);
    }

    return csrfReadyPromise;
}

/** @deprecated Use ensureCsrfCookie() */
export function csrfReady(): Promise<boolean> {
    return ensureCsrfCookie();
}
