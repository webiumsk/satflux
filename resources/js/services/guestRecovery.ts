import { mnemonicToSeedSync, validateMnemonic } from "@scure/bip39";
import { wordlist } from "@scure/bip39/wordlists/english.js";
import {
    clearStoredAccountMnemonic,
    deriveRecoveryPublicKeyHex,
    generateAccountMnemonic24,
    getStoredAccountMnemonic,
    normalizeAccountMnemonic,
    signRecoveryMessage,
    storeAccountMnemonic,
} from "./accountSeed";

export {
    clearStoredAccountMnemonic as clearStoredGuestMnemonic,
    getStoredAccountMnemonic as getStoredGuestMnemonic,
    storeAccountMnemonic as storeGuestMnemonic,
};

export function generateGuestMnemonic24(): string {
    return generateAccountMnemonic24();
}

export function guestRecoveryPublicKeyHexFromMnemonic(mnemonic: string): string {
    return deriveRecoveryPublicKeyHex(mnemonic);
}

export function signGuestRecoveryMessage(mnemonic: string, messageUtf8: string): string {
    return signRecoveryMessage(mnemonic, messageUtf8);
}

export function guestRecoveryMessage(challengeId: string, nonceHex: string): string {
    return `satflux:guest-recovery:v1|${challengeId}|${nonceHex}`;
}

// Legacy exports kept for any direct imports of normalize/validate helpers.
export function normalizeMnemonic(mnemonic: string): string {
    return normalizeAccountMnemonic(mnemonic);
}

export function validateGuestMnemonic(mnemonic: string): boolean {
    return validateMnemonic(normalizeAccountMnemonic(mnemonic), wordlist);
}

export function mnemonicToSeed(mnemonic: string): Uint8Array {
    return mnemonicToSeedSync(normalizeAccountMnemonic(mnemonic), "");
}
