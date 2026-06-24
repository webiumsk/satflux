/** Seed-first auth: recovery phrase is the credential; email is contact only. */
export function isSeedFirstRegistration(): boolean {
    return import.meta.env.VITE_SEED_FIRST_REGISTRATION === "true";
}

/** Guest → Free upgrade collects email + verification only (no password). */
export function isGuestUpgradeEmailOnly(): boolean {
    const explicit = import.meta.env.VITE_GUEST_UPGRADE_EMAIL_ONLY;
    if (explicit === "true") return true;
    if (explicit === "false") return false;
    return isSeedFirstRegistration();
}
