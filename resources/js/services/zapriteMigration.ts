import api from "./api";
import { MIGRATION_MAX_FILE_BYTES } from "@/evolu/migrationArchiveValues";
import { parseZapriteMigrationExport } from "@/evolu/migrationZaprite";

const responseCodes = new Set([
    "zaprite_invalid_input", "zaprite_auth_failed", "zaprite_rate_limited",
    "zaprite_unavailable", "zaprite_response_invalid", "zaprite_limit_exceeded",
]);

/** Credentials exist only for the bounded read and never enter the source archive. */
export async function fetchZapriteMigrationExport(apiKey: string, options: {
    signal: AbortSignal;
    onProgress?: (page: number, pages: number) => void;
}): Promise<Uint8Array> {
    const pages: unknown[] = [];
    let lastPage = 1;
    let totalBytes = 0;
    try {
        for (let page = 1; page <= lastPage; page++) {
            if (options.signal.aborted) throw new Error("zaprite_cancelled");
            let body: unknown;
            try {
                const response = await api.post<{ data: unknown }>("/invoicing/ephemeral/migration/zaprite", {
                    api_key: apiKey, page,
                }, { signal: options.signal, timeout: 25_000, skipErrorFlash: true });
                // Do not retain an Axios request configuration containing the key.
                response.config.data = undefined;
                body = response.data.data;
            } catch (failure) {
                const error = failure as { config?: { data?: unknown }; response?: { data?: { code?: unknown } } };
                if (error.config) error.config.data = undefined;
                const code = error.response?.data?.code;
                throw new Error(options.signal.aborted ? "zaprite_cancelled"
                    : typeof code === "string" && responseCodes.has(code) ? code : "zaprite_unavailable");
            }
            if (!body || typeof body !== "object") throw new Error("zaprite_response_invalid");
            const meta = Reflect.get(body, "meta");
            const count = meta && typeof meta === "object" ? Reflect.get(meta, "pagesCount") : null;
            if (!Number.isInteger(count) || count < 0 || count > 20) throw new Error("zaprite_limit_exceeded");
            const terminal = Math.max(1, count);
            if (page > 1 && terminal !== lastPage) throw new Error("zaprite_incomplete_export");
            lastPage = terminal;
            totalBytes += new TextEncoder().encode(JSON.stringify(body)).byteLength;
            if (totalBytes > MIGRATION_MAX_FILE_BYTES) throw new Error("zaprite_limit_exceeded");
            pages.push(body);
            options.onProgress?.(page, lastPage);
        }
        if (options.signal.aborted) throw new Error("zaprite_cancelled");
        const bytes = new TextEncoder().encode(JSON.stringify({ version: 1, provider: "zaprite", pages }));
        if (bytes.byteLength > MIGRATION_MAX_FILE_BYTES) throw new Error("zaprite_limit_exceeded");
        // Structural and pagination proof is required before the UI can select this file.
        parseZapriteMigrationExport(bytes);
        return bytes;
    } finally {
        apiKey = "";
    }
}
