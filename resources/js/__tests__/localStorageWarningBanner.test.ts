import { afterEach, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import { createI18n } from "vue-i18n";
import en from "../locales/en.json";
import LocalStorageWarningBanner from "../components/invoicing/LocalStorageWarningBanner.vue";
import { evoluErrorRef, resetEvoluErrorMonitorForTests } from "../evolu/evoluErrorMonitor";

const estimate = vi.hoisted(() => vi.fn());
vi.mock("@/services/browserStorage", async importOriginal => ({
    ...await importOriginal<typeof import("@/services/browserStorage")>(),
    getStorageEstimate: estimate,
}));

afterEach(() => {
    resetEvoluErrorMonitorForTests();
    estimate.mockReset();
});

async function banner(error: string | null, usagePercent = 1) {
    evoluErrorRef().value = error;
    estimate.mockResolvedValue({ usageBytes: usagePercent, quotaBytes: 100, usagePercent });
    const wrapper = mount(LocalStorageWarningBanner, {
        global: {
            plugins: [createI18n({ legacy: false, locale: "en", messages: { en } })],
            stubs: { RouterLink: { template: '<a><slot /></a>' } },
        },
    });
    await flushPromises();
    return wrapper;
}

it("shows a relay quota refusal with its synchronization explanation", async () => {
    const wrapper = await banner("ProtocolQuotaError");
    expect(wrapper.text()).toContain(en.invoicing.relay_quota_title);
    expect(wrapper.text()).toContain(en.account.evolu_relay_quota_hint);
    expect(wrapper.text()).not.toContain(en.invoicing.storage_warning_title);
    expect(wrapper.text()).not.toContain(en.invoicing.storage_quota_exceeded);
    expect(wrapper.text()).toContain(en.invoicing.storage_export_cta);
    wrapper.unmount();
});

it("preserves the local pressure warning alongside a relay quota refusal", async () => {
    const wrapper = await banner("ProtocolQuotaError", 85);
    expect(wrapper.text()).toContain(en.invoicing.storage_warning_title);
    expect(wrapper.text()).toContain("85%");
    expect(wrapper.text()).toContain(en.account.evolu_relay_quota_hint);
    wrapper.unmount();
});

it("describes a generic storage failure without claiming measured quota pressure", async () => {
    const wrapper = await banner("StorageError");
    expect(wrapper.text()).toContain(en.invoicing.storage_error_title);
    expect(wrapper.text()).toContain(en.invoicing.storage_quota_exceeded);
    expect(wrapper.text()).not.toContain(en.invoicing.storage_warning_title);
    wrapper.unmount();
});

it("hides the warning when storage and synchronization report no problem", async () => {
    const wrapper = await banner(null);
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
    wrapper.unmount();
});
