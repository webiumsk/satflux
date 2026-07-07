import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { storesApi } from "@/services/api";
import { useLocalStoreSanitizer } from "@/composables/useLocalStoreSanitizer";
import { sanitizeLocalStoreReferences } from "@/evolu/sanitizeStoreReferences";

vi.mock("@/services/api", () => ({
    default: {
        get: vi.fn(),
    },
    storesApi: {
        list: vi.fn(),
    },
}));

vi.mock("@/evolu/sanitizeStoreReferences", () => ({
    sanitizeLocalStoreReferences: vi.fn(async () => ({
        clearedCompanyLinks: 0,
        clearedDocumentStores: 0,
        clearedRecurringStores: 0,
    })),
}));

vi.mock("@/store/flash", () => ({
    useFlashStore: () => ({
        warning: vi.fn(),
    }),
}));

vi.mock("vue-i18n", () => ({
    useI18n: () => ({
        t: (key: string) => key,
    }),
}));

describe("useLocalStoreSanitizer", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        useLocalStoreSanitizer().resetSanitizerSession();
    });

    it("does not clear local store references when store ownership cannot be loaded", async () => {
        const evolu = {};
        (storesApi.list as ReturnType<typeof vi.fn>).mockRejectedValueOnce(new Error("network"));

        const sanitizer = useLocalStoreSanitizer();
        await sanitizer.sanitizeIfNeeded(evolu as never);

        expect(sanitizeLocalStoreReferences).not.toHaveBeenCalled();

        (storesApi.list as ReturnType<typeof vi.fn>).mockResolvedValueOnce([
            { id: "store-1", name: "Store 1", wallet_type: null, created_at: "", updated_at: "" },
        ]);

        await sanitizer.sanitizeIfNeeded(evolu as never);

        expect(sanitizeLocalStoreReferences).toHaveBeenCalledTimes(1);
        expect(sanitizeLocalStoreReferences).toHaveBeenCalledWith(
            evolu,
            new Set(["store-1"]),
        );
    });
});
