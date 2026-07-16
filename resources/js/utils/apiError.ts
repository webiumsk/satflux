/**
 * Axios-style error shape for catch blocks. TypeScript only allows `any` or
 * `unknown` on catch parameters, so typed access to the response payload
 * needs a narrowing step - this is the single central one (the counterpart
 * of evolu/queryLoad.ts#toAppRows for API errors).
 */
export interface ApiErrorLike {
    message?: string;
    /** Axios: set when the request went out but no response came back. */
    request?: unknown;
    /** Axios v1 mirrors response.status here on the error itself. */
    status?: number;
    response?: {
        status?: number;
        data?: {
            message?: string;
            error?: string;
            errors?: Record<string, string[] | string | undefined>;
            [key: string]: unknown;
        };
    };
}

/** Usage: `catch (rawError) { const err = asApiError(rawError); ... }` */
export function asApiError(error: unknown): ApiErrorLike {
    return (error ?? {}) as ApiErrorLike;
}
