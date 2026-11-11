import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import type { Component } from "vue";
import type { User } from "../store/auth";
import { clearRelayOverride, readRelayOverride, writeRelayOverrideDisabled } from "../evolu/relayOverrideStorage";
import { getStoredAccountMnemonic } from "../services/accountSeed";
import { loadInvoicingLocalStats, type InvoicingLocalStats } from "../evolu/invoicingLocalStats";
import { fetchEvoluRelayUsage } from "../services/evoluRelayUsageApi";
import { evolu } from "../evolu/client";

const mocks = vi.hoisted(() => ({
    route: { query: { restore_phrase: "1" } as Record<string, string | undefined> },
    routerReplace: vi.fn(),
    flashSuccess: vi.fn(),
    flashWarning: vi.fn(),
    flashError: vi.fn(),
    restoreWithAccountPasskey: vi.fn(),
    unlockDeviceWithPassphrase: vi.fn(),
    unlockDeviceWithPasskey: vi.fn(),
    bindRecoveryPhraseOnThisDevice: vi.fn(),
    clearSessionAccountMnemonic: vi.fn(),
    previewOwnerSwitchImpact: vi.fn(),
    resetEvoluBootstrapForRetry: vi.fn(),
    storeAccountMnemonic: vi.fn(),
    isDeviceRemembered: vi.fn(),
    listDevicePasskeySlots: vi.fn(),
    listAccountEnvelopes: vi.fn(),
    reloadApp: vi.fn(),
    apiGet: vi.fn(),
    apiPost: vi.fn(),
    apiPut: vi.fn(),
    fetchUser: vi.fn(),
    enrollGuestRecoveryPublicKey: vi.fn(),
    storeGuestMnemonic: vi.fn(),
    initEvoluFromAccountSeedIfNeeded: vi.fn(),
    reconcileEvoluRelayPreferenceFromProfile: vi.fn(),
    resumeEvoluRelaySyncOnDevice: vi.fn(),
    captureLocalDefaultRelayOwnerConfirmation: vi.fn(),
    confirmLocalDefaultRelayOwner: vi.fn(),
    writeRelayOverrideUrl: vi.fn(),
    probeRelayReachability: vi.fn(async () => true),
    activeRelayUrl: "",
    recordedRelayError: "",
    user: null as User | null,
    invoicingCompaniesList: vi.fn(),
}));

vi.mock("vue-router", () => ({
    useRoute: () => mocks.route,
    useRouter: () => ({
        replace: mocks.routerReplace,
        push: vi.fn(),
    }),
}));

vi.mock("../store/auth", async () => {
    const { shallowRef } = await import("vue");
    const user = shallowRef(mocks.user);
    Object.defineProperty(mocks, "user", {
        get: () => user.value,
        set: (value: User | null) => { user.value = value; },
        configurable: true,
    });
    return {
        useAuthStore: () => ({
            isAuthenticated: true,
            get user() { return user.value; },
            fetchUser: mocks.fetchUser,
            reconcileEvoluRelayPreferenceFromProfile: mocks.reconcileEvoluRelayPreferenceFromProfile,
            resumeEvoluRelaySyncOnDevice: mocks.resumeEvoluRelaySyncOnDevice,
            captureLocalDefaultRelayOwnerConfirmation: mocks.captureLocalDefaultRelayOwnerConfirmation,
            enrollGuestRecoveryPublicKey: mocks.enrollGuestRecoveryPublicKey,
        }),
    };
});

vi.mock("../store/flash", () => ({
    useFlashStore: () => ({
        success: mocks.flashSuccess,
        warning: mocks.flashWarning,
        error: mocks.flashError,
    }),
}));

vi.mock("../composables/usePricing", async () => {
    const { computed } = await import("vue");
    return {
        proEffectiveMonthlySats: (pro: { sats_per_year: number }) => Math.round(pro.sats_per_year / 12),
        proHasMonthlyDiscount: () => false,
        usePricing: () => ({
            pricing: computed(() => ({
                trial_days: 30,
                grace_days: 30,
                free: { sats_per_year: 0 },
                pro: { sats_per_year: 210000, sats_per_month_display: 21000 },
            })),
            formatSats: (amount: number) => `${amount} sats`,
            load: vi.fn(async () => undefined),
        }),
    };
});

vi.mock("../composables/usePlanFeatures", async () => {
    const { computed } = await import("vue");
    return {
        usePlanFeatures: () => ({
            planFeatures: computed(() => ({
                invoicing_highlight_keys: [],
                free: { feature_keys: [] },
                pro: { feature_keys: [] },
                enterprise: { feature_keys: [] },
            })),
            isInvoicingFeature: () => false,
            load: vi.fn(async () => undefined),
        }),
    };
});

vi.mock("../composables/useCurrentPlan", async () => {
    const { computed } = await import("vue");
    return {
        useCurrentPlan: () => ({
            planCode: computed(() => "free"),
            hasProOrHigher: computed(() => false),
            canUpgradeToPro: computed(() => true),
        }),
    };
});

vi.mock("../services/api", () => ({
    default: {
        get: mocks.apiGet,
        post: mocks.apiPost,
        put: mocks.apiPut,
    },
    invoicingApi: {
        companies: {
            list: mocks.invoicingCompaniesList,
        },
    },
}));

vi.mock("../services/guestRecovery", () => ({
    getStoredGuestMnemonic: vi.fn(() => null),
    storeGuestMnemonic: mocks.storeGuestMnemonic,
}));

