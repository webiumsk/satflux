import { enableAutoUnmount, flushPromises, shallowMount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

enableAutoUnmount(afterEach);
const mocks = vi.hoisted(() => ({
    continueAsGuest: vi.fn(), storeGuestMnemonic: vi.fn(), initEvolu: vi.fn(),
    error: vi.fn(), replace: vi.fn(), get: vi.fn(), fetchStores: vi.fn(),
}));
vi.mock("../services/api", () => ({ default: { get: mocks.get } }));
vi.mock("../store/auth", () => ({ useAuthStore: () => ({ continueAsGuest: mocks.continueAsGuest }) }));
vi.mock("../store/stores", () => ({ useStoresStore: () => ({ fetchStores: mocks.fetchStores }) }));
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
import Register from "../pages/auth/Register.vue";

const enrollment = { recoveryPublicKeyHex: "b".repeat(64), mnemonic: "synthetic recovery phrase fixture" };
const settings = (enabled = true, siteKey: string | null = "synthetic-site-key") => ({
    data: { turnstile: { enabled, site_key: siteKey, action: "register" } },
});
const mountPage = () => shallowMount(Register, { global: { stubs: { "router-link": true } } });
type Page = ReturnType<typeof mountPage>;

async function finishBackup(wrapper: Page) {
    wrapper.findComponent({ name: "GuestBackupWizardModal" }).vm.$emit("done", enrollment);
    await flushPromises();
}

beforeEach(() => {
    vi.clearAllMocks();
    mocks.get.mockResolvedValue(settings());
    mocks.continueAsGuest.mockResolvedValue({ store_id: "synthetic-local-store" });
    mocks.fetchStores.mockResolvedValue(undefined);
    mocks.initEvolu.mockResolvedValue(undefined);
});

describe("Turnstile registration flow", () => {
    it("waits until backup is unmounted and verification succeeds before creating an account", async () => {
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.findComponent({ name: "TurnstileChallenge" }).exists()).toBe(false);
        await finishBackup(wrapper);
        expect(wrapper.findComponent({ name: "GuestBackupWizardModal" }).exists()).toBe(false);
        const challenge = wrapper.findComponent({ name: "TurnstileChallenge" });
        expect(challenge.props()).toEqual({ siteKey: "synthetic-site-key" });
        expect(wrapper.html()).not.toContain(enrollment.mnemonic);
        expect(mocks.continueAsGuest).not.toHaveBeenCalled();
        challenge.vm.$emit("verified", "synthetic-valid-token");
        await flushPromises();
        expect(mocks.continueAsGuest).toHaveBeenCalledExactlyOnceWith(enrollment.recoveryPublicKeyHex, "synthetic-valid-token");
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledExactlyOnceWith(enrollment.mnemonic);
        expect(mocks.initEvolu).toHaveBeenCalledExactlyOnceWith(enrollment.mnemonic);
        expect(mocks.replace).toHaveBeenCalledWith("/stores/synthetic-local-store/wallet-connection");
    });

    it("preserves ordinary signup when verification is explicitly disabled", async () => {
        mocks.get.mockResolvedValue(settings(false, null));
        const wrapper = mountPage();
        await flushPromises();
        await finishBackup(wrapper);
        expect(mocks.continueAsGuest).toHaveBeenCalledExactlyOnceWith(enrollment.recoveryPublicKeyHex, undefined);
        expect(wrapper.findComponent({ name: "TurnstileChallenge" }).exists()).toBe(false);
    });

    it.each([
        settings(true, null), { data: {} },
        { data: { turnstile: { enabled: "false", action: "register" } } },
        { data: { turnstile: { enabled: true, site_key: "key", action: "other" } } },
    ])("does not assume disabled protection when public configuration is invalid", async configuration => {
        mocks.get.mockResolvedValue(configuration);
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.findComponent({ name: "AuthSeedGuestPanel" }).props("primaryDisabled")).toBe(true);
        expect(wrapper.text()).toContain("auth.turnstile_unavailable");
        await finishBackup(wrapper);
        expect(mocks.continueAsGuest).not.toHaveBeenCalled();
    });

    it("keeps existing-account restoration available when configuration cannot load", async () => {
        mocks.get.mockRejectedValue(new Error("Synthetic network failure"));
        const wrapper = mountPage();
        await flushPromises();
        const panel = wrapper.findComponent({ name: "AuthSeedGuestPanel" });
        expect(panel.props("primaryDisabled")).toBe(true);
        panel.vm.$emit("secondary");
        await flushPromises();
        const restore = wrapper.findComponent({ name: "GuestRestoreModal" });
        expect(restore.props("open")).toBe(true);
        restore.vm.$emit("success", { store_id: "synthetic-existing-store" });
        await flushPromises();
        expect(mocks.fetchStores).toHaveBeenCalledOnce();
        expect(mocks.replace).toHaveBeenCalledWith("/dashboard");
        expect(mocks.continueAsGuest).not.toHaveBeenCalled();
    });

    it("retains the confirmed phrase and obtains a fresh token after a failed submission", async () => {
        mocks.continueAsGuest.mockRejectedValueOnce({ response: { data: { code: "turnstile_failed" } } });
        const wrapper = mountPage();
        await flushPromises();
        await finishBackup(wrapper);
        wrapper.findComponent({ name: "TurnstileChallenge" }).vm.$emit("verified", "synthetic-first-token");
        await flushPromises();
        expect(mocks.continueAsGuest).toHaveBeenCalledTimes(1);
        expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
        expect(wrapper.findComponent({ name: "TurnstileChallenge" }).exists()).toBe(false);
        expect(wrapper.text()).toContain("auth.turnstile_error");
        await wrapper.findAll("button").find(button => button.text() === "auth.turnstile_retry")!.trigger("click");
        await flushPromises();
        expect(mocks.continueAsGuest).toHaveBeenCalledTimes(1);
        wrapper.findComponent({ name: "TurnstileChallenge" }).vm.$emit("verified", "synthetic-second-token");
        await flushPromises();
        expect(mocks.continueAsGuest).toHaveBeenNthCalledWith(2, enrollment.recoveryPublicKeyHex, "synthetic-second-token");
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledExactlyOnceWith(enrollment.mnemonic);
        expect(wrapper.html()).not.toContain(enrollment.mnemonic);
    });

    it("ignores duplicate verification callbacks while creation is in flight", async () => {
        let complete!: (value: { store_id: string }) => void;
        mocks.continueAsGuest.mockReturnValue(new Promise(resolve => { complete = resolve; }));
        const wrapper = mountPage();
        await flushPromises();
        await finishBackup(wrapper);
        const challenge = wrapper.findComponent({ name: "TurnstileChallenge" });
        challenge.vm.$emit("verified", "synthetic-one-token");
        challenge.vm.$emit("verified", "synthetic-duplicate-token");
        expect(mocks.continueAsGuest).toHaveBeenCalledTimes(1);
        complete({ store_id: "synthetic-local-store" });
        await flushPromises();
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledOnce();
    });

    it("does not update a browser owner after the registration page is unmounted", async () => {
        let complete!: (value: { store_id: string }) => void;
        mocks.continueAsGuest.mockReturnValue(new Promise(resolve => { complete = resolve; }));
        const wrapper = mountPage();
        await flushPromises();
        await finishBackup(wrapper);
        wrapper.findComponent({ name: "TurnstileChallenge" }).vm.$emit("verified", "synthetic-one-token");
        wrapper.unmount();
        complete({ store_id: "synthetic-local-store" });
        await flushPromises();
        expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
        expect(mocks.initEvolu).not.toHaveBeenCalled();
        expect(mocks.replace).not.toHaveBeenCalled();
    });

    it("allows cancellation without reusing an old enrollment", async () => {
        const wrapper = mountPage();
        await flushPromises();
        await finishBackup(wrapper);
        await wrapper.findAll("button").find(button => button.text() === "auth.turnstile_back")!.trigger("click");
        expect(wrapper.findComponent({ name: "TurnstileChallenge" }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: "AuthSeedGuestPanel" }).exists()).toBe(true);
        expect(mocks.continueAsGuest).not.toHaveBeenCalled();
        expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
    });

    it("recovers configuration failure without losing an already confirmed enrollment", async () => {
        mocks.continueAsGuest.mockRejectedValueOnce({ response: { data: { code: "turnstile_unavailable" } } });
        const wrapper = mountPage();
        await flushPromises();
        await finishBackup(wrapper);
        wrapper.findComponent({ name: "TurnstileChallenge" }).vm.$emit("verified", "synthetic-first-token");
        await flushPromises();
        mocks.get.mockRejectedValueOnce(new Error("Synthetic config outage"));
        await wrapper.findAll("button").find(button => button.text() === "auth.turnstile_retry")!.trigger("click");
        await flushPromises();
        expect(wrapper.text()).toContain("auth.turnstile_unavailable");
        await wrapper.findAll("button").find(button => button.text() === "auth.turnstile_retry")!.trigger("click");
        await flushPromises();
        wrapper.findComponent({ name: "TurnstileChallenge" }).vm.$emit("verified", "synthetic-second-token");
        await flushPromises();
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledExactlyOnceWith(enrollment.mnemonic);
    });
});
