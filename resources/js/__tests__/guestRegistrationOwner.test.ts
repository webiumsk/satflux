import { enableAutoUnmount, flushPromises, shallowMount } from "@vue/test-utils";
import { afterEach, beforeEach, expect, it, vi } from "vitest";

enableAutoUnmount(afterEach);
const mocks = vi.hoisted(() => ({
    continueAsGuest: vi.fn(), storeGuestMnemonic: vi.fn(), initEvolu: vi.fn(),
    error: vi.fn(), replace: vi.fn(),
}));
vi.mock("../store/auth", () => ({ useAuthStore: () => ({ continueAsGuest: mocks.continueAsGuest }) }));
vi.mock("../store/stores", () => ({ useStoresStore: () => ({ fetchStores: vi.fn() }) }));
vi.mock("../store/flash", () => ({ useFlashStore: () => ({ error: mocks.error }) }));
vi.mock("vue-router", () => ({ useRouter: () => ({ replace: mocks.replace }) }));
vi.mock("vue-i18n", async (importOriginal) => ({
    ...await importOriginal<typeof import("vue-i18n")>(),
    useI18n: () => ({ t: (key: string) => key }),
}));
vi.mock("../services/guestRecovery", () => ({ storeGuestMnemonic: mocks.storeGuestMnemonic }));
vi.mock("../services/accountSeed", () => ({
    initEvoluFromAccountSeedIfNeeded: mocks.initEvolu, isEvoluUnavailableError: () => false,
}));
vi.mock("../services/deviceUnlock/passkeyPrf", () => ({ isPasskeyPrfSupported: vi.fn(async () => false) }));

beforeEach(() => { vi.clearAllMocks(); });

it("does not overwrite the session phrase or Evolu owner when enrollment is rejected by an existing session", async () => {
    mocks.continueAsGuest.mockRejectedValue({ response: { data: { message: "Already signed in to another account." } } });
    const { default: Register } = await import("../pages/auth/Register.vue");
    const wrapper = shallowMount(Register, { global: { stubs: { "router-link": true } } });

    wrapper.findComponent({ name: "GuestBackupWizardModal" }).vm.$emit("done", {
        recoveryPublicKeyHex: "b".repeat(64), mnemonic: "new account phrase",
    });
    await flushPromises();

    expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
    expect(mocks.initEvolu).not.toHaveBeenCalled();
    expect(mocks.replace).not.toHaveBeenCalled();
    expect(mocks.error).toHaveBeenCalledWith("Already signed in to another account.");
});
