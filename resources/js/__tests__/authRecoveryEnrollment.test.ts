import { createPinia, setActivePinia } from "pinia";
import { beforeEach, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), csrf: vi.fn() }));

vi.mock("../services/api", () => ({ default: { get: mocks.get, post: mocks.post } }));
vi.mock("../services/csrf", () => ({ ensureCsrfCookie: mocks.csrf }));
vi.mock("../services/chorala", () => ({ syncChoralaIdentity: vi.fn() }));
vi.mock("../evolu/bootstrap", () => ({ ensureEvoluBoundToAccountSeed: vi.fn() }));

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    mocks.csrf.mockResolvedValue(true);
    mocks.get.mockResolvedValue({ data: { id: 7, guest_recovery_enrolled: true } });
});

it("enrolls the recovery key through the account endpoint, never through guest signup", async () => {
    mocks.post.mockResolvedValue({ data: { user: { id: 7 } } });
    const { useAuthStore } = await import("../store/auth");

    const store = useAuthStore();
    store.user = { id: 7, email: "original@example.com" };
    await store.enrollGuestRecoveryPublicKey("a".repeat(64));

    expect(mocks.post).toHaveBeenCalledTimes(1);
    expect(mocks.post).toHaveBeenCalledWith("/account/recovery-key", {
        expected_user_id: 7,
        recovery_public_key: "a".repeat(64),
    });
});

it("keeps enrollment bound to the initiating account while CSRF setup is in flight", async () => {
    const { useAuthStore } = await import("../store/auth");
    const store = useAuthStore();
    store.user = { id: 7, email: "original@example.com" };
    mocks.csrf.mockImplementationOnce(async () => {
        store.user = { id: 8, email: "other@example.com" };
        return true;
    });
    const mismatch = { response: { status: 409, data: { code: "account_changed" } } };
    mocks.post.mockRejectedValueOnce(mismatch);

    await expect(store.enrollGuestRecoveryPublicKey("a".repeat(64))).rejects.toBe(mismatch);

    expect(mocks.post).toHaveBeenCalledWith("/account/recovery-key", {
        expected_user_id: 7,
        recovery_public_key: "a".repeat(64),
    });
    expect(mocks.get).not.toHaveBeenCalled();
    expect(store.loading).toBe(false);
});

it("does not enroll when this tab has no authenticated account", async () => {
    const { useAuthStore } = await import("../store/auth");
    await expect(useAuthStore().enrollGuestRecoveryPublicKey("a".repeat(64))).rejects.toThrow("signed-in account");
    expect(mocks.post).not.toHaveBeenCalled();
});


it("sends the explicit wizard-opening account instead of a newer canonical profile account", async () => {
    const { useAuthStore } = await import("../store/auth");
    const store = useAuthStore();
    store.user = { id: 8, email: "current@example.test" };
    const mismatch = { response: { status: 409, data: { code: "account_changed" } } };
    mocks.post.mockRejectedValueOnce(mismatch);
    await expect(store.enrollGuestRecoveryPublicKey("a".repeat(64), 7)).rejects.toBe(mismatch);
    expect(mocks.post).toHaveBeenCalledWith("/account/recovery-key", {
        expected_user_id: 7, recovery_public_key: "a".repeat(64),
    });
    expect(mocks.get).not.toHaveBeenCalled();
    store.$dispose();
});

it("never substitutes the current account for an explicitly absent opening account", async () => {
    const { useAuthStore } = await import("../store/auth");
    const store = useAuthStore();
    store.user = { id: 8, email: "current@example.test" };
    await expect(store.enrollGuestRecoveryPublicKey("a".repeat(64), null)).rejects.toThrow("signed-in account");
    expect(mocks.post).not.toHaveBeenCalled();
    store.$dispose();
});


it("rejects an explicit former account when the current local session has already signed out", async () => {
    const { useAuthStore } = await import("../store/auth");
    const store = useAuthStore();
    store.user = null;
    await expect(store.enrollGuestRecoveryPublicKey("a".repeat(64), 7)).rejects.toThrow("signed-in account");
    expect(mocks.post).not.toHaveBeenCalled();
    store.$dispose();
});
