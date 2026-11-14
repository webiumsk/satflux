import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import { createI18n } from "vue-i18n";
import type { Ref } from "vue";
import type { User } from "../store/auth";
import type { RelayConnections } from "../evolu/relayConnectionStatus";
import type { RelayProtocols, RelayProtocolState } from "../evolu/relayProtocolStatus";
import en from "../locales/en.json";
import LocalStorageWarningBanner from "../components/invoicing/LocalStorageWarningBanner.vue";
import { evoluErrorRef, resetEvoluErrorMonitorForTests } from "../evolu/evoluErrorMonitor";

const PRIMARY = "wss://evolu.satflux.io";
const SECONDARY = "wss://relay.example.test";
const mocks = vi.hoisted(() => ({
    estimate: vi.fn(),
    user: undefined as Ref<User | null> | undefined,
    connections: undefined as Ref<RelayConnections> | undefined,
    protocols: undefined as Ref<RelayProtocols> | undefined,
    online: undefined as Ref<boolean> | undefined,
    active: [] as string[],
    notifyActive: undefined as (() => void) | undefined,
    unsubscribe: vi.fn(),
}));
vi.mock("../services/browserStorage", async importOriginal => ({
    ...await importOriginal<typeof import("../services/browserStorage")>(),
    getStorageEstimate: mocks.estimate,
}));
vi.mock("../store/auth", async () => {
    const { shallowRef } = await import("vue");
    mocks.user = shallowRef(null);
    return { useAuthStore: () => ({ get user() { return mocks.user!.value; } }) };
});
vi.mock("../composables/useEvoluRelayConnections", async () => {
    const { ref } = await import("vue");
    mocks.connections = ref({});
    mocks.protocols = ref({});
    return { useEvoluRelayConnections: () => ({ connections: mocks.connections, protocols: mocks.protocols }) };
});
vi.mock("../composables/useOnlineStatus", async () => {
    const { ref } = await import("vue");
    mocks.online = ref(true);
    return { useOnlineStatus: () => mocks.online };
});
vi.mock("../evolu/relayPreferenceLifecycle", () => ({
    getActiveEvoluRelayUrls: () => mocks.active,
    subscribeActiveEvoluRelayUrls: (listener: () => void) => {
        mocks.notifyActive = listener;
        return () => { mocks.notifyActive = undefined; mocks.unsubscribe(); };
    },
}));

const wrappers: VueWrapper[] = [];
beforeEach(() => {
    vi.clearAllMocks();
    mocks.user!.value = { id: 1, evolu_relays: [{ url: PRIMARY, enabled: true }, { url: SECONDARY, enabled: true }] } as User;
    mocks.active = [PRIMARY, SECONDARY];
    mocks.connections!.value = { [PRIMARY]: "connected", [SECONDARY]: "connected" };
    mocks.protocols!.value = {};
    mocks.online!.value = true;
});
afterEach(() => {
    for (const wrapper of wrappers.splice(0)) wrapper.unmount();
    resetEvoluErrorMonitorForTests();
    mocks.estimate.mockReset();
});

