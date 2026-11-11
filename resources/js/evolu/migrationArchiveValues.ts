export const MIGRATION_MAX_FILE_BYTES = 10 * 1024 * 1024;
export const MIGRATION_MAX_TOTAL_BYTES = 20 * 1024 * 1024;
export const MIGRATION_MAX_ORIGINALS = 50;
export const MIGRATION_CHUNK_BYTES = 256 * 1024;

export async function migrationSha256(bytes: Uint8Array): Promise<string> {
    const digest = await crypto.subtle.digest("SHA-256", new Uint8Array(bytes).buffer);
    return Array.from(new Uint8Array(digest), value => value.toString(16).padStart(2, "0")).join("");
}
