import { afterEach, beforeEach, describe, expect, it } from "vitest";
import {
    clearStoredAccountMnemonic,
    getStoredAccountMnemonic,
    hydrateAccountMnemonicSession,
    storeAccountMnemonic,
} from "../services/accountSeed";

const TEST_MNEMONIC =
    "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art";

describe("accountSeed persistence", () => {
    beforeEach(() => {
        sessionStorage.clear();
        localStorage.clear();
    });

    afterEach(() => {
        sessionStorage.clear();
        localStorage.clear();
    });

    it("persists mnemonic to localStorage when stored", () => {
        storeAccountMnemonic(TEST_MNEMONIC);
        expect(sessionStorage.getItem("satflux.account.mnemonic.v1")).toBeTruthy();
        expect(localStorage.getItem("satflux.account.mnemonic.persistent.v1")).toBeTruthy();
    });

    it("hydrates session mnemonic from localStorage after tab restart", () => {
        storeAccountMnemonic(TEST_MNEMONIC);
        sessionStorage.clear();

        hydrateAccountMnemonicSession();

        expect(getStoredAccountMnemonic()).toBe(
            "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art",
        );
    });

    it("clears persistent mnemonic on logout cleanup", () => {
        storeAccountMnemonic(TEST_MNEMONIC);
        clearStoredAccountMnemonic();
        expect(getStoredAccountMnemonic()).toBeNull();
        expect(localStorage.getItem("satflux.account.mnemonic.persistent.v1")).toBeNull();
    });
});
