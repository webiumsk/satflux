<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
      <router-link
        :to="`/stores/${storeId}`"
        class="text-indigo-600 hover:text-indigo-500 text-sm"
      >
        ← Back to Store
      </router-link>
    </div>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">Cashu Payments</h1>

    <div class="flex flex-col md:flex-row gap-3 items-start md:items-center mb-6">
      <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-gray-700">
          Settlement state
        </label>
        <select
          v-model="settlementState"
          class="rounded-lg border border-gray-300 bg-white text-gray-900 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        >
          <option value="">All</option>
          <option value="SETTLED">SETTLED</option>
          <option value="PENDING">PENDING</option>
          <option value="FAILED">FAILED</option>
        </select>
      </div>

      <div class="ml-auto flex items-center gap-3">
        <button
          type="button"
          @click="fetchPayments()"
          :disabled="loading"
          class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          Refresh
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12">
      <svg class="animate-spin h-10 w-10 text-indigo-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="text-gray-600">Loading payments...</p>
    </div>

    <div v-else-if="error" class="bg-red-500/10 border border-red-500/20 rounded-xl p-6 mb-6 text-red-700">
      {{ error }}
    </div>

    <div v-else class="bg-white shadow rounded-lg p-6">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600 border-b">
              <th class="py-3 px-3">Created</th>
              <th class="py-3 px-3">Amount</th>
              <th class="py-3 px-3">Settlement</th>
              <th class="py-3 px-3">Error</th>
              <th class="py-3 px-3">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="p in payments.items"
              :key="p.quote_id"
              class="border-b last:border-b-0"
            >
              <td class="py-3 px-3 text-gray-700 whitespace-nowrap">
                {{ formatDate(p.created_at) }}
              </td>
              <td class="py-3 px-3 text-gray-900 font-medium whitespace-nowrap">
                {{ formatAmount(p.amount_sats) }} sat
              </td>
              <td class="py-3 px-3">
                <span :class="settlementPillClass(p.settlement_state)">
                  {{ p.settlement_state || '—' }}
                </span>
              </td>
              <td class="py-3 px-3 text-gray-600 break-all max-w-xs">
                {{ p.settlement_error || '—' }}
              </td>
              <td class="py-3 px-3">
                <button
                  v-if="canRetry(p)"
                  type="button"
                  @click="retryPayment(p.quote_id)"
                  :disabled="retryingQuoteIds.has(p.quote_id)"
                  class="px-3 py-2 rounded-lg border border-red-500/30 bg-red-500/10 text-red-700 hover:bg-red-500/15 disabled:opacity-50"
                >
                  {{ retryingQuoteIds.has(p.quote_id) ? 'Retrying...' : 'Retry' }}
                </button>
                <span v-else class="text-gray-400">—</span>
              </td>
            </tr>

            <tr v-if="payments.items.length === 0">
              <td colspan="5" class="py-8 text-center text-gray-500">
                No payments found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between mt-6 gap-4">
        <button
          type="button"
          @click="changeOffset(-limit)"
          :disabled="offset <= 0"
          class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          Previous
        </button>

        <div class="text-sm text-gray-600">
          Showing
          {{ payments.total === 0 ? 0 : offset + 1 }} -
          {{ Math.min(offset + limit, payments.total) }}
          of {{ payments.total }}
        </div>

        <button
          type="button"
          @click="changeOffset(limit)"
          :disabled="offset + limit >= payments.total"
          class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../services/api';

const route = useRoute();
const storeId = route.params.id as string;

type SettlementState = 'SETTLED' | 'PENDING' | 'FAILED';

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
const settlementState = ref<string>('');

const payments = ref<CashuPaymentsResponse>({
  total: 0,
  offset: 0,
  limit,
  items: [],
});

const loading = ref(false);
const error = ref<string | null>(null);
const retryingQuoteIds = ref<Set<string>>(new Set());

function formatDate(dateString?: string | null): string {
  if (!dateString) return '—';
  try {
    return new Date(dateString).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
  } catch {
    return dateString;
  }
}

function formatAmount(amountSats?: number | null): string {
  if (amountSats == null) return '—';
  const n = typeof amountSats === 'string' ? Number(amountSats) : amountSats;
  if (!Number.isFinite(n)) return String(amountSats);
  return Math.round(n).toLocaleString();
}

function settlementPillClass(state?: string | null): string {
  switch (state) {
    case 'SETTLED':
      return 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700';
    case 'PENDING':
      return 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700';
    case 'FAILED':
      return 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700';
    default:
      return 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700';
  }
}

function canRetry(p: CashuPayment): boolean {
  if (!p.quote_id) return false;
  if (p.settlement_state !== 'FAILED') return false;
  const err = (p.settlement_error ?? '').toString();
  // BTCPay returns proofs-related errors when retry isn't possible.
  if (err.toLowerCase().includes('proofs are not available')) return false;
  return true;
}

async function fetchPayments() {
  loading.value = true;
  error.value = null;
  try {
    const params: Record<string, any> = {
      limit,
      offset: offset.value,
    };
    if (settlementState.value) {
      params.settlementState = settlementState.value;
    }

    const response = await api.get(`/stores/${storeId}/cashu/payments`, { params });
    const d = response.data?.data ?? {};

    payments.value = {
      total: Number(d.total ?? 0),
      offset: Number(d.offset ?? offset.value),
      limit: Number(d.limit ?? limit),
      items: (d.items ?? []) as CashuPayment[],
    };
  } catch (err: any) {
    error.value = err.response?.data?.message || err.message || 'Failed to load Cashu payments';
  } finally {
    loading.value = false;
  }
}

async function retryPayment(quoteId: string) {
  if (!quoteId) return;

  retryingQuoteIds.value.add(quoteId);
  // Re-assign to trigger Vue reactivity.
  retryingQuoteIds.value = new Set(retryingQuoteIds.value);
  try {
    await api.post(`/stores/${storeId}/cashu/payments/${quoteId}/retry`);
    await fetchPayments();
  } catch (err: any) {
    error.value = err.response?.data?.message || err.message || 'Retry failed';
  } finally {
    retryingQuoteIds.value.delete(quoteId);
    retryingQuoteIds.value = new Set(retryingQuoteIds.value);
  }
}

function changeOffset(delta: number) {
  offset.value = Math.max(0, offset.value + delta);
  fetchPayments();
}

// When filters change, reset pagination.
watch(settlementState, () => {
  offset.value = 0;
  fetchPayments();
});
onMounted(async () => {
  await fetchPayments();
});
</script>

