<template>
  <div class="space-y-6">
    <div
      class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden"
    >
      <div
        class="px-6 py-5 border-b border-gray-700 bg-gray-800/50 flex flex-col md:flex-row md:items-center justify-between gap-4"
      >
        <div>
          <h1 class="text-2xl font-bold text-white">
            {{ t("stores.cashu_payments_title") }}
          </h1>
          <p class="text-sm text-gray-400 mt-1">
            {{ t("stores.cashu_payments_subtitle") }}
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:pointer-events-none transition-colors shadow-lg shadow-indigo-900/20 shrink-0"
          :disabled="loading"
          @click="fetchPayments()"
        >
          {{ t("stores.cashu_payments_refresh") }}
        </button>
      </div>

      <div class="px-6 py-5">
        <div
          class="mb-6 flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-4"
        >
          <div class="flex flex-col gap-1.5 sm:min-w-[12rem]">
            <label
              class="block text-xs font-medium text-gray-400 mb-1 uppercase tracking-wider"
              for="cashu-settlement-filter"
            >
              {{ t("stores.cashu_settlement_filter") }}
            </label>
            <Select
              id="cashu-settlement-filter"
              v-model="settlementState"
              :options="settlementStateOptions"
              :placeholder="t('stores.cashu_filter_all')"
              :disabled="loading"
              @change="onSettlementFilterChange"
            />
          </div>
        </div>

        <div
          v-if="loading && payments.items.length === 0"
          class="text-center py-16"
        >
          <svg
            class="animate-spin mx-auto h-10 w-10 text-indigo-500 mb-4"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            />
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
          <p class="text-gray-400 text-sm">
            {{ t("stores.cashu_payments_loading") }}
          </p>
        </div>

        <div
          v-else-if="error"
          class="rounded-xl border border-red-500/25 bg-red-500/10 p-4 mb-4"
        >
          <div class="flex items-start gap-3">
            <svg
              class="h-5 w-5 text-red-400 flex-shrink-0 mt-0.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p class="text-sm text-red-200 leading-relaxed">{{ error }}</p>
          </div>
        </div>

        <template v-else>
          <div class="overflow-x-auto rounded-xl border border-gray-700">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
              <thead class="bg-gray-700/50">
                <tr>
                  <th
                    class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                  >
                    {{ t("stores.cashu_col_created") }}
                  </th>
                  <th
                    class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                  >
                    {{ t("stores.cashu_col_amount") }}
                  </th>
                  <th
                    class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                  >
                    {{ t("stores.cashu_col_settlement") }}
                  </th>
                  <th
                    class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                  >
                    {{ t("stores.cashu_col_error") }}
                  </th>
                  <th
                    class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
                  >
                    {{ t("stores.cashu_col_action") }}
                  </th>
                </tr>
              </thead>
              <tbody class="bg-gray-800 divide-y divide-gray-700">
                <tr
                  v-for="p in payments.items"
                  :key="p.quote_id"
                  class="hover:bg-gray-800/60 transition-colors"
                >
                  <td
                    class="px-4 py-3 text-gray-300 whitespace-nowrap tabular-nums"
                  >
                    {{ formatDate(p.created_at) }}
                  </td>
                  <td
                    class="px-4 py-3 text-white font-medium whitespace-nowrap tabular-nums"
                  >
                    {{ formatAmount(p.amount_sats) }} sat
                  </td>
                  <td class="px-4 py-3">
                    <span :class="settlementPillClass(p.settlement_state)">
                      {{ p.settlement_state || "-" }}
                    </span>
                  </td>
                  <td
                    class="px-4 py-3 break-all max-w-xs"
                    :class="
                      p.settlement_error
                        ? 'text-red-300/90 text-sm'
                        : 'text-gray-500 text-sm'
                    "
                  >
                    {{ p.settlement_error || "-" }}
                  </td>
                  <td class="px-4 py-3">
                    <button
                      v-if="canRetry(p)"
                      type="button"
                      class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-red-500/35 bg-red-500/10 text-red-300 hover:bg-red-500/20 disabled:opacity-50 transition-colors"
                      :disabled="retryingQuoteIds.has(p.quote_id)"
                      @click="retryPayment(p.quote_id)"
                    >
                      {{
                        retryingQuoteIds.has(p.quote_id)
                          ? t("stores.cashu_payments_retrying")
                          : t("stores.cashu_payments_retry")
                      }}
                    </button>
                    <span v-else class="text-gray-600 text-sm">-</span>
                  </td>
                </tr>
                <tr v-if="payments.items.length === 0">
                  <td
                    colspan="5"
                    class="px-4 py-14 text-center text-gray-500 text-sm"
                  >
                    {{ t("stores.cashu_payments_empty") }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 mt-6 pt-4 border-t border-gray-700"
          >
            <button
              type="button"
              class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white disabled:opacity-40 disabled:pointer-events-none transition-colors"
              :disabled="offset <= 0 || loading"
              @click="changeOffset(-limit)"
            >
              {{ t("common.previous") }}
            </button>
            <div
              class="text-sm text-gray-500 text-center order-first sm:order-none"
            >
              {{
                t("stores.cashu_payments_showing", {
                  start: payments.total === 0 ? 0 : offset + 1,
                  end: Math.min(offset + limit, payments.total),
                  total: payments.total,
                })
              }}
            </div>
            <button
              type="button"
              class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white disabled:opacity-40 disabled:pointer-events-none transition-colors"
              :disabled="offset + limit >= payments.total || loading"
              @click="changeOffset(limit)"
            >
              {{ t("common.next") }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import api from "../../services/api";
import Select from "../ui/Select.vue";

const { t } = useI18n();

const props = defineProps<{
  store: { id: string };
}>();

type SettlementState = "SETTLED" | "PENDING" | "FAILED";

interface CashuPayment {
  quote_id: string;
  created_at?: string | null;
  amount_sats?: number | null;
  settlement_state?: SettlementState | string | null;
  settlement_error?: string | null;
}

interface CashuPaymentsResponse {
  total: number;
  offset: number;
  limit: number;
  items: CashuPayment[];
}

const limit = 50;
const offset = ref(0);
const settlementState = ref<string>("");

const settlementStateOptions = computed(() => [
  { label: t("stores.cashu_filter_all"), value: "" },
  { label: "SETTLED", value: "SETTLED" },
  { label: "PENDING", value: "PENDING" },
  { label: "FAILED", value: "FAILED" },
]);

const payments = ref<CashuPaymentsResponse>({
  total: 0,
  offset: 0,
  limit,
  items: [],
});

const loading = ref(false);
const error = ref<string | null>(null);
const retryingQuoteIds = ref<Set<string>>(new Set());

function storeId(): string {
  return props.store.id;
}

function formatDate(dateString?: string | null): string {
  if (!dateString) return "-";
  try {
    return new Date(dateString).toLocaleString(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    });
  } catch {
    return dateString;
  }
}

function formatAmount(amountSats?: number | null): string {
  if (amountSats == null) return "-";
  const n = typeof amountSats === "string" ? Number(amountSats) : amountSats;
  if (!Number.isFinite(n)) return String(amountSats);
  return Math.round(n).toLocaleString();
}

function settlementPillClass(state?: string | null): string {
  const base =
    "inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wide border";
  switch (state) {
    case "SETTLED":
      return `${base} bg-emerald-500/15 text-emerald-300 border-emerald-500/30`;
    case "PENDING":
      return `${base} bg-gray-700/90 text-gray-300 border-gray-600`;
    case "FAILED":
      return `${base} bg-red-500/15 text-red-300 border-red-500/35`;
    default:
      return `${base} bg-gray-700/90 text-gray-400 border-gray-600`;
  }
}

function canRetry(p: CashuPayment): boolean {
  if (!p.quote_id) return false;
  if (p.settlement_state !== "FAILED") return false;
  const err = (p.settlement_error ?? "").toString();
  if (err.toLowerCase().includes("proofs are not available")) return false;
  return true;
}

async function fetchPayments() {
  loading.value = true;
  error.value = null;
  try {
    const params: Record<string, unknown> = {
      limit,
      offset: offset.value,
    };
    if (settlementState.value) {
      params.settlementState = settlementState.value;
    }

    const response = await api.get(`/stores/${storeId()}/cashu/payments`, {
      params,
    });
    const d = response.data?.data ?? {};

    payments.value = {
      total: Number(d.total ?? 0),
      offset: Number(d.offset ?? offset.value),
      limit: Number(d.limit ?? limit),
      items: (d.items ?? []) as CashuPayment[],
    };
  } catch (err: unknown) {
    const e = err as {
      response?: { data?: { message?: string } };
      message?: string;
    };
    error.value =
      e.response?.data?.message ||
      e.message ||
      t("stores.cashu_payments_load_error");
  } finally {
    loading.value = false;
  }
}

async function retryPayment(quoteId: string) {
  if (!quoteId) return;

  retryingQuoteIds.value.add(quoteId);
  retryingQuoteIds.value = new Set(retryingQuoteIds.value);
  try {
    await api.post(`/stores/${storeId()}/cashu/payments/${quoteId}/retry`);
    await fetchPayments();
  } catch (err: unknown) {
    const e = err as {
      response?: { data?: { message?: string } };
      message?: string;
    };
    error.value =
      e.response?.data?.message ||
      e.message ||
      t("stores.cashu_payments_retry_error");
  } finally {
    retryingQuoteIds.value.delete(quoteId);
    retryingQuoteIds.value = new Set(retryingQuoteIds.value);
  }
}

function changeOffset(delta: number) {
  offset.value = Math.max(0, offset.value + delta);
  fetchPayments();
}

function onSettlementFilterChange() {
  offset.value = 0;
  fetchPayments();
}

watch(
  () => props.store.id,
  () => {
    offset.value = 0;
    settlementState.value = "";
    fetchPayments();
  },
);

onMounted(async () => {
  await fetchPayments();
});
</script>
