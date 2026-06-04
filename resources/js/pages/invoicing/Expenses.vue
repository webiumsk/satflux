<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader :company-label="companyName" :show-filter-bar="true">
        <template #filters>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="f in statusFilters"
              :key="f.id"
              type="button"
              class="invoicing-filter"
              :class="activeFilter === f.id ? 'invoicing-filter--active' : 'invoicing-filter--idle'"
              @click="setFilter(f.id)"
            >
              {{ t(f.labelKey) }}
            </button>
            <select v-model="year" class="invoicing-sf-input max-w-[120px]" @change="load">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
        </template>
        <template #actions>
          <RouterLink :to="expenseNewTo()" class="invoicing-btn-primary shrink-0">
            + {{ t('invoicing.expenses_new') }}
          </RouterLink>
        </template>
      </InvoicingAppHeader>
    </template>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="expenses.length === 0" class="invoicing-card-pad text-center text-gray-600">
      {{ t('invoicing.expenses_empty') }}
    </div>

    <div v-else class="invoicing-card overflow-hidden my-6">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b text-xs uppercase text-gray-600">
            <tr>
              <th class="text-left p-3">{{ t('invoicing.expense_col_internal') }}</th>
              <th class="text-left p-3">{{ t('invoicing.expense_col_external') }}</th>
              <th class="text-left p-3">{{ t('invoicing.expense_col_title') }}</th>
              <th class="text-left p-3">{{ t('invoicing.expense_col_issue') }}</th>
              <th class="text-left p-3">{{ t('invoicing.expense_col_due') }}</th>
              <th class="text-right p-3">{{ t('invoicing.expense_col_total') }}</th>
              <th class="text-left p-3">{{ t('invoicing.expense_col_status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="e in expenses"
              :key="e.id"
              class="border-b hover:bg-gray-50 cursor-pointer"
              @click="goShow(e.id)"
            >
              <td class="p-3 font-medium text-indigo-700">{{ e.internal_number }}</td>
              <td class="p-3">{{ e.external_number || '—' }}</td>
              <td class="p-3 max-w-[200px] truncate">{{ e.title || '—' }}</td>
              <td class="p-3 whitespace-nowrap">{{ formatDate(e.issue_date) }}</td>
              <td class="p-3 whitespace-nowrap" :class="e.is_overdue ? 'text-red-600' : ''">
                {{ e.due_date ? formatDate(e.due_date) : '—' }}
              </td>
              <td class="p-3 text-right font-medium">{{ formatMoney(e.total, e.currency) }}</td>
              <td class="p-3">
                <span :class="statusClass(e.status)">{{ statusLabel(e.status) }}</span>
                <span v-if="e.has_attachment" class="ml-1 text-gray-400" title="PDF">📎</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="lastPage > 1" class="flex justify-center gap-2 p-3 border-t text-sm">
        <button type="button" class="invoicing-btn-secondary py-1" :disabled="page <= 1" @click="changePage(page - 1)">
          ‹
        </button>
        <span class="text-gray-600">{{ page }} / {{ lastPage }}</span>
        <button type="button" class="invoicing-btn-secondary py-1" :disabled="page >= lastPage" @click="changePage(page + 1)">
          ›
        </button>
      </div>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import api from '../../services/api';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';

type ExpenseRow = {
  id: string;
  internal_number: string;
  external_number: string | null;
  title: string | null;
  issue_date: string;
  due_date: string | null;
  total: string;
  currency: string;
  status: string;
  is_overdue?: boolean;
  has_attachment?: boolean;
};

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { rememberCompany } = useInvoicingLayout();
const companyId = computed(() => route.params.companyId as string);
const companyName = ref('');
const loading = ref(true);
const expenses = ref<ExpenseRow[]>([]);
const page = ref(1);
const lastPage = ref(1);
const activeFilter = ref('all');
const year = ref(new Date().getFullYear());

const statusFilters = [
  { id: 'all', labelKey: 'invoicing.adv_all' },
  { id: 'unpaid', labelKey: 'invoicing.expense_filter_unpaid' },
  { id: 'paid', labelKey: 'invoicing.adv_paid' },
  { id: 'overdue', labelKey: 'invoicing.expense_filter_overdue' },
];

const yearOptions = computed(() => {
  const current = new Date().getFullYear();
  return [current, current - 1, current - 2];
});

function expenseNewTo() {
  return { name: 'invoicing-expense-new', params: { companyId: companyId.value } };
}

function goShow(expenseId: string) {
  router.push({ name: 'invoicing-expense-show', params: { companyId: companyId.value, expenseId } });
}

function setFilter(id: string) {
  activeFilter.value = id;
  page.value = 1;
  load();
}

function changePage(p: number) {
  page.value = p;
  load();
}

function formatMoney(amount: string | number, currency: string) {
  const n = typeof amount === 'string' ? parseFloat(amount) : amount;
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'EUR' }).format(n || 0);
}

function formatDate(iso: string) {
  if (!iso) return '—';
  const d = iso.slice(0, 10);
  const [y, m, day] = d.split('-');
  return `${day}.${m}.${y}`;
}

function statusLabel(status: string) {
  if (status === 'paid') return t('invoicing.adv_paid');
  if (status === 'cancelled') return t('invoicing.expense_status_cancelled');
  return t('invoicing.expense_filter_unpaid');
}

function statusClass(status: string) {
  if (status === 'paid') return 'text-green-700';
  if (status === 'recorded') return 'text-gray-700';
  return 'text-amber-700';
}

async function load() {
  loading.value = true;
  try {
    const [companyRes, listRes] = await Promise.all([
      api.get(`/invoicing/companies/${companyId.value}`),
      api.get(`/invoicing/companies/${companyId.value}/expenses`, {
        params: {
          filter: activeFilter.value === 'all' ? undefined : activeFilter.value,
          year: year.value,
          page: page.value,
        },
      }),
    ]);
    companyName.value = companyRes.data.data?.trade_name || companyRes.data.data?.legal_name || '';
    const rows = listRes.data.data ?? [];
    expenses.value = rows.map((e: ExpenseRow & { due_date?: string; status: string }) => ({
      ...e,
      is_overdue: e.status === 'recorded' && e.due_date && e.due_date < new Date().toISOString().slice(0, 10),
      has_attachment: Boolean((e as { attachment_path?: string }).attachment_path),
    }));
    lastPage.value = listRes.data.last_page ?? 1;
    page.value = listRes.data.current_page ?? 1;
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  rememberCompany(companyId.value);
  load();
});
</script>
