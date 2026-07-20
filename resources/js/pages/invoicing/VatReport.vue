<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader />
    </template>

    <header class="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">{{ t("invoicing.vat_report_title") }}</h1>
        <p class="mt-1 text-sm text-gray-400">{{ t("invoicing.vat_report_subtitle") }}</p>
      </div>
      <div class="flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 text-xs text-gray-400">
          <span>{{ t("invoicing.vat_report_period") }}</span>
          <select v-model="periodPreset" class="invoicing-sf-input min-w-[180px]">
            <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
              {{ t(opt.labelKey) }}
            </option>
          </select>
        </label>
        <button
          type="button"
          class="invoicing-btn-secondary"
          :disabled="isEmpty"
          @click="exportCsv"
        >
          {{ t("invoicing.vat_report_export_csv") }}
        </button>
        <button
          type="button"
          class="invoicing-btn-secondary"
          :disabled="isEmpty"
          @click="printReport"
        >
          {{ t("invoicing.vat_report_print") }}
        </button>
      </div>
    </header>

    <div v-if="loading" class="py-16 text-center text-gray-400">
      {{ t("common.loading") }}
    </div>

    <div
      v-else-if="isEmpty"
      class="rounded-xl border border-gray-700 bg-gray-800/60 px-4 py-16 text-center text-gray-400"
    >
      {{ t("invoicing.vat_report_empty") }}
    </div>

    <div v-else class="space-y-8">
      <section
        v-for="currency in summary.byCurrency"
        :key="currency.currency"
        class="rounded-xl border border-gray-700 bg-gray-800/60 overflow-hidden"
      >
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-700 px-5 py-4">
          <h2 class="text-lg font-semibold text-white">{{ currency.currency }}</h2>
          <dl class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
            <div class="flex gap-2">
              <dt class="text-gray-400">{{ t("invoicing.vat_report_documents") }}:</dt>
              <dd class="text-white">{{ currency.documentCount }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="text-gray-400">{{ t("invoicing.vat_report_turnover") }}:</dt>
              <dd class="font-semibold text-white">{{ money(currency.turnover) }}</dd>
            </div>
          </dl>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-400">
                <th class="px-5 py-2 font-medium">{{ t("invoicing.vat_report_col_rate") }}</th>
                <th class="px-5 py-2 text-right font-medium">{{ t("invoicing.vat_report_col_base") }}</th>
                <th class="px-5 py-2 text-right font-medium">{{ t("invoicing.vat_report_col_vat") }}</th>
                <th class="px-5 py-2 text-right font-medium">{{ t("invoicing.vat_report_col_gross") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="bucket in currency.byRate"
                :key="bucket.rate"
                class="border-t border-gray-700/60 text-gray-200"
              >
                <td class="px-5 py-2">{{ bucket.rate }}%</td>
                <td class="px-5 py-2 text-right">{{ money(bucket.base) }}</td>
                <td class="px-5 py-2 text-right">{{ money(bucket.vat) }}</td>
                <td class="px-5 py-2 text-right">{{ money(bucket.gross) }}</td>
              </tr>
              <tr class="border-t border-gray-600 font-semibold text-white">
                <td class="px-5 py-2">{{ t("invoicing.vat_report_total") }}</td>
                <td class="px-5 py-2 text-right">{{ money(currency.base) }}</td>
                <td class="px-5 py-2 text-right">{{ money(currency.vat) }}</td>
                <td class="px-5 py-2 text-right">{{ money(currency.turnover) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import InvoicingPageShell from "../../components/invoicing/InvoicingPageShell.vue";
import InvoicingAppHeader from "../../components/invoicing/InvoicingAppHeader.vue";
import { useVatReport } from "../../composables/useVatReport";
import { vatSummaryCsvBlob } from "../../evolu/vatReport";
import { downloadCsvBlob } from "../../evolu/documentBulkLocal";
import type { IssuePeriodPreset } from "../../composables/useInvoicingIssuePeriod";

const { t } = useI18n();
const route = useRoute();
const companyId = computed(() => route.params.companyId as string);

const { loading, period, summary } = useVatReport(companyId);

const periodOptions: { value: IssuePeriodPreset; labelKey: string }[] = [
  { value: "this_month", labelKey: "invoicing.period_this_month" },
  { value: "last_month", labelKey: "invoicing.period_last_month" },
  { value: "this_quarter", labelKey: "invoicing.period_this_quarter" },
  { value: "last_quarter", labelKey: "invoicing.period_last_quarter" },
  { value: "this_year", labelKey: "invoicing.period_this_year" },
  { value: "last_year", labelKey: "invoicing.period_last_year" },
];

const periodPreset = computed<IssuePeriodPreset>({
  get: () => period.value.preset,
  set: (value) => {
    period.value = { ...period.value, preset: value };
  },
});

const isEmpty = computed(() => summary.value.byCurrency.length === 0);

function money(value: number): string {
  return value.toFixed(2);
}

function exportCsv(): void {
  const filename = `vat-report-${summary.value.from}_${summary.value.to}.csv`;
  downloadCsvBlob(vatSummaryCsvBlob(summary.value), filename);
}

/**
 * Print just the report by rendering a self-contained HTML document into a
 * hidden iframe - keeps the app chrome out of the PDF, works offline, and
 * leaks no global print CSS into the rest of the SPA.
 */
function printReport(): void {
  const escapeHtml = (value: string | number): string =>
    String(value).replace(/[&<>"]/g, (ch) =>
      ch === "&" ? "&amp;" : ch === "<" ? "&lt;" : ch === ">" ? "&gt;" : "&quot;",
    );
  const title = `${t("invoicing.vat_report_title")} ${summary.value.from} - ${summary.value.to}`;
  const sections = summary.value.byCurrency
    .map((currency) => {
      const rows = currency.byRate
        .map(
          (bucket) =>
            `<tr><td>${bucket.rate}%</td><td class="r">${money(bucket.base)}</td><td class="r">${money(bucket.vat)}</td><td class="r">${money(bucket.gross)}</td></tr>`,
        )
        .join("");
      return `<h2>${escapeHtml(currency.currency)}</h2><table><thead><tr><th>${escapeHtml(t("invoicing.vat_report_col_rate"))}</th><th class="r">${escapeHtml(t("invoicing.vat_report_col_base"))}</th><th class="r">${escapeHtml(t("invoicing.vat_report_col_vat"))}</th><th class="r">${escapeHtml(t("invoicing.vat_report_col_gross"))}</th></tr></thead><tbody>${rows}<tr class="total"><td>${escapeHtml(t("invoicing.vat_report_total"))}</td><td class="r">${money(currency.base)}</td><td class="r">${money(currency.vat)}</td><td class="r">${money(currency.turnover)}</td></tr></tbody></table>`;
    })
    .join("");
  const doc = `<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(title)}</title><style>body{font-family:system-ui,sans-serif;color:#111;margin:24px}h1{font-size:18px}h2{font-size:14px;margin-top:20px}table{border-collapse:collapse;width:100%;margin-top:6px}th,td{border:1px solid #ccc;padding:6px 10px;font-size:12px}.r{text-align:right}.total{font-weight:700}</style></head><body><h1>${escapeHtml(title)}</h1>${sections}</body></html>`;

  const iframe = document.createElement("iframe");
  iframe.style.position = "fixed";
  iframe.style.right = "0";
  iframe.style.bottom = "0";
  iframe.style.width = "0";
  iframe.style.height = "0";
  iframe.style.border = "0";
  document.body.appendChild(iframe);
  const frameDoc = iframe.contentWindow?.document;
  if (!frameDoc) {
    iframe.remove();
    return;
  }
  frameDoc.open();
  frameDoc.write(doc);
  frameDoc.close();
  const win = iframe.contentWindow;
  const cleanup = () => window.setTimeout(() => iframe.remove(), 500);
  if (win) {
    win.focus();
    win.onafterprint = cleanup;
    win.print();
  }
  cleanup();
}
</script>
