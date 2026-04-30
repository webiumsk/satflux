import { generateMnemonic, mnemonicToSeedSync, validateMnemonic } from "@scure/bip39";
import { wordlist } from "@scure/bip39/wordlists/english.js";
import { hkdf } from "@noble/hashes/hkdf.js";
import { sha512 } from "@noble/hashes/sha2.js";
import * as ed25519 from "@noble/ed25519";

ed25519.hashes.sha512 = sha512;

const HKDF_INFO = new TextEncoder().encode("satflux-guest-ed25519-v1");

function bytesToHex(u8: Uint8Array): string {
    return Array.from(u8, (b) => b.toString(16).padStart(2, "0")).join("");
}

function normalizeMnemonic(mnemonic: string): string {
    return mnemonic
        .trim()
        .toLowerCase()
        .split(/\s+/)
        .join(" ");
}

export function generateGuestMnemonic24(): string {
    return generateMnemonic(wordlist, 256);
}

export function guestRecoveryPublicKeyHexFromMnemonic(mnemonic: string): string {
    const normalized = normalizeMnemonic(mnemonic);
    if (!validateMnemonic(normalized, wordlist)) {
        throw new Error("invalid_mnemonic");
    }
    const seed = mnemonicToSeedSync(normalized, "");
    const sk = hkdf(sha512, seed, new Uint8Array(0), HKDF_INFO, 32);
    const pk = ed25519.getPublicKey(sk);
    return bytesToHex(pk);
}

export function signGuestRecoveryMessage(mnemonic: string, messageUtf8: string): string {
    const normalized = normalizeMnemonic(mnemonic);
    if (!validateMnemonic(normalized, wordlist)) {
        throw new Error("invalid_mnemonic");
    }
    const seed = mnemonicToSeedSync(normalized, "");
    const sk = hkdf(sha512, seed, new Uint8Array(0), HKDF_INFO, 32);
    const sig = ed25519.sign(new TextEncoder().encode(messageUtf8), sk);
    return bytesToHex(sig);
}

export function guestRecoveryMessage(challengeId: string, nonceHex: string): string {
    return `satflux:guest-recovery:v1|${challengeId}|${nonceHex}`;
}
