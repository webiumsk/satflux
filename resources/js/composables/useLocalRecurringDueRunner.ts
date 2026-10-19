import type { Evolu } from "@evolu/common/local-first";
import { useI18n } from "vue-i18n";
import { processDueLocalRecurringProfiles } from "@/evolu/recurringDueRunner";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { useAuthStore } from "@/store/auth";
import { hasEffectiveFeature } from "@/composables/useEffectiveAccess";
import { useFlashStore } from "@/store/flash";

const lastRunByAccount = new Map<number, number>();
const MIN_RUN_INTERVAL_MS = 60_000;

export function useLocalRecurringDueRunner() {
    const flash = useFlashStore();
    const auth = useAuthStore();
    const { t } = useI18n();

    async function runDueProfiles(evolu: Evolu<InvoicingLocalSchema>): Promise<void> {
        if (!isInvoicingLocalFirst()) return;
        const accountId = auth.user?.id;
        if (accountId == null || auth.user?.is_guest) return;
        if (Date.now() - (lastRunByAccount.get(accountId) ?? 0) < MIN_RUN_INTERVAL_MS) return;
        // Automatic local writes require a fresh server capability; offline data remains readable.
        if (!await auth.fetchUser() || auth.user?.id !== accountId
            || auth.user?.is_guest || !auth.user?.email_verified_at
            || !hasEffectiveFeature(auth.user, 'business_invoicing')) return;
        lastRunByAccount.set(accountId, Date.now());
        const result = await processDueLocalRecurringProfiles(evolu);

        if (result.generated.length === 1) {
            flash.success(
                t("invoicing.recurring_due_generated_one", { number: result.generated[0].number }),
            );
        } else if (result.generated.length > 1) {
            flash.success(
                t("invoicing.recurring_due_generated_many", { count: result.generated.length }),
            );
        }

        if (result.errors > 0) {
            flash.warning(
                t("invoicing.recurring_due_generated_errors", { count: result.errors }),
            );
        }
    }

    return { runDueProfiles };
}