vi.mock("../services/accountSeed", () => ({
    initEvoluFromAccountSeedIfNeeded: mocks.initEvoluFromAccountSeedIfNeeded,
    bindRecoveryPhraseOnThisDevice: mocks.bindRecoveryPhraseOnThisDevice,
    clearSessionAccountMnemonic: mocks.clearSessionAccountMnemonic,
    deriveRecoveryPublicKeyHex: vi.fn((phrase: string) => `pk:${phrase}`),
    getStoredAccountMnemonic: vi.fn(() => null),
    previewOwnerSwitchImpact: mocks.previewOwnerSwitchImpact,
    storeAccountMnemonic: mocks.storeAccountMnemonic,
}));

vi.mock("../services/deviceUnlock/provider", () => ({
    addAccountPasskeyFromSession: vi.fn(),
    addPasskeyToRememberedDevice: vi.fn(),
    changeDevicePassphrase: vi.fn(),
    forgetDevice: vi.fn(),
    isDeviceRemembered: mocks.isDeviceRemembered,
    listDevicePasskeySlots: mocks.listDevicePasskeySlots,
    passkeyPrfUnlockProvider: { isSupported: vi.fn(async () => true) },
    removeDevicePasskeySlot: vi.fn(),
    rememberDeviceWithPassphrase: vi.fn(),
    restoreWithAccountPasskey: mocks.restoreWithAccountPasskey,
    unlockDeviceWithPasskey: mocks.unlockDeviceWithPasskey,
    unlockDeviceWithPassphrase: mocks.unlockDeviceWithPassphrase,
    upgradeAccountPasskey: vi.fn(),
}));

vi.mock("../services/deviceUnlock/accountPasskeyEnvelope", () => ({
    credentialIdToB64Url: vi.fn((value: string) => value),
    deleteAccountEnvelope: vi.fn(),
    listAccountEnvelopes: mocks.listAccountEnvelopes,
}));

vi.mock("../services/deviceUnlock/passkeyPrf", () => ({
    PasskeyCancelledError: class PasskeyCancelledError extends Error {},
    PasskeyPrfUnsupportedError: class PasskeyPrfUnsupportedError extends Error {},
    PasskeyUnsupportedError: class PasskeyUnsupportedError extends Error {},
}));

vi.mock("../services/deviceUnlock/envelope", () => ({
    isAcceptableDevicePassphrase: vi.fn(() => true),
}));

vi.mock("../evolu/flags", () => ({
    isInvoicingLocalFirst: () => true,
}));

vi.mock("../evolu/bootstrap", () => ({
    ensureEvoluBoundToAccountSeed: vi.fn(async () => undefined),
    resetEvoluBootstrapForRetry: mocks.resetEvoluBootstrapForRetry,
}));

vi.mock("../evolu/reloadGuard", () => ({
    allowEvoluPageReload: vi.fn(),
}));

vi.mock("../evolu/client", () => ({
    evolu: {
        loadQuery: vi.fn(async () => []),
        reloadApp: mocks.reloadApp,
        exportDatabase: vi.fn(async () => new Uint8Array(123)),
    },
}));

vi.mock("../evolu/config", async (importOriginal) => ({
    ...await importOriginal<typeof import("../evolu/config")>(),
    getEvoluRelayBuildInfo: () => ({ enabled: false, url: "" }),
}));

vi.mock("../services/evoluRelayPreference", () => ({
    getResolvedEvoluRelayUrls: () => [],
    getEvoluRelayRuntimeInfo: () => ({
        url: mocks.activeRelayUrl,
        defaultUrl: "",
        profileUrl: "",
        override: { kind: "none" },
    }),
}));

vi.mock("../evolu/evoluRelaySubscription", () => ({
    refreshEvoluRelaySubscription: vi.fn(async () => undefined),
}));

vi.mock("../evolu/invoicingLocalStats", () => ({
    formatByteSize: (value: number) => `${value} B`,
    loadInvoicingLocalStats: vi.fn(async () => ({
        ownerId: null,
        companies: 0,
        contacts: 0,
        documents: 0,
        dbBytes: 0,
        counts: { companies: 0, contacts: 0, documents: 0, expenses: 0, documentByType: {} },
        relayError: mocks.recordedRelayError,
    })),
}));

vi.mock("../services/evoluRelayUsageApi", () => ({
    fetchEvoluRelayUsage: vi.fn(async () => null),
    probeRelayReachability: mocks.probeRelayReachability,
    relayUsagePercent: vi.fn(() => 0),
}));

vi.mock("../evolu/relayOverrideStorage", async (importOriginal) => ({
    ...await importOriginal<typeof import("../evolu/relayOverrideStorage")>(),
    clearRelayOverride: vi.fn(),
    readRelayOverride: vi.fn(() => ({ kind: "none" })),
    writeRelayOverrideDisabled: vi.fn(),
    writeRelayOverrideUrl: mocks.writeRelayOverrideUrl,
}));

vi.mock("../utils/dashboardInvoicingTab", () => ({
    dashboardInvoicingTabPath: () => "/dashboard/invoicing",
}));

/** Find a translated Profile action and fail clearly when its control is absent. */
function findButton(wrapper: VueWrapper, text: string) {
    const button = wrapper.findAll("button").find((candidate) => candidate.text().includes(text));
    if (!button) {
        throw new Error(`button not found: ${text}`);
    }
    return button;
}

/** Select the last matching action when multiple Profile sections share its label. */
function findLastButton(wrapper: VueWrapper, text: string) {
    const buttons = wrapper.findAll("button").filter((candidate) => candidate.text().includes(text));
    const button = buttons[buttons.length - 1];
    if (!button) {
        throw new Error(`button not found: ${text}`);
    }
    return button;
}

