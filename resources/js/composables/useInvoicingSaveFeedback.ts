import { useI18n } from "vue-i18n";
import { useFlashStore } from "@/store/flash";

/** Toast feedback after invoicing settings and inline saves. */
export function useInvoicingSaveFeedback() {
    const flash = useFlashStore();
    const { t } = useI18n();

    function notifySaved(messageKey = "invoicing.settings_saved") {
        flash.success(t(messageKey));
    }

    function notifySaveFailed(message?: string) {
        flash.error(message ?? t("common.error_generic"));
    }

    return { notifySaved, notifySaveFailed };
}
