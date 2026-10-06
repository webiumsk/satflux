import { enableAutoUnmount, flushPromises, shallowMount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";

enableAutoUnmount(afterEach);

const mocks = vi.hoisted(() => ({
    createStore: vi.fn(),
    fetchStore: vi.fn(),
    updateSettings: vi.fn(),
    error: vi.fn(),
    push: vi.fn(),
}));

vi.mock("../store/stores", () => ({ useStoresStore: () => mocks }));
vi.mock("../store/flash", () => ({ useFlashStore: () => ({ clear: vi.fn(), error: mocks.error }) }));
vi.mock("vue-router", () => ({ useRouter: () => ({ push: mocks.push }) }));
vi.mock("vue-i18n", async (importOriginal) => ({
    ...await importOriginal<typeof import("vue-i18n")>(),
    useI18n: () => ({ t: (key: string) => key }),
}));
vi.mock("../services/api", () => ({
    storesApi: { settings: { update: mocks.updateSettings } },
    walletApi: {},
}));
// The wallet step loads this asynchronously; keep imports inside the test lifetime.
vi.mock("../components/stores/WalletConnectionSmartPaste.vue", () => ({
    default: { name: "WalletConnectionSmartPaste", template: "<div />" },
}));
vi.mock("../components/stores/WalletConnectionTypeGuide.vue", () => ({
    default: { name: "WalletConnectionTypeGuide", template: "<div />" },
}));
vi.mock("../components/stores/wallet-connection/LightningAddressQuickConnect.vue", () => ({
    default: { name: "LightningAddressQuickConnect", template: "<div />" },
}));
vi.mock("../composables/useAccountLimits", () => ({
    useAccountLimits: () => ({
        limits: ref({ stores: { current: 0, max: 1, unlimited: false } }),
        load: vi.fn(async () => {}),
    }),
}));

beforeEach(() => {
    vi.clearAllMocks();
    mocks.createStore.mockResolvedValue({ id: "first-store", name: "First Store" });
    mocks.fetchStore.mockResolvedValue({ id: "first-store", name: "Edited Store" });
    mocks.updateSettings.mockResolvedValue({ id: "remote-store", name: "Edited Store" });
});

async function mountWizard() {
    const { default: Create } = await import("../pages/stores/Create.vue");
    const wrapper = shallowMount(Create, { global: { stubs: { "router-link": true } } });
    await flushPromises();
    return wrapper;
}

function button(wrapper: Awaited<ReturnType<typeof mountWizard>>, key: string) {
    const match = wrapper.findAll("button").find((candidate) => candidate.text().includes(key));
    if (!match) throw new Error(`Button missing: ${key}`);
    return match;
}

describe("first store wizard", () => {
    it("Back then Next updates the existing store and keeps its local id", async () => {
        const wrapper = await mountWizard();
        await wrapper.get("#name").setValue("First Store");
        await button(wrapper, "create_store.next_step").trigger("click");
        await flushPromises();
        expect(mocks.createStore).toHaveBeenCalledTimes(1);

        await button(wrapper, "create_store.back").trigger("click");
        await wrapper.get("#name").setValue("Edited Store");
        await wrapper.get("#default_currency").setValue("USD");
        await button(wrapper, "create_store.next_step").trigger("click");
        await flushPromises();

        expect(mocks.createStore).toHaveBeenCalledTimes(1);
        expect(mocks.updateSettings).toHaveBeenCalledWith("first-store", expect.objectContaining({
            name: "Edited Store", default_currency: "USD",
        }));
        expect(mocks.fetchStore).toHaveBeenCalledWith("first-store");
        expect(wrapper.text()).toContain("create_store.wallet_paste_hint");
        // Settings responses use the BTCPay id. Another Back/Next must keep the local id.
        await button(wrapper, "create_store.back").trigger("click");
        await button(wrapper, "create_store.next_step").trigger("click");
        await flushPromises();
        expect(mocks.updateSettings.mock.calls[1]![0]).toBe("first-store");
        expect(mocks.createStore).toHaveBeenCalledTimes(1);
    });

    it("a failed settings update stays on step one and retries without creating another store", async () => {
        const wrapper = await mountWizard();
        await wrapper.get("#name").setValue("First Store");
        await button(wrapper, "create_store.next_step").trigger("click");
        await flushPromises();
        await button(wrapper, "create_store.back").trigger("click");
        mocks.updateSettings.mockRejectedValueOnce(new Error("offline"));

        await button(wrapper, "create_store.next_step").trigger("click");
        await flushPromises();
        expect(wrapper.find("#name").exists()).toBe(true);
        expect(mocks.error).toHaveBeenCalled();
        await button(wrapper, "create_store.next_step").trigger("click");
        await flushPromises();
        expect(mocks.createStore).toHaveBeenCalledTimes(1);
        expect(mocks.updateSettings).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain("create_store.wallet_paste_hint");
    });

    it("an incomplete payment account shows the support message instead of a retry hint", async () => {
        mocks.createStore.mockRejectedValueOnce({
            response: { status: 503, data: { code: "payment_account_incomplete", message: "server text" } },
        });
        const wrapper = await mountWizard();
        await wrapper.get("#name").setValue("First Store");
        await button(wrapper, "create_store.next_step").trigger("click");
        await flushPromises();

        expect(mocks.error).toHaveBeenCalledWith("create_store.payment_account_incomplete");
        expect(wrapper.find("#name").exists()).toBe(true);
    });
});