/** Mount the actual notice with synthetic account, storage and worker evidence. */
async function banner(error: string | null = null, usagePercent = 1) {
    evoluErrorRef().value = error;
    mocks.estimate.mockResolvedValue({ usageBytes: usagePercent, quotaBytes: 100, usagePercent });
    const wrapper = mount(LocalStorageWarningBanner, {
        attrs: { class: "mb-4" },
        global: {
            plugins: [createI18n({ legacy: false, locale: "en", messages: { en } })],
            stubs: { RouterLink: { props: ["to"], template: '<a :href="to"><slot /></a>' } },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();
    return wrapper;
}

/** Supply only sanitized protocol observations, as the existing worker channel does. */
function observe(state: RelayProtocolState, url = SECONDARY): void {
    mocks.protocols!.value = { ...mocks.protocols!.value, [url]: { state, observedAt: Date.now() } };
}

it("does not present the historical SDK quota error as a current relay failure", async () => {
    const wrapper = await banner("ProtocolQuotaError");
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
});

it("names the quota-rejecting relay even while connected, without declaring the other copy safe", async () => {
    observe("quota_exceeded");
    observe("response_received", PRIMARY);
    const wrapper = await banner("ProtocolQuotaError");
    expect(wrapper.text()).toContain(en.invoicing.relay_warning_some);
    expect(wrapper.get("li").text()).toContain(SECONDARY);
    expect(wrapper.get("li").text()).toContain(en.account.evolu_relay_protocol_quota_exceeded);
    expect(wrapper.text()).toContain(en.invoicing.relay_warning_quota_steps);
    expect(wrapper.text()).toContain(en.invoicing.relay_warning_backup_hint);
    expect(wrapper.find('[role="status"]').classes()).toContain("bg-amber-50");
    expect(wrapper.classes()).toContain("mb-4");
    expect(wrapper.findAll("a").map(link => link.attributes("href"))).toEqual(["/account?tab=sync", "/account?tab=sync"]);
});

it("clears the recovered relay warning even when the SDK error remains set", async () => {
    observe("quota_exceeded");
    const wrapper = await banner("ProtocolQuotaError");
    observe("response_received");
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
    expect(evoluErrorRef().value).toBe("ProtocolQuotaError");
});

it("distinguishes all enabled relays reporting errors from a partial failure", async () => {
    observe("quota_exceeded");
    observe("sync_failed", PRIMARY);
    const wrapper = await banner();
    expect(wrapper.text()).toContain(en.invoicing.relay_warning_all);
    expect(wrapper.findAll("li")).toHaveLength(2);
    expect(wrapper.find('[role="status"]').classes()).toContain("bg-red-50");
    observe("response_received", PRIMARY);
    await flushPromises();
    expect(wrapper.text()).toContain(en.invoicing.relay_warning_some);
    expect(wrapper.text()).not.toContain(en.invoicing.relay_warning_all);
});

it("does not count an unknown enabled relay as another observed failure", async () => {
    observe("quota_exceeded");
    mocks.connections!.value = { [SECONDARY]: "connected" };
    const wrapper = await banner();
    expect(wrapper.text()).toContain(en.invoicing.relay_warning_some);
    expect(wrapper.text()).not.toContain(en.invoicing.relay_warning_all);
});

it("reports all affected when the sole enabled relay fails and ignores a disabled relay", async () => {
    mocks.user!.value = { ...mocks.user!.value!, evolu_relays: [{ url: PRIMARY, enabled: false }, { url: SECONDARY, enabled: true }] };
    observe("quota_exceeded");
    observe("sync_failed", PRIMARY);
    const wrapper = await banner();
    expect(wrapper.text()).toContain(en.invoicing.relay_warning_all);
    expect(wrapper.findAll("li")).toHaveLength(1);
    expect(wrapper.text()).not.toContain(PRIMARY);
    mocks.user!.value = { ...mocks.user!.value!, evolu_relays: [] };
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
});

it("hides paused, device-off and removed routes despite retained worker errors", async () => {
    observe("quota_exceeded");
    const wrapper = await banner();
    mocks.active = [];
    mocks.notifyActive?.();
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
    mocks.active = [SECONDARY];
    mocks.notifyActive?.();
    await flushPromises();
    expect(wrapper.text()).toContain(SECONDARY);
    mocks.user!.value = { ...mocks.user!.value!, evolu_relays: [{ url: PRIMARY, enabled: true }] };
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
});

it("does not expose another account's worker destinations after logout or a different selection", async () => {
    observe("quota_exceeded");
    const wrapper = await banner();
    mocks.user!.value = null;
    await flushPromises();
    expect(wrapper.text()).not.toContain(SECONDARY);
    mocks.user!.value = { id: 2, evolu_relays: [{ url: PRIMARY, enabled: true }] } as User;
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
});

it("removes a warning when the observation channel expires its evidence", async () => {
    observe("quota_exceeded");
    const wrapper = await banner("ProtocolQuotaError");
    mocks.protocols!.value = {};
    mocks.connections!.value = {};
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
});

it("reports an observed connection failure only when the browser is online", async () => {
    mocks.connections!.value = { [PRIMARY]: "connected", [SECONDARY]: "disconnected" };
    const wrapper = await banner();
    expect(wrapper.get("li").text()).toContain(en.account.evolu_relay_state_disconnected);
    expect(wrapper.text()).toContain(en.invoicing.relay_warning_retry_steps);
    mocks.online!.value = false;
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
    observe("quota_exceeded");
    await flushPromises();
    expect(wrapper.text()).toContain(en.account.evolu_relay_protocol_quota_exceeded);
});

it.each([
    ["write_key_rejected", "relay_warning_key_steps"],
    ["version_mismatch", "relay_warning_version_steps"],
    ["write_failed", "relay_warning_retry_steps"],
    ["sync_failed", "relay_warning_retry_steps"],
    ["invalid_message", "relay_warning_retry_steps"],
] as const)("provides recovery guidance for %s", async (state, key) => {
    observe(state);
    const wrapper = await banner();
    expect(wrapper.text()).toContain(en.invoicing[key]);
    expect(wrapper.text()).toContain(en.account[`evolu_relay_protocol_${state}`]);
    expect(wrapper.text()).toContain(en.invoicing.relay_warning_next_steps);
});

it("preserves local storage pressure separately from an active relay error", async () => {
    observe("quota_exceeded");
    const wrapper = await banner("ProtocolQuotaError", 85);
    const notices = wrapper.findAll('[role="status"]');
    expect(notices).toHaveLength(2);
    expect(notices[0].text()).toContain(en.invoicing.storage_warning_title);
    expect(notices[0].text()).toContain("85%");
    expect(notices[0].text()).not.toContain(SECONDARY);
    expect(notices[1].text()).toContain(SECONDARY);
});

it("describes a generic storage failure without claiming measured quota pressure", async () => {
    const wrapper = await banner("StorageError");
    expect(wrapper.text()).toContain(en.invoicing.storage_error_title);
    expect(wrapper.text()).toContain(en.invoicing.storage_quota_exceeded);
    expect(wrapper.text()).not.toContain(en.invoicing.storage_warning_title);
});

it("hides the warning when storage and observed relays report no problem and cleans up", async () => {
    const wrapper = await banner();
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
    wrapper.unmount();
    expect(mocks.unsubscribe).toHaveBeenCalledOnce();
    expect(mocks.notifyActive).toBeUndefined();
    wrappers.pop();
});
