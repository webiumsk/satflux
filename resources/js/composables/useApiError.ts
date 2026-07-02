export function getApiErrorMessage(err: unknown, fallback = 'Request failed'): string {
    if (typeof err === 'object' && err !== null) {
        const response = (err as { response?: { data?: { message?: string } } }).response;
        const message = response?.data?.message;
        if (typeof message === 'string' && message.trim() !== '') {
            return message;
        }
    }

    if (err instanceof Error && err.message.trim() !== '') {
        return err.message;
    }

    return fallback;
}