// Profile.vue is a large SFC; compile it once up front (outside the timed
// tests) so the per-test budget covers only mount + interaction, not the
// one-off transform that otherwise makes the first test flaky under load.
let Profile: Component;
beforeAll(async () => {
    Profile = (await import("../pages/account/Profile.vue")).default;
}, 30000);

/** Mount the real Profile component with synthetic account services and settle its initial requests. */
async function mountProfile(): Promise<VueWrapper> {
    const wrapper = mount(Profile, {
        global: {
            stubs: {
                RouterLink: { template: "<a><slot /></a>" },
                GuestBackupWizardModal: true,
                GuestUpgradeForm: true,
                InvoicingBackupCard: true,
                LocalStorageCard: true,
                EvoluRelayManager: true,
            },
        },
    });
    await flushPromises();
    await flushPromises();
    return wrapper;
}

describe("Profile recovery phrase owner switch guard", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mocks.enrollGuestRecoveryPublicKey.mockResolvedValue({ user: { id: 1 } });
        mocks.initEvoluFromAccountSeedIfNeeded.mockResolvedValue("already_synced");
        mocks.fetchUser.mockResolvedValue(true);
        mocks.resumeEvoluRelaySyncOnDevice.mockResolvedValue(true);
        vi.mocked(readRelayOverride).mockReturnValue({ kind: "none" });
        mocks.captureLocalDefaultRelayOwnerConfirmation.mockReturnValue(mocks.confirmLocalDefaultRelayOwner);
        mocks.confirmLocalDefaultRelayOwner.mockReturnValue(false);
        mocks.activeRelayUrl = "";
        mocks.recordedRelayError = "";
        mocks.user = {
            id: 1, name: 'Synthetic Merchant', email: 'synthetic@example.test', role: 'free', is_guest: false,
            email_verified_at: '2026-01-01', guest_recovery_enrolled: true, can_use_password_login: true, evolu_relay_url: null,
            purchase_availability: { pro_subscriptions: false, pro_credits: false, company_slots: false, isdoc_packs: false },
        };
        mocks.route.query = { restore_phrase: "1" };
        mocks.restoreWithAccountPasskey.mockResolvedValue({ recoveryPhrase: "passkey phrase" });
        mocks.unlockDeviceWithPassphrase.mockResolvedValue({ recoveryPhrase: "device phrase" });
        mocks.unlockDeviceWithPasskey.mockResolvedValue({ recoveryPhrase: "device passkey phrase" });
        mocks.bindRecoveryPhraseOnThisDevice.mockResolvedValue("already_synced");
        mocks.previewOwnerSwitchImpact.mockResolvedValue({
            switches: true,
            companies: 1,
            contacts: 2,
            documents: 3,
        });
        mocks.isDeviceRemembered.mockResolvedValue(false);
        mocks.listDevicePasskeySlots.mockResolvedValue([]);
        mocks.listAccountEnvelopes.mockResolvedValue([
            {
                credential_id: "credential-1",
                label: "Main passkey",
                created_at: "2026-07-20T00:00:00Z",
                last_used_at: null,
            },
        ]);
        mocks.apiGet.mockResolvedValue({
            data: {
                subscriber: null,
                billing: null,
                creditBalance: 0,
                creditHistory: [],
            },
        });
        mocks.invoicingCompaniesList.mockResolvedValue([]);
    });

    it("uses live guarded row errors for the current sync badge while retaining historical SDK errors", async () => {
        vi.mocked(getStoredAccountMnemonic).mockReturnValue("synthetic-invalid-history-fixture");
        mocks.recordedRelayError = "ProtocolQuotaError";
        mocks.route.query = { tab: "sync" };
        const wrapper = await mountProfile();
        const tab = findButton(wrapper, "account.tab_sync");
        expect(wrapper.text()).toContain("account.evolu_stats_relay_error");
        expect(tab.find('[title="account.tab_sync_badge_hint"]').exists()).toBe(false);
        const manager = wrapper.getComponent({ name: "EvoluRelayManager" });
        manager.vm.$emit("sync-error", true); await flushPromises();
        expect(tab.find('[title="account.tab_sync_badge_hint"]').exists()).toBe(true);
        manager.vm.$emit("sync-error", false); await flushPromises();
        expect(tab.find('[title="account.tab_sync_badge_hint"]').exists()).toBe(false);
        expect(wrapper.text()).toContain("account.evolu_stats_relay_error");
        wrapper.unmount(); vi.mocked(getStoredAccountMnemonic).mockReturnValue(null);
    });

    it("keeps Profile enrollment bound to the wizard-opening account after canonical background refresh", async () => {
        mocks.route.query = {};
        mocks.user = { ...mocks.user!, guest_recovery_enrolled: false };
        const mismatch = { response: { status: 409, data: { code: "account_changed", message: "The signed-in account changed." } } };
        mocks.enrollGuestRecoveryPublicKey.mockRejectedValueOnce(mismatch);
        const wrapper = await mountProfile();
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        const wizard = wrapper.getComponent({ name: "GuestBackupWizardModal" });
        expect(wizard.props("open")).toBe(true);
        mocks.user = { ...mocks.user!, id: 2 };
        wizard.vm.$emit("done", { recoveryPublicKeyHex: "a".repeat(64), mnemonic: "synthetic account-one phrase" });
        await flushPromises();
        expect(mocks.enrollGuestRecoveryPublicKey).toHaveBeenCalledWith("a".repeat(64), 1);
        expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
        expect(mocks.initEvoluFromAccountSeedIfNeeded).not.toHaveBeenCalled();
        expect(mocks.flashError).toHaveBeenCalledWith("The signed-in account changed.");
        wrapper.unmount();
    });

    it("rejects stale Profile enrollment after the account changes away and back", async () => {
        mocks.route.query = {};
        mocks.user = { ...mocks.user!, guest_recovery_enrolled: false };
        const wrapper = await mountProfile();
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        const original = mocks.user!;
        mocks.user = { ...original, id: 2 };
        mocks.user = original;
        wrapper.getComponent({ name: "GuestBackupWizardModal" }).vm.$emit("done", {
            recoveryPublicKeyHex: "a".repeat(64), mnemonic: "synthetic stale phrase",
        });
        await flushPromises();
        expect(mocks.enrollGuestRecoveryPublicKey).not.toHaveBeenCalled();
        expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
        expect(mocks.flashError).toHaveBeenCalledWith("account.recovery_phrase_save_failed");
        wrapper.unmount();
    });

    it("recaptures the intended account when the Profile backup wizard is closed and reopened", async () => {
        mocks.route.query = {};
        mocks.user = { ...mocks.user!, guest_recovery_enrolled: false };
        const wrapper = await mountProfile();
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        const wizard = wrapper.getComponent({ name: "GuestBackupWizardModal" });
        wizard.vm.$emit("close");
        mocks.user = { ...mocks.user!, id: 2 };
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        wizard.vm.$emit("done", { recoveryPublicKeyHex: "b".repeat(64), mnemonic: "synthetic account-two phrase" });
        wizard.vm.$emit("close");
        await flushPromises();
        expect(mocks.enrollGuestRecoveryPublicKey).toHaveBeenCalledWith("b".repeat(64), 2);
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledWith("synthetic account-two phrase");
        expect(mocks.flashSuccess).toHaveBeenCalledWith("account.recovery_phrase_saved");
        wrapper.unmount();
    });

    it("does not let a previous pending enrollment bind its phrase or close a newly opened wizard", async () => {
        mocks.route.query = {};
        mocks.user = { ...mocks.user!, guest_recovery_enrolled: false };
        let finish!: (value: unknown) => void;
        mocks.enrollGuestRecoveryPublicKey.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
        const wrapper = await mountProfile();
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        const wizard = wrapper.getComponent({ name: "GuestBackupWizardModal" });
        wizard.vm.$emit("done", { recoveryPublicKeyHex: "a".repeat(64), mnemonic: "synthetic old phrase" });
        await flushPromises();
        mocks.user = { ...mocks.user!, id: 2 };
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        finish({ user: { id: 1 } });
        await flushPromises();
        expect(wizard.props("open")).toBe(true);
        expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
        expect(mocks.initEvoluFromAccountSeedIfNeeded).not.toHaveBeenCalled();
        expect(mocks.flashSuccess).not.toHaveBeenCalled();
        wizard.vm.$emit("done", { recoveryPublicKeyHex: "b".repeat(64), mnemonic: "synthetic new phrase" });
        await flushPromises();
        expect(mocks.enrollGuestRecoveryPublicKey).toHaveBeenLastCalledWith("b".repeat(64), 2);
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledTimes(1);
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledWith("synthetic new phrase");
        wrapper.unmount();
    });

    it("ignores backup completion without an opening account context rather than enrolling the current account", async () => {
        mocks.route.query = {};
        const wrapper = await mountProfile();
        wrapper.getComponent({ name: "GuestBackupWizardModal" }).vm.$emit("done", {
            recoveryPublicKeyHex: "a".repeat(64), mnemonic: "synthetic unbound phrase",
        });
        await flushPromises();
        expect(mocks.enrollGuestRecoveryPublicKey).not.toHaveBeenCalled();
        expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it("ignores retained done and close callbacks from an earlier Profile wizard opening", async () => {
        mocks.route.query = {};
        mocks.user = { ...mocks.user!, guest_recovery_enrolled: false };
        const wrapper = await mountProfile();
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        const wizard = wrapper.getComponent({ name: "GuestBackupWizardModal" });
        const oldDone = wizard.vm.$.vnode.props?.onDone as (payload: { recoveryPublicKeyHex: string; mnemonic: string }) => Promise<void>;
        const oldClose = wizard.vm.$.vnode.props?.onClose as () => void;
        oldClose();
        mocks.user = { ...mocks.user!, id: 2 };
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        await oldDone({ recoveryPublicKeyHex: "a".repeat(64), mnemonic: "synthetic discarded phrase" });
        oldClose();
        await flushPromises();
        expect(wizard.props("open")).toBe(true);
        expect(mocks.enrollGuestRecoveryPublicKey).not.toHaveBeenCalled();
        expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it("consumes a Profile opening once when its completion callback is submitted twice", async () => {
        mocks.route.query = {};
        mocks.user = { ...mocks.user!, guest_recovery_enrolled: false };
        let finish!: (value: unknown) => void;
        mocks.enrollGuestRecoveryPublicKey.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
        const wrapper = await mountProfile();
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        const wizard = wrapper.getComponent({ name: "GuestBackupWizardModal" });
        const done = wizard.vm.$.vnode.props?.onDone as (payload: { recoveryPublicKeyHex: string; mnemonic: string }) => Promise<void>;
        const payload = { recoveryPublicKeyHex: "a".repeat(64), mnemonic: "synthetic confirmed phrase" };
        const first = done(payload);
        await done(payload);
        expect(mocks.enrollGuestRecoveryPublicKey).toHaveBeenCalledTimes(1);
        finish({ user: { id: 1 } });
        await first;
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });

    it("suppresses stale Profile success when the account changes while Evolu initialization waits", async () => {
        mocks.route.query = {};
        mocks.user = { ...mocks.user!, guest_recovery_enrolled: false };
        let finishInit!: (value: string) => void;
        mocks.initEvoluFromAccountSeedIfNeeded.mockImplementationOnce(() => new Promise(resolve => { finishInit = resolve; }));
        const wrapper = await mountProfile();
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        wrapper.getComponent({ name: "GuestBackupWizardModal" }).vm.$emit("done", {
            recoveryPublicKeyHex: "a".repeat(64), mnemonic: "synthetic confirmed phrase",
        });
        await flushPromises();
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledTimes(1);
        expect(mocks.initEvoluFromAccountSeedIfNeeded).toHaveBeenCalledWith("synthetic confirmed phrase", expect.any(Function));
        const isCurrent = mocks.initEvoluFromAccountSeedIfNeeded.mock.calls[0][1] as () => boolean;
        expect(isCurrent()).toBe(true);
        mocks.user = { ...mocks.user!, id: 2 };
        expect(isCurrent()).toBe(false);
        finishInit("already_synced");
        await flushPromises();
        expect(mocks.storeGuestMnemonic).toHaveBeenCalledTimes(1);
        expect(mocks.flashSuccess).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it("does not bind a pending Profile enrollment after the page unmounts", async () => {
        mocks.route.query = {};
        mocks.user = { ...mocks.user!, guest_recovery_enrolled: false };
        let finish!: (value: unknown) => void;
        mocks.enrollGuestRecoveryPublicKey.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
        const wrapper = await mountProfile();
        await findButton(wrapper, "account.recovery_phrase_setup").trigger("click");
        wrapper.getComponent({ name: "GuestBackupWizardModal" }).vm.$emit("done", {
            recoveryPublicKeyHex: "a".repeat(64), mnemonic: "synthetic pending phrase",
        });
        await flushPromises();
        wrapper.unmount();
        finish({ user: { id: 1 } });
        await flushPromises();
        expect(mocks.storeGuestMnemonic).not.toHaveBeenCalled();
        expect(mocks.initEvoluFromAccountSeedIfNeeded).not.toHaveBeenCalled();
        expect(mocks.flashSuccess).not.toHaveBeenCalled();
    });

    it("requires confirmation before passkey restore re-links existing local invoicing data", async () => {
        const wrapper = await mountProfile();

        await findButton(wrapper, "account.passkey_restore_button").trigger("click");
        await flushPromises();

        expect(mocks.restoreWithAccountPasskey).toHaveBeenCalledTimes(1);
        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledWith("passkey phrase");
        expect(mocks.confirmLocalDefaultRelayOwner).not.toHaveBeenCalled();
        expect(mocks.bindRecoveryPhraseOnThisDevice).not.toHaveBeenCalled();
        expect(mocks.resetEvoluBootstrapForRetry).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("auth.guest_restore_owner_switch_title");

        await findButton(wrapper, "auth.guest_restore_owner_switch_confirm").trigger("click");
        await flushPromises();

        expect(mocks.restoreWithAccountPasskey).toHaveBeenCalledTimes(2);
        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledTimes(1);
        expect(mocks.resetEvoluBootstrapForRetry).toHaveBeenCalledTimes(1);
        expect(mocks.bindRecoveryPhraseOnThisDevice).toHaveBeenCalledWith("passkey phrase");
        expect(mocks.confirmLocalDefaultRelayOwner).toHaveBeenCalledWith("passkey phrase");
    }, 15000);

    it("requires confirmation before typed phrase restore re-links existing local invoicing data", async () => {
        const wrapper = await mountProfile();
        await wrapper.find("textarea").setValue("typed phrase");

        await findButton(wrapper, "account.recovery_phrase_restore_on_device_submit").trigger("click");
        await flushPromises();

        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledWith("typed phrase");
        expect(mocks.confirmLocalDefaultRelayOwner).not.toHaveBeenCalled();
        expect(mocks.bindRecoveryPhraseOnThisDevice).not.toHaveBeenCalled();
        expect(mocks.resetEvoluBootstrapForRetry).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("auth.guest_restore_owner_switch_title");

        await findLastButton(wrapper, "auth.guest_restore_owner_switch_confirm").trigger("click");
        await flushPromises();

        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledTimes(1);
        expect(mocks.resetEvoluBootstrapForRetry).toHaveBeenCalledTimes(1);
        expect(mocks.bindRecoveryPhraseOnThisDevice).toHaveBeenCalledWith("typed phrase");
        expect(mocks.confirmLocalDefaultRelayOwner).toHaveBeenCalledWith("typed phrase");
    }, 15000);

    it("requires confirmation before remembered-device passphrase unlock re-links existing local invoicing data", async () => {
        mocks.isDeviceRemembered.mockResolvedValue(true);
        const wrapper = await mountProfile();
        await wrapper.find("input[type='password']").setValue("device passphrase");

        await wrapper.find("form[autocomplete='on']").trigger("submit");
        await flushPromises();

        expect(mocks.unlockDeviceWithPassphrase).toHaveBeenCalledTimes(1);
        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledWith("device phrase");
        expect(mocks.clearSessionAccountMnemonic).toHaveBeenCalledTimes(1);
        expect(mocks.resetEvoluBootstrapForRetry).not.toHaveBeenCalled();
        expect(mocks.reloadApp).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("auth.guest_restore_owner_switch_title");

        await wrapper.find("input[type='password']").setValue("device passphrase");
        await wrapper.find("form[autocomplete='on']").trigger("submit");
        await flushPromises();

        expect(mocks.unlockDeviceWithPassphrase).toHaveBeenCalledTimes(2);
        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledTimes(1);
        expect(mocks.resetEvoluBootstrapForRetry).toHaveBeenCalledTimes(1);
        expect(mocks.confirmLocalDefaultRelayOwner).toHaveBeenCalled();
        expect(mocks.reloadApp).toHaveBeenCalledTimes(1);
    }, 15000);

    it("still shows the owner-switch warning when the previous session phrase is corrupted", async () => {
        mocks.isDeviceRemembered.mockResolvedValue(true);
        const wrapper = await mountProfile();
        // The unlock submit is the next caller of both mocks: hand it a corrupted
        // previous phrase and make re-storing it throw like the real service does.
        vi.mocked(getStoredAccountMnemonic).mockReturnValueOnce("corrupted junk");
        mocks.storeAccountMnemonic.mockImplementationOnce(() => {
            throw new Error("invalid_mnemonic");
        });
        await wrapper.find("input[type='password']").setValue("device passphrase");

        await wrapper.find("form[autocomplete='on']").trigger("submit");
        await flushPromises();

        // Best-effort restore: the corrupted previous phrase is dropped and the
        // confirmation step still shows instead of surfacing an unlock error.
        expect(mocks.storeAccountMnemonic).toHaveBeenCalledWith("corrupted junk");
        expect(mocks.clearSessionAccountMnemonic).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain("auth.guest_restore_owner_switch_title");
        expect(wrapper.text()).not.toContain("account.device_unlock_failed");
        expect(mocks.reloadApp).not.toHaveBeenCalled();
    }, 15000);

    it("requires confirmation before remembered-device passkey unlock re-links existing local invoicing data", async () => {
        mocks.isDeviceRemembered.mockResolvedValue(true);
        mocks.listDevicePasskeySlots.mockResolvedValue([
            {
                id: "slot-1",
                credentialIdB64: "credential-1",
                label: "Device passkey",
                createdAt: "2026-07-20T00:00:00Z",
                lastUsedAt: null,
            },
        ]);
        const wrapper = await mountProfile();

        await findButton(wrapper, "account.passkey_unlock_button").trigger("click");
        await flushPromises();

        expect(mocks.unlockDeviceWithPasskey).toHaveBeenCalledTimes(1);
        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledWith("device passkey phrase");
        expect(mocks.clearSessionAccountMnemonic).toHaveBeenCalledTimes(1);
        expect(mocks.resetEvoluBootstrapForRetry).not.toHaveBeenCalled();
        expect(mocks.reloadApp).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("auth.guest_restore_owner_switch_title");

        await findButton(wrapper, "auth.guest_restore_owner_switch_confirm").trigger("click");
        await flushPromises();

        expect(mocks.unlockDeviceWithPasskey).toHaveBeenCalledTimes(2);
        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledTimes(1);
        expect(mocks.resetEvoluBootstrapForRetry).toHaveBeenCalledTimes(1);
        expect(mocks.confirmLocalDefaultRelayOwner).toHaveBeenCalled();
        expect(mocks.reloadApp).toHaveBeenCalledTimes(1);
    }, 15000);

    it("reconciles the canonical profile after a successful local owner confirmation", async () => {
        mocks.previewOwnerSwitchImpact.mockResolvedValue({ switches: false });
        mocks.confirmLocalDefaultRelayOwner.mockReturnValue(true);
        const wrapper = await mountProfile();
        await wrapper.find("textarea").setValue("confirmed phrase");
        await findButton(wrapper, "account.recovery_phrase_restore_on_device_submit").trigger("click");
        await flushPromises();
        expect(mocks.confirmLocalDefaultRelayOwner).toHaveBeenCalledWith("confirmed phrase");
        expect(mocks.fetchUser).toHaveBeenCalledTimes(1);
        expect(mocks.captureLocalDefaultRelayOwnerConfirmation.mock.invocationCallOrder[0])
            .toBeLessThan(mocks.bindRecoveryPhraseOnThisDevice.mock.invocationCallOrder[0]);
        expect(mocks.bindRecoveryPhraseOnThisDevice.mock.invocationCallOrder[0])
            .toBeLessThan(mocks.confirmLocalDefaultRelayOwner.mock.invocationCallOrder[0]);
        wrapper.unmount();
    });

    it("does not authorize a default owner after a failed local bind", async () => {
        mocks.previewOwnerSwitchImpact.mockResolvedValue({ switches: false });
        mocks.bindRecoveryPhraseOnThisDevice.mockResolvedValue("owner_restore_failed");
        const wrapper = await mountProfile();
        await wrapper.find("textarea").setValue("failed phrase");
        await findButton(wrapper, "account.recovery_phrase_restore_on_device_submit").trigger("click");
        await flushPromises();
        expect(mocks.confirmLocalDefaultRelayOwner).not.toHaveBeenCalled();
        expect(mocks.fetchUser).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it("confirms the bound phrase even when the input changes during the bind", async () => {
        mocks.previewOwnerSwitchImpact.mockResolvedValue({ switches: false });
        let finishBind!: (result: string) => void;
        mocks.bindRecoveryPhraseOnThisDevice.mockImplementationOnce(() => new Promise<string>(resolve => { finishBind = resolve; }));
        const wrapper = await mountProfile();
        await wrapper.find("textarea").setValue("original phrase");
        await findButton(wrapper, "account.recovery_phrase_restore_on_device_submit").trigger("click");
        await flushPromises();
        await wrapper.find("textarea").setValue("different phrase");
        finishBind("already_synced");
        await flushPromises();
        expect(mocks.confirmLocalDefaultRelayOwner).toHaveBeenCalledWith("original phrase");
        wrapper.unmount();
    });

    it("requires a new owner-switch preview after the account changes away and back", async () => {
        const wrapper = await mountProfile();
        await wrapper.find("textarea").setValue("typed phrase");
        await findButton(wrapper, "account.recovery_phrase_restore_on_device_submit").trigger("click");
        await flushPromises();
        const originalUser = mocks.user!;
        mocks.user = { ...originalUser, id: 2 };
        mocks.user = originalUser;
        await flushPromises();
        await findButton(wrapper, "account.recovery_phrase_restore_on_device_submit").trigger("click");
        await flushPromises();
        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledTimes(2);
        expect(mocks.bindRecoveryPhraseOnThisDevice).not.toHaveBeenCalled();
        expect(mocks.confirmLocalDefaultRelayOwner).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it("allows actual Enterprise subscriber credit purchases while PRO sales are paused", async () => {
        mocks.user = { ...mocks.user!, role: 'enterprise', plan: {
            code: 'enterprise', name: 'Enterprise', features: [], max_stores: null, max_api_keys: null, max_ln_addresses: null,
        } };
        mocks.apiGet.mockResolvedValue({ data: {
            subscriber: { isActive: true }, creditPurchasesAvailable: true,
            billing: { isTrial: false, nextChargeSats: 500 }, creditBalance: 0, creditHistory: [],
        } });
        mocks.apiPost.mockResolvedValue({ data: {} });
        vi.spyOn(window, 'alert').mockImplementation(() => undefined);
        const wrapper = await mountProfile();
        await findButton(wrapper, 'account.tab_billing').trigger('click');
        const addCredit = findButton(wrapper, 'account.add_credit');
        expect(addCredit.isVisible()).toBe(true);
        await addCredit.trigger('click');
        await wrapper.get('input[type="number"]').setValue('500');
        await findButton(wrapper, 'account.add_credit_btn').trigger('click');
        await flushPromises();
        expect(mocks.apiPost).toHaveBeenCalledWith('/subscriptions/credits', { amount: 500, currency: 'SATS' });
        wrapper.unmount();
    });

    it("does not permit PRO subscriber credits based on an Enterprise account role", async () => {
        mocks.user = { ...mocks.user!, role: 'enterprise', plan: {
            code: 'enterprise', name: 'Enterprise', features: [], max_stores: null, max_api_keys: null, max_ln_addresses: null,
        } };
        mocks.apiGet.mockResolvedValue({ data: {
            subscriber: { isActive: true, planId: 'pro-provider-plan' }, creditPurchasesAvailable: false,
            billing: { isTrial: true, nextChargeSats: 500 }, creditBalance: 0, creditHistory: [],
        } });
        const wrapper = await mountProfile();
        await findButton(wrapper, 'account.tab_billing').trigger('click');
        expect(wrapper.findAll('button').some(button => button.text().includes('account.add_credit'))).toBe(false);
        expect(wrapper.findAll('button').some(button => button.text().includes('account.pay_now'))).toBe(false);
        const actions = wrapper.vm as unknown as { startCreditCheckout: (amount: number) => Promise<boolean> };
        await expect(actions.startCreditCheckout(500)).resolves.toBe(false);
        expect(mocks.apiPost).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    describe('device relay sync resume', () => {
        /** Stub only navigation so resume ordering remains observable. */
        function stubReload() {
            const reload = vi.fn();
            const original = window;
            vi.stubGlobal('window', new Proxy(original, {
                /** Preserve all browser behavior except the navigation under test. */
                get(target, key) { return key === 'location' ? { reload } : Reflect.get(target, key); },
            }));
            return reload;
        }
        it('keeps device sync off while refreshing canonical destinations before resume', async () => {
            vi.mocked(readRelayOverride).mockReturnValue({ kind: 'disabled' });
            const wrapper = await mountProfile();
            const reload = stubReload();
            try {
                mocks.resumeEvoluRelaySyncOnDevice.mockImplementationOnce(async () => {
                    expect(clearRelayOverride).not.toHaveBeenCalled();
                    mocks.user = { ...mocks.user!, evolu_relays: [{ url: 'wss://kept.test', enabled: true }] };
                    return true;
                });
                const state = wrapper.vm as unknown as { relaySyncDisabledOnDevice: boolean; toggleRelaySyncOnDevice: () => Promise<void> };
                state.relaySyncDisabledOnDevice = false;
                await state.toggleRelaySyncOnDevice();
                expect(mocks.resumeEvoluRelaySyncOnDevice).toHaveBeenCalledOnce();
                expect(mocks.fetchUser).not.toHaveBeenCalled();
                expect(clearRelayOverride).not.toHaveBeenCalled();
                expect(mocks.reconcileEvoluRelayPreferenceFromProfile).not.toHaveBeenCalled();
                expect(mocks.resumeEvoluRelaySyncOnDevice.mock.invocationCallOrder[0]).toBeLessThan(reload.mock.invocationCallOrder[0]);
                expect(reload).toHaveBeenCalledOnce();
            } finally { vi.unstubAllGlobals(); wrapper.unmount(); }
        });
        it.each(['failed', 'account_change', 'account_aba'] as const)('retains device-off after %s during canonical refresh', async failure => {
            vi.mocked(readRelayOverride).mockReturnValue({ kind: 'disabled' });
            const wrapper = await mountProfile();
            const reload = stubReload();
            try {
                mocks.resumeEvoluRelaySyncOnDevice.mockImplementationOnce(async () => {
                    if (failure !== 'failed') {
                        const original = mocks.user!;
                        mocks.user = { ...original, id: 2 };
                        if (failure === 'account_aba') mocks.user = original;
                    }
                    return failure !== 'failed';
                });
                const state = wrapper.vm as unknown as { relaySyncDisabledOnDevice: boolean; toggleRelaySyncOnDevice: () => Promise<void> };
                state.relaySyncDisabledOnDevice = false;
                await state.toggleRelaySyncOnDevice();
                expect(state.relaySyncDisabledOnDevice).toBe(true);
                expect(writeRelayOverrideDisabled).toHaveBeenCalledOnce();
                expect(clearRelayOverride).not.toHaveBeenCalled();
                expect(mocks.reconcileEvoluRelayPreferenceFromProfile).not.toHaveBeenCalled();
                expect(reload).not.toHaveBeenCalled();
            } finally { vi.unstubAllGlobals(); wrapper.unmount(); }
        });
    });


    describe("Profile local stats loading", () => {
        const stats: InvoicingLocalStats = {
            ownerId: "synthetic-local-owner",
            ownerIdShort: "synthetic",
            counts: {
                company: 1, contact: 2, document: 3, documentByType: { invoice: 3 },
                expense: 0, documentLine: 3, documentEvent: 1, expenseAttachment: 0,
                recurringProfile: 0, numberSeries: 1,
            },
            companies: [{ id: "company", name: "Verified local company", invoices: 3, documents: 3, contacts: 2 }],
            lastRelayPushAt: null,
            lastForceRelayPushAt: null,
            localDbBytes: null,
            relayError: "ProtocolQuotaError",
        };
        beforeEach(() => {
            mocks.route.query = { tab: "sync" };
            mocks.activeRelayUrl = "wss://relay.example.invalid";
            vi.mocked(getStoredAccountMnemonic).mockReturnValue("synthetic-stats-unlock");
            vi.mocked(loadInvoicingLocalStats).mockReset().mockResolvedValue(stats);
            vi.mocked(fetchEvoluRelayUsage).mockReset().mockResolvedValue(null);
            vi.mocked(evolu.exportDatabase).mockReset().mockResolvedValue(new Uint8Array(123));
        });
        afterEach(() => {
            vi.mocked(getStoredAccountMnemonic).mockReturnValue(null);
        });

        it("shows verified local counts without waiting for relay usage or exporting the database", async () => {
            vi.mocked(fetchEvoluRelayUsage).mockImplementation(() => new Promise(() => undefined));
            const wrapper = await mountProfile();
            const state = wrapper.vm as unknown as { evoluStatsLoading: boolean };
            expect(wrapper.text()).toContain("Verified local company");
            expect(state.evoluStatsLoading).toBe(false);
            expect(fetchEvoluRelayUsage).toHaveBeenCalledWith("wss://relay.example.invalid", stats.ownerId);
            expect(evolu.exportDatabase).not.toHaveBeenCalled();
            wrapper.unmount();
        });

        it("keeps local counts visible while an explicitly requested database size is calculated", async () => {
            let completeExport!: (bytes: Awaited<ReturnType<typeof evolu.exportDatabase>>) => void;
            vi.mocked(evolu.exportDatabase).mockImplementation(() => new Promise(resolve => { completeExport = resolve; }));
            const wrapper = await mountProfile();
            await findButton(wrapper, "account.evolu_stats_refresh").trigger("click");
            await flushPromises();
            const state = wrapper.vm as unknown as { evoluStatsLoading: boolean; evoluStatsExporting: boolean; evoluLocalStats: InvoicingLocalStats };
            expect(wrapper.text()).toContain("Verified local company");
            expect(state.evoluStatsLoading).toBe(false);
            expect(state.evoluStatsExporting).toBe(true);
            completeExport(new Uint8Array(42));
            await flushPromises();
            expect(state.evoluLocalStats.localDbBytes).toBe(42);
            expect(state.evoluStatsExporting).toBe(false);
            wrapper.unmount();
        });

        it("rejects late local stats after an account change away and back before requesting remote usage", async () => {
            let completeStats!: (value: InvoicingLocalStats) => void;
            vi.mocked(loadInvoicingLocalStats).mockImplementation(() => new Promise(resolve => { completeStats = resolve; }));
            const wrapper = await mountProfile();
            const original = mocks.user!;
            mocks.user = { ...original, id: 2 };
            mocks.user = original;
            completeStats(stats);
            await flushPromises();
            expect(wrapper.text()).not.toContain("Verified local company");
            expect(fetchEvoluRelayUsage).not.toHaveBeenCalled();
            expect(evolu.exportDatabase).not.toHaveBeenCalled();
            wrapper.unmount();
        });

        it("discards a late relay response after the account changes", async () => {
            let completeUsage!: (value: Awaited<ReturnType<typeof fetchEvoluRelayUsage>>) => void;
            vi.mocked(fetchEvoluRelayUsage).mockImplementation(() => new Promise(resolve => { completeUsage = resolve; }));
            const wrapper = await mountProfile();
            mocks.user = { ...mocks.user!, id: 2 };
            completeUsage({ ownerId: stats.ownerId, storedBytes: 5, quotaBytes: 100, firstActivityAt: null, lastActivityAt: null });
            await flushPromises();
            const state = wrapper.vm as unknown as { evoluRelayUsage: unknown; evoluLocalStats: unknown };
            expect(state.evoluRelayUsage).toBeNull();
            expect(state.evoluLocalStats).toBeNull();
            wrapper.unmount();
        });

        it("does not issue usage or export requests after the Profile is unmounted", async () => {
            let completeStats!: (value: InvoicingLocalStats) => void;
            vi.mocked(loadInvoicingLocalStats).mockImplementation(() => new Promise(resolve => { completeStats = resolve; }));
            const wrapper = await mountProfile();
            wrapper.unmount();
            completeStats(stats);
            await flushPromises();
            expect(fetchEvoluRelayUsage).not.toHaveBeenCalled();
            expect(evolu.exportDatabase).not.toHaveBeenCalled();
        });
    });

});
