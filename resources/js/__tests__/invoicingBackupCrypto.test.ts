import { describe, expect, it, vi } from "vitest";

// invoicingBackup transitively imports the real Evolu client through
// invoicingSnapshot - replace it with inert query tokens before importing.
vi.mock("@/evolu/client", () => {
    const tokens = [
        "allBankImportBatchesQuery",
        "allBankTransactionMatchesQuery",
        "allBankTransactionsQuery",
        "allCompaniesDetailQuery",
        "allCompanyStockBalancesQuery",
        "allCompanyStockItemsQuery",
        "allCompanyStockMovementsQuery",
        "allCompanyWarehousesQuery",
        "allContactsQuery",
        "allDocumentEventsQuery",
        "allDocumentLinesQuery",
        "allDocumentSnapshotsQuery",
        "allDocumentsQuery",
        "allExpensesQuery",
        "allExpenseAttachmentsQuery",
        "allNumberSeriesQuery",
        "allRecurringProfileLinesQuery",
        "allRecurringProfilesQuery",
    ];
    return Object.fromEntries(tokens.map((token) => [token, token]));
});

import { wordlist } from "@scure/bip39/wordlists/english.js";
import {
    BACKUP_PASSPHRASE_WORD_COUNT,
    ENCRYPTED_BACKUP_FORMAT,
    decryptBackupEnvelopeText,
    encryptBackupEnvelopeText,
    generateBackupPassphrase,
    isEncryptedBackupEnvelope,
} from "../evolu/invoicingBackupCrypto";
import { buildBackupEnvelopeFromSnapshot } from "../evolu/invoicingBackup";
import {
    classifyBackupText,
    decryptAndValidateBackup,
    parseAndValidateBackup,
} from "../evolu/invoicingBackupRestore";
import { EMPTY_INVOICING_SNAPSHOT } from "../evolu/invoicingSnapshot";

// Low iteration count keeps the PBKDF2 tests fast; production calibrates
// to the OWASP floor or above.
const TEST_ITERATIONS = 1_000;
const PASSPHRASE = "correct horse battery staple";

async function makePlaintextEnvelopeJson(): Promise<string> {
    const snapshot = {
        ...EMPTY_INVOICING_SNAPSHOT,
        contact: [{ id: "c1", name: "Alice" }],
    };
    const envelope = await buildBackupEnvelopeFromSnapshot(snapshot, "owner-id-1");
    return JSON.stringify(envelope);
}

describe("encrypted backup envelope (P2 phase 2)", () => {
    it("round-trips: encrypt -> decrypt -> full plaintext validation passes", async () => {
        const inner = await makePlaintextEnvelopeJson();
        const encrypted = await encryptBackupEnvelopeText(inner, PASSPHRASE, {
            iterations: TEST_ITERATIONS,
        });

        expect(encrypted.format).toBe(ENCRYPTED_BACKUP_FORMAT);
        expect(encrypted.kdf.iterations).toBe(TEST_ITERATIONS);
        // The ciphertext must not leak the payload.
        expect(JSON.stringify(encrypted)).not.toContain("Alice");

        const decrypted = await decryptBackupEnvelopeText(encrypted, PASSPHRASE);
        expect(decrypted).toBe(inner);

        const validated = await decryptAndValidateBackup(encrypted, PASSPHRASE, null);
        expect(validated.ok).toBe(true);
        if (validated.ok) {
            expect(validated.value.tableCounts.contact).toBe(1);
        }
    });

    it("rejects a wrong passphrase and a tampered ciphertext with decrypt_failed", async () => {
        const inner = await makePlaintextEnvelopeJson();
        const encrypted = await encryptBackupEnvelopeText(inner, PASSPHRASE, {
            iterations: TEST_ITERATIONS,
        });

        await expect(decryptBackupEnvelopeText(encrypted, "wrong passphrase")).rejects.toThrow(
            "backup_decrypt_failed",
        );

        const tampered = {
            ...encrypted,
            cipher: {
                ...encrypted.cipher,
                ciphertextB64: encrypted.cipher.ciphertextB64.slice(0, -4) + "AAAA",
            },
        };
        const result = await decryptAndValidateBackup(tampered, PASSPHRASE, null);
        expect(result).toEqual({ ok: false, error: "decrypt_failed" });
    });

    it("rejects a hostile iteration count before deriving any key", async () => {
        const inner = await makePlaintextEnvelopeJson();
        const encrypted = await encryptBackupEnvelopeText(inner, PASSPHRASE, {
            iterations: TEST_ITERATIONS,
        });

        for (const iterations of [2 ** 31, 0, -1, 1.5, Number.NaN]) {
            const hostile = { ...encrypted, kdf: { ...encrypted.kdf, iterations } };
            await expect(decryptBackupEnvelopeText(hostile, PASSPHRASE)).rejects.toThrow(
                "backup_decrypt_failed",
            );
        }
    });

    it("classifies encrypted, plaintext and invalid files", async () => {
        const inner = await makePlaintextEnvelopeJson();
        const encrypted = await encryptBackupEnvelopeText(inner, PASSPHRASE, {
            iterations: TEST_ITERATIONS,
        });

        const enc = await classifyBackupText(JSON.stringify(encrypted));
        expect(enc.kind).toBe("encrypted");

        expect((await classifyBackupText(inner)).kind).toBe("plaintext");
        expect((await classifyBackupText("not json")).kind).toBe("invalid");
        expect((await classifyBackupText('{"format":"something-else"}')).kind).toBe("invalid");

        // The invalid path still yields the precise plaintext error code.
        const invalid = await parseAndValidateBackup('{"format":"something-else"}', null);
        expect(invalid).toEqual({ ok: false, error: "invalid_format" });
    });

    it("isEncryptedBackupEnvelope requires format, kdf and cipher", async () => {
        const inner = await makePlaintextEnvelopeJson();
        const encrypted = await encryptBackupEnvelopeText(inner, PASSPHRASE, {
            iterations: TEST_ITERATIONS,
        });
        expect(isEncryptedBackupEnvelope(encrypted)).toBe(true);
        expect(isEncryptedBackupEnvelope(JSON.parse(inner))).toBe(false);
        expect(isEncryptedBackupEnvelope(null)).toBe(false);
        expect(isEncryptedBackupEnvelope({ format: ENCRYPTED_BACKUP_FORMAT })).toBe(false);
    });

    it("generates a 6-word passphrase from the BIP-39 wordlist", () => {
        const seen = new Set<string>();
        for (let i = 0; i < 10; i++) {
            const passphrase = generateBackupPassphrase();
            const words = passphrase.split(" ");
            expect(words).toHaveLength(BACKUP_PASSPHRASE_WORD_COUNT);
            for (const word of words) {
                expect(wordlist).toContain(word);
            }
            seen.add(passphrase);
        }
        // 10 draws from a 66-bit space colliding would mean a broken RNG.
        expect(seen.size).toBeGreaterThan(1);
    });
});
