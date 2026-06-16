import { validateMnemonic } from "@scure/bip39";
import { wordlist } from "@scure/bip39/wordlists/english.js";
import { hkdf } from "@noble/hashes/hkdf.js";
import { sha512 } from "@noble/hashes/sha2.js";
import { mnemonicToSeedSync } from "@scure/bip39";
import {
    ownerSecretToMnemonic,
    type Mnemonic,
    type OwnerSecret,
} from "@evolu/common/local-first";

const EVOLU_OWNER_HKDF_INFO = new TextEncoder().encode("satflux-evolu-owner-v1");

function normalizeMnemonicWords(mnemonic: string): string {
    return mnemonic
        .trim()
        .toLowerCase()
        .split(/\s+/)
        .join(" ");
}

/** Evolu AppOwner = same 24-word Satflux recovery phrase. */
export function deriveEvoluOwnerMnemonic(mnemonic: string): Mnemonic {
    const normalized = normalizeMnemonicWords(mnemonic);
    if (!validateMnemonic(normalized, wordlist)) {
        throw new Error("invalid_mnemonic");
    }
    return normalized as Mnemonic;
}

/** Pre-unification HKDF owner (migrated automatically when local data exists). */
export function deriveLegacyHkdfEvoluOwnerMnemonic(mnemonic: string): Mnemonic {
    const normalized = normalizeMnemonicWords(mnemonic);
    if (!validateMnemonic(normalized, wordlist)) {
        throw new Error("invalid_mnemonic");
    }
    const seed = mnemonicToSeedSync(normalized, "");
    const secretBytes = hkdf(sha512, seed, new Uint8Array(0), EVOLU_OWNER_HKDF_INFO, 32);
    return ownerSecretToMnemonic(secretBytes as OwnerSecret);
}

export function isTargetEvoluOwner(
    ownerMnemonic: string | null | undefined,
    accountMnemonic: string,
): boolean {
    if (!ownerMnemonic) return false;
    const target = deriveEvoluOwnerMnemonic(accountMnemonic);
    return ownerMnemonic === target;
}
