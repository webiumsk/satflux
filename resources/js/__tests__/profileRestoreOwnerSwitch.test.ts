import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import type { Component } from "vue";
import type { User } from "../store/auth";
import { clearRelayOverride, readRelayOverride, writeRelayOverrideDisabled } from "../evolu/relayOverrideStorage";
import { getStoredAccountMnemonic } from "../services/accountSeed";

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
    reconcileEvoluRelayPreferenceFromProfile: vi.fn(),
    resumeEvoluRelaySyncOnDevice: vi.fn(),
    captureLocalDefaultRelayOwnerConfirmation: vi.fn(),
    confirmLocalDefaultRelayOwner: vi.fn(),
    writeRelayOverrideUrl: vi.fn(),
    probeRelayReachability: vi.fn(async () => true),
    activeRelayUrl: "",
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
            enrollGuestRecoveryPublicKey: vi.fn(),
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
    storeGuestMnemonic: vi.fn(),
}));

vi.mock("../services/accountSeed", () => ({
    initEvoluFromAccountSeedIfNeeded: vi.fn(async () => "already_synced"),
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
        mocks.fetchUser.mockResolvedValue(true);
        mocks.resumeEvoluRelaySyncOnDevice.mockResolvedValue(true);
        vi.mocked(readRelayOverride).mockReturnValue({ kind: "none" });
        mocks.captureLocalDefaultRelayOwnerConfirmation.mockReturnValue(mocks.confirmLocalDefaultRelayOwner);
        mocks.confirmLocalDefaultRelayOwner.mockReturnValue(false);
        mocks.activeRelayUrl = "";
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

});
