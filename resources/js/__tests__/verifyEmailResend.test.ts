import { enableAutoUnmount, flushPromises, shallowMount } from "@vue/test-utils";
import { afterEach, beforeEach, expect, it, vi } from "vitest";

enableAutoUnmount(afterEach);

const mocks = vi.hoisted(() => ({
    verifyGet: vi.fn(),
    post: vi.fn(),
    ensureCsrfCookie: vi.fn(async () => true),
    auth: { user: null as unknown, fetchUser: vi.fn() },
}));

vi.mock("axios", () => ({ default: { create: () => ({ get: mocks.verifyGet }) } }));
vi.mock("../services/api", () => ({ default: { post: mocks.post } }));
vi.mock("../services/csrf", () => ({ ensureCsrfCookie: mocks.ensureCsrfCookie }));
vi.mock("../store/auth", () => ({ useAuthStore: () => mocks.auth }));
vi.mock("vue-router", () => ({
    useRoute: () => ({ params: { id: "1", hash: "abc" } }),
    useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));
vi.mock("vue-i18n", async (importOriginal) => ({
    ...await importOriginal<typeof import("vue-i18n")>(),
    useI18n: () => ({ t: (key: string) => key }),
}));

beforeEach(() => {
    vi.clearAllMocks();
    mocks.auth.user = null;
});

it("offers a fresh link when a verification link is rejected", async () => {
    mocks.verifyGet.mockResolvedValue({ status: 403, data: { message: "Invalid verification link. Please request a new email." } });
    mocks.post.mockResolvedValue({ data: {} });
    const { default: VerifyEmail } = await import("../pages/auth/VerifyEmail.vue");
    const wrapper = shallowMount(VerifyEmail, { global: { stubs: { "router-link": true } } });
    await flushPromises();

    expect(wrapper.text()).toContain("Please request a new email.");
    await wrapper.get("#resend-email").setValue("merchant@example.com");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(mocks.ensureCsrfCookie).toHaveBeenCalled();
    expect(mocks.post).toHaveBeenCalledWith("/auth/email/verification-notification", { email: "merchant@example.com" });
    expect(wrapper.text()).toContain("auth.verification_email_resent");
});

it("does not post the resend without a CSRF cookie", async () => {
    mocks.verifyGet.mockResolvedValue({ status: 403, data: { message: "Invalid verification link." } });
    mocks.ensureCsrfCookie.mockResolvedValueOnce(false);
    const { default: VerifyEmail } = await import("../pages/auth/VerifyEmail.vue");
    const wrapper = shallowMount(VerifyEmail, { global: { stubs: { "router-link": true } } });
    await flushPromises();

    await wrapper.get("#resend-email").setValue("merchant@example.com");
    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(mocks.post).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain("auth.failed_to_connect");
    expect(wrapper.get("button[type=submit]").attributes("disabled")).toBeUndefined();
});
