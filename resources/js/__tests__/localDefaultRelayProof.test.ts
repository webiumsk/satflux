import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { createRandomBytes } from "@evolu/common";
import { createAppOwner, createOwnerSecret } from "@evolu/common/local-first";
import { clearStoredAccountMnemonic, storeAccountMnemonic } from "@/services/accountSeed";
import { confirmLocalDefaultRelayOwner, resolveLocalDefaultRelayOwner } from "@/evolu/localDefaultRelayProof";

const owner = createAppOwner(createOwnerSecret({ randomBytes: createRandomBytes() }));
const proofKey = "satflux.evolu.local_default_owner.v1";

beforeEach(() => { sessionStorage.clear(); localStorage.clear(); });
afterEach(() => { vi.restoreAllMocks(); sessionStorage.clear(); localStorage.clear(); });

it("stores public account provenance only and rejects a different account", () => {
    storeAccountMnemonic(owner.mnemonic!);
    expect(confirmLocalDefaultRelayOwner(7, owner.mnemonic!)).toBe(true);
    const raw = sessionStorage.getItem(proofKey)!;
    expect(Object.keys(JSON.parse(raw)).sort()).toEqual(["accountId", "ownerPublicKey"]);
    expect(raw.includes(owner.mnemonic!)).toBe(false);
    expect(resolveLocalDefaultRelayOwner(7)).not.toBeNull();
    expect(resolveLocalDefaultRelayOwner(8)).toBeNull();
    expect(sessionStorage.getItem(proofKey)).toBeNull();
});

it("requires a present valid current phrase and invalidates proof when it is cleared", () => {
    expect(confirmLocalDefaultRelayOwner(7, owner.mnemonic!)).toBe(false);
    storeAccountMnemonic(owner.mnemonic!);
    expect(confirmLocalDefaultRelayOwner(7, "synthetic invalid mnemonic")).toBe(false);
    expect(confirmLocalDefaultRelayOwner(7, owner.mnemonic!)).toBe(true);
    clearStoredAccountMnemonic();
    expect(resolveLocalDefaultRelayOwner(7)).toBeNull();
    expect(sessionStorage.getItem(proofKey)).toBeNull();
});

it.each(["{", "null", JSON.stringify({ accountId: 7, ownerPublicKey: "invalid" })])("rejects malformed public provenance (%s)", raw => {
    storeAccountMnemonic(owner.mnemonic!);
    sessionStorage.setItem(proofKey, raw);
    expect(resolveLocalDefaultRelayOwner(7)).toBeNull();
    expect(sessionStorage.getItem(proofKey)).toBeNull();
});

it("fails closed when browser storage cannot persist or read confirmation", () => {
    storeAccountMnemonic(owner.mnemonic!);
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => { throw new Error("synthetic storage unavailable"); });
    expect(confirmLocalDefaultRelayOwner(7, owner.mnemonic!)).toBe(false);
    vi.spyOn(Storage.prototype, "getItem").mockImplementation(() => { throw new Error("synthetic storage unavailable"); });
    expect(resolveLocalDefaultRelayOwner(7)).toBeNull();
});
