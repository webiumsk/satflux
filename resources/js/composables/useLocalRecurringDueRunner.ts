import type { Evolu } from "@evolu/common/local-first";
import { useI18n } from "vue-i18n";
import { processDueLocalRecurringProfiles } from "@/evolu/recurringDueRunner";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { useFlashStore } from "@/store/flash";

let lastRunAt = 0;
const MIN_RUN_INTERVAL_MS = 60_000;

export function useLocalRecurringDueRunner() {
    const flash = useFlashStore();
    const { t } = useI18n();

    async function runDueProfiles(evolu: Evolu<InvoicingLocalSchema>): Promise<void> {
        if (!isInvoicingLocalFirst()) return;
        if (Date.now() - lastRunAt < MIN_RUN_INTERVAL_MS) return;

        lastRunAt = Date.now();
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
