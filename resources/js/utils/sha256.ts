/** SHA-256 hex via WebCrypto. Returns null when the digest API is unavailable. */
export async function sha256Hex(text: string): Promise<string | null> {
    try {
        const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(text));
        return Array.from(new Uint8Array(digest))
            .map((byte) => byte.toString(16).padStart(2, "0"))
            .join("");
    } catch {
        return null;
    }
}
