import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
    route: { query: { restore_phrase: "1" } as Record<string, string | undefined> },
    routerReplace: vi.fn(),
    flashSuccess: vi.fn(),
    flashWarning: vi.fn(),
    flashError: vi.fn(),
    restoreWithAccountPasskey: vi.fn(),
    bindRecoveryPhraseOnThisDevice: vi.fn(),
    previewOwnerSwitchImpact: vi.fn(),
    resetEvoluBootstrapForRetry: vi.fn(),
    listAccountEnvelopes: vi.fn(),
    apiGet: vi.fn(),
    invoicingCompaniesList: vi.fn(),
}));

vi.mock("vue-router", () => ({
    useRoute: () => mocks.route,
    useRouter: () => ({
        replace: mocks.routerReplace,
        push: vi.fn(),
    }),
}));

vi.mock("../store/auth", () => ({
    useAuthStore: () => ({
        isAuthenticated: true,
        user: {
            id: 1,
            name: "Merchant",
            email: "merchant@example.com",
            role: "free",
            is_guest: false,
            guest_recovery_enrolled: true,
            can_use_password_login: true,
            evolu_relay_url: null,
        },
        fetchUser: vi.fn(),
        enrollGuestRecoveryPublicKey: vi.fn(),
    }),
}));

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
        post: vi.fn(),
        put: vi.fn(),
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
    deriveRecoveryPublicKeyHex: vi.fn((phrase: string) => `pk:${phrase}`),
    getStoredAccountMnemonic: vi.fn(() => null),
    previewOwnerSwitchImpact: mocks.previewOwnerSwitchImpact,
}));

vi.mock("../services/deviceUnlock/provider", () => ({
    addAccountPasskeyFromSession: vi.fn(),
    addPasskeyToRememberedDevice: vi.fn(),
    changeDevicePassphrase: vi.fn(),
    forgetDevice: vi.fn(),
    isDeviceRemembered: vi.fn(async () => false),
    listDevicePasskeySlots: vi.fn(async () => []),
    passkeyPrfUnlockProvider: { isSupported: vi.fn(async () => true) },
    removeDevicePasskeySlot: vi.fn(),
    rememberDeviceWithPassphrase: vi.fn(),
    restoreWithAccountPasskey: mocks.restoreWithAccountPasskey,
    unlockDeviceWithPasskey: vi.fn(),
    unlockDeviceWithPassphrase: vi.fn(),
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
        reloadApp: vi.fn(),
    },
}));

vi.mock("../evolu/config", () => ({
    getEvoluRelayBuildInfo: () => ({ enabled: false, url: "" }),
    normalizeEvoluRelayBaseUrl: (value: string | null | undefined) => value ?? "",
}));

vi.mock("../services/evoluRelayPreference", () => ({
    getEvoluRelayRuntimeInfo: () => ({
        url: "",
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
    probeRelayReachability: vi.fn(async () => true),
    relayUsagePercent: vi.fn(() => 0),
}));

vi.mock("../evolu/relayOverrideStorage", () => ({
    clearRelayOverride: vi.fn(),
    readRelayOverride: vi.fn(() => ({ kind: "none" })),
    writeRelayOverrideDisabled: vi.fn(),
    writeRelayOverrideUrl: vi.fn(),
}));

vi.mock("../utils/dashboardInvoicingTab", () => ({
    dashboardInvoicingTabPath: () => "/dashboard/invoicing",
}));

function findButton(wrapper: VueWrapper, text: string) {
    const button = wrapper.findAll("button").find((candidate) => candidate.text().includes(text));
    if (!button) {
        throw new Error(`button not found: ${text}`);
    }
    return button;
}

async function mountProfile(): Promise<VueWrapper> {
    const { default: Profile } = await import("../pages/account/Profile.vue");
    const wrapper = mount(Profile, {
        global: {
            stubs: {
                RouterLink: { template: "<a><slot /></a>" },
                GuestBackupWizardModal: true,
                GuestUpgradeForm: true,
                InvoicingBackupCard: true,
                LocalStorageCard: true,
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
        mocks.route.query = { restore_phrase: "1" };
        mocks.restoreWithAccountPasskey.mockResolvedValue({ recoveryPhrase: "passkey phrase" });
        mocks.bindRecoveryPhraseOnThisDevice.mockResolvedValue("already_synced");
        mocks.previewOwnerSwitchImpact.mockResolvedValue({
            switches: true,
            companies: 1,
            contacts: 2,
            documents: 3,
        });
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
        expect(mocks.bindRecoveryPhraseOnThisDevice).not.toHaveBeenCalled();
        expect(mocks.resetEvoluBootstrapForRetry).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("auth.guest_restore_owner_switch_title");

        await findButton(wrapper, "auth.guest_restore_owner_switch_confirm").trigger("click");
        await flushPromises();

        expect(mocks.restoreWithAccountPasskey).toHaveBeenCalledTimes(2);
        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledTimes(1);
        expect(mocks.resetEvoluBootstrapForRetry).toHaveBeenCalledTimes(1);
        expect(mocks.bindRecoveryPhraseOnThisDevice).toHaveBeenCalledWith("passkey phrase");
    });

    it("requires confirmation before typed phrase restore re-links existing local invoicing data", async () => {
        const wrapper = await mountProfile();
        await wrapper.find("textarea").setValue("typed phrase");

        await findButton(wrapper, "account.recovery_phrase_restore_on_device_submit").trigger("click");
        await flushPromises();

        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledWith("typed phrase");
        expect(mocks.bindRecoveryPhraseOnThisDevice).not.toHaveBeenCalled();
        expect(mocks.resetEvoluBootstrapForRetry).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain("auth.guest_restore_owner_switch_title");

        await findButton(wrapper, "auth.guest_restore_owner_switch_confirm").trigger("click");
        await flushPromises();

        expect(mocks.previewOwnerSwitchImpact).toHaveBeenCalledTimes(1);
        expect(mocks.resetEvoluBootstrapForRetry).toHaveBeenCalledTimes(1);
        expect(mocks.bindRecoveryPhraseOnThisDevice).toHaveBeenCalledWith("typed phrase");
    });
});
