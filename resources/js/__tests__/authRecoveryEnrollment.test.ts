import { createPinia, setActivePinia } from "pinia";
import { beforeEach, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock("../services/api", () => ({ default: { get: mocks.get, post: mocks.post } }));
vi.mock("../services/csrf", () => ({ ensureCsrfCookie: vi.fn(async () => true) }));
vi.mock("../services/chorala", () => ({ syncChoralaIdentity: vi.fn() }));
vi.mock("../evolu/bootstrap", () => ({ ensureEvoluBoundToAccountSeed: vi.fn() }));

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    mocks.get.mockResolvedValue({ data: { id: 7, guest_recovery_enrolled: true } });
});

it("enrolls the recovery key through the account endpoint, never through guest signup", async () => {
    mocks.post.mockResolvedValue({ data: { user: { id: 7 } } });
    const { useAuthStore } = await import("../store/auth");

    await useAuthStore().enrollGuestRecoveryPublicKey("a".repeat(64));

    expect(mocks.post).toHaveBeenCalledTimes(1);
    expect(mocks.post).toHaveBeenCalledWith("/account/recovery-key", { recovery_public_key: "a".repeat(64) });
});
