import { enableAutoUnmount, flushPromises, shallowMount } from "@vue/test-utils";
import { afterEach, beforeEach, expect, it, vi } from "vitest";

enableAutoUnmount(afterEach);
const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    push: vi.fn(),
    auth: { user: null as { id: number; email: string } | null, fetchUser: vi.fn() },
}));
vi.mock("axios", () => ({ default: { create: () => ({ get: mocks.get }) } }));
vi.mock("../services/api", () => ({ default: { post: vi.fn() } }));
vi.mock("../services/csrf", () => ({ ensureCsrfCookie: vi.fn(async () => true) }));
vi.mock("../store/auth", () => ({ useAuthStore: () => mocks.auth }));
vi.mock("vue-router", () => ({
    useRoute: () => ({ params: { id: "2", hash: "synthetic-hash" } }),
    useRouter: () => ({ push: mocks.push }),
}));
vi.mock("vue-i18n", async (importOriginal) => ({
    ...await importOriginal<typeof import("vue-i18n")>(),
    useI18n: () => ({ t: (key: string) => key }),
}));

beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    mocks.auth.user = null;
});
afterEach(() => vi.useRealTimers());

it.each([false, true])("verification preserves authentication (already signed in: %s) and offers explicit sign-in", async (authenticated) => {
    const current = authenticated ? { id: 1, email: "current@example.test" } : null;
    mocks.auth.user = current;
    // Even an older server's auto-login-shaped response must not be adopted.
    mocks.get.mockResolvedValue({ status: 200, data: { verified: true, user: { id: 2, email: "target@example.test" } } });
    const { default: VerifyEmail } = await import("../pages/auth/VerifyEmail.vue");
    const wrapper = shallowMount(VerifyEmail, { global: { stubs: { "router-link": true } } });
    await flushPromises();

    expect(wrapper.text()).toContain("auth.email_verified_success");
    expect(mocks.auth.user).toBe(current);
    expect(mocks.auth.fetchUser).not.toHaveBeenCalled();
    await vi.advanceTimersByTimeAsync(2000);
    expect(mocks.push).toHaveBeenCalledWith({ name: "login", query: { email_verified: "1" } });
    expect(mocks.auth.user).toBe(current);
});

it("a rejected link leaves the current account untouched and does not redirect", async () => {
    const current = { id: 1, email: "current@example.test" };
    mocks.auth.user = current;
    mocks.get.mockResolvedValue({ status: 403, data: { message: "Invalid verification link." } });
    const { default: VerifyEmail } = await import("../pages/auth/VerifyEmail.vue");
    const wrapper = shallowMount(VerifyEmail, { global: { stubs: { "router-link": true } } });
    await flushPromises();
    await vi.advanceTimersByTimeAsync(3000);

    expect(wrapper.text()).toContain("Invalid verification link.");
    expect(mocks.auth.user).toBe(current);
    expect(mocks.auth.fetchUser).not.toHaveBeenCalled();
    expect(mocks.push).not.toHaveBeenCalled();
});
