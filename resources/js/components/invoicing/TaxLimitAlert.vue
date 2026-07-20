<template>
  <div
    v-if="progress"
    class="rounded-lg border px-4 py-3"
    :class="
      progress.level === 'approaching'
        ? 'border-amber-300 bg-amber-50 text-amber-900'
        : 'border-red-300 bg-red-50 text-red-900'
    "
    role="status"
  >
    <p class="text-sm font-semibold">
      {{ t("invoicing.vat_limit_alert_title") }} - {{ progress.percent }}%
    </p>
    <p class="mt-1 text-sm">
      {{
        progress.level === "exceeded"
          ? t("invoicing.vat_limit_alert_exceeded", {
              turnover: money(progress.turnover),
              limit: money(progress.limit),
              currency,
            })
          : t("invoicing.vat_limit_alert_body", {
              turnover: money(progress.turnover),
              limit: money(progress.limit),
              currency,
              percent: progress.percent,
            })
      }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, toRef } from "vue";
import { useI18n } from "vue-i18n";
import { useVatReport } from "../../composables/useVatReport";
import { turnoverForCurrency, vatLimitProgress } from "../../evolu/vatReport";

const props = defineProps<{
  companyId: string;
  /** Annual VAT-registration turnover limit; <=0 disables the alert. */
  limit: number;
  /** Currency the limit is expressed in (company default). */
  currency: string;
}>();

const { t, locale } = useI18n();

// this-year net turnover for the company (useVatReport defaults to this_year).
const { summary } = useVatReport(toRef(props, "companyId"));

const progress = computed(() => {
  if (!(props.limit > 0)) return null;
  const turnover = turnoverForCurrency(summary.value, props.currency);
  const result = vatLimitProgress(turnover, props.limit);
  // Only surface once the user is actually approaching the threshold.
  return result && result.level !== "ok" ? result : null;
});

function money(value: number): string {
  return value.toLocaleString(locale.value, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>
