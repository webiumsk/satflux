<template>
  <InvoicingPageShell>
    <template #header>
      <InvoicingAppHeader :show-filter-bar="false" />
    </template>
    <template #toolbar>
      <RouterLink :to="expenseListTo()" class="invoicing-back mb-0">
        ← {{ t('invoicing.main_nav_expenses') }}
      </RouterLink>
    </template>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <template v-else-if="expense">
      <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
        <div>
          <h1 class="invoicing-title">{{ expense.internal_number }}</h1>
          <p v-if="expense.title" class="text-gray-600 mt-1">{{ expense.title }}</p>
          <p class="text-sm mt-1" :class="statusClass(expense.status)">{{ statusLabel(expense.status) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <RouterLink
            v-if="expense.status !== 'cancelled'"
            :to="expenseEditTo(expense.id)"
            class="invoicing-btn-secondary text-sm"
          >
            {{ t('invoicing.action_edit') }}
          </RouterLink>
          <button type="button" class="invoicing-btn-secondary text-sm" :disabled="duplicating" @click="duplicate">
            {{ t('invoicing.expense_action_duplicate') }}
          </button>
          <button
            v-if="expense.status === 'recorded'"
            type="button"
            class="invoicing-btn-primary text-sm"
            :disabled="acting"
            @click="markPaid"
          >
            {{ t('invoicing.bulk_mark_paid') }}
          </button>
          <button
            v-if="expense.status === 'paid'"
            type="button"
            class="invoicing-btn-secondary text-sm"
            :disabled="acting"
            @click="unmarkPaid"
          >
            {{ t('invoicing.expense_action_unmark_paid') }}
          </button>
          <button
            v-if="expense.status !== 'cancelled'"
            type="button"
            class="invoicing-btn-secondary text-sm text-red-700"
            :disabled="acting"
            @click="cancelExpense"
          >
            {{ t('invoicing.expense_action_cancel') }}
          </button>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-6">
        <div class="invoicing-card-pad space-y-3 text-sm">
          <div class="flex justify-between gap-4">
            <span class="text-gray-500">{{ t('invoicing.expense_col_external') }}</span>
            <span>{{ expense.external_number || '—' }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-gray-500">{{ t('invoicing.expense_col_total') }}</span>
            <span class="font-semibold">{{ formatMoney(expense.total, expense.currency) }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-gray-500">{{ t('invoicing.expense_col_issue') }}</span>
            <span>{{ formatDate(expense.issue_date) }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-gray-500">{{ t('invoicing.expense_col_delivery') }}</span>
            <span>{{ expense.delivery_date ? formatDate(expense.delivery_date) : '—' }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-gray-500">{{ t('invoicing.expense_col_due') }}</span>
            <span :class="isOverdue ? 'text-red-600 font-medium' : ''">
              {{ expense.due_date ? formatDate(expense.due_date) : '—' }}
            </span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-gray-500">{{ t('invoicing.variable_symbol') }}</span>
            <span>{{ expense.variable_symbol || '—' }}</span>
          </div>
          <div v-if="expense.internal_note" class="pt-2 border-t">
            <span class="text-gray-500 block mb-1">{{ t('invoicing.expense_internal_note') }}</span>
            <p class="whitespace-pre-wrap">{{ expense.internal_note }}</p>
          </div>
        </div>

        <div class="invoicing-card-pad space-y-3">
          <h2 class="font-medium text-gray-900">{{ t('invoicing.expense_attachment') }}</h2>
          <p v-if="expense.original_filename" class="text-sm text-gray-600">{{ expense.original_filename }}</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="expense.attachment_path"
              type="button"
              class="invoicing-btn-secondary text-sm"
              @click="openAttachment"
            >
              {{ t('invoicing.expense_attachment_view') }}
            </button>
            <label v-if="expense.status !== 'cancelled'" class="invoicing-btn-secondary text-sm cursor-pointer">
              {{ uploading ? t('common.loading') : t('invoicing.expense_attachment_upload') }}
              <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp,.xml,.isdoc" @change="onFile" />
            </label>
          </div>
        </div>
      </div>

      <div v-if="history.length" class="invoicing-card-pad mt-6">
        <h2 class="font-medium mb-3">{{ t('invoicing.tab_history') }}</h2>
        <ul class="text-sm space-y-2">
          <li v-for="h in history" :key="h.id" class="flex justify-between gap-2 text-gray-600">
            <span>{{ h.action }}</span>
            <span>{{ formatDateTime(h.created_at) }}</span>
          </li>
        </ul>
      </div>
    </template>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import api, { getWebBlob } from '../../services/api';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';

type Expense = {
  id: string;
  internal_number: string;
  external_number: string | null;
  title: string | null;
  status: string;
  issue_date: string;
  delivery_date: string | null;
  due_date: string | null;
  total: string;
  currency: string;
  variable_symbol: string | null;
  internal_note: string | null;
  attachment_path: string | null;
  original_filename: string | null;
};

type HistoryRow = { id: string; action: string; created_at: string };

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { companyId, rememberCompany } = useInvoicingLayout();
const expenseId = computed(() => route.params.expenseId as string);

const expense = ref<Expense | null>(null);
const history = ref<HistoryRow[]>([]);
const loading = ref(true);
const acting = ref(false);
const duplicating = ref(false);
const uploading = ref(false);

const isOverdue = computed(() => {
  if (!expense.value || expense.value.status !== 'recorded' || !expense.value.due_date) return false;
  return expense.value.due_date.slice(0, 10) < new Date().toISOString().slice(0, 10);
});

function expenseListTo() {
  return { name: 'invoicing-expenses', params: { companyId: companyId.value } };
}

function expenseEditTo(id: string) {
  return { name: 'invoicing-expense-edit', params: { companyId: companyId.value, expenseId: id } };
}

function formatMoney(amount: string | number, currency: string) {
  const n = typeof amount === 'string' ? parseFloat(amount) : amount;
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'EUR' }).format(n || 0);
}

function formatDate(iso: string) {
  const d = (iso || '').slice(0, 10);
  const [y, m, day] = d.split('-');
  return day && m && y ? `${day}.${m}.${y}` : '—';
}

function formatDateTime(iso: string) {
  return new Date(iso).toLocaleString();
}

function statusLabel(status: string) {
  if (status === 'paid') return t('invoicing.adv_paid');
  if (status === 'cancelled') return t('invoicing.expense_status_cancelled');
  return t('invoicing.expense_filter_unpaid');
}

function statusClass(status: string) {
  if (status === 'paid') return 'text-green-700';
  if (status === 'cancelled') return 'text-gray-500';
  return 'text-amber-700';
}

async function load() {
  loading.value = true;
  try {
    const [expRes, histRes] = await Promise.all([
      api.get(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`),
      api.get(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/history`),
    ]);
    expense.value = expRes.data.data;
    history.value = histRes.data.data ?? [];
  } finally {
    loading.value = false;
  }
}

async function duplicate() {
  duplicating.value = true;
  try {
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/duplicate`,
    );
    await router.push({
      name: 'invoicing-expense-edit',
      params: { companyId: companyId.value, expenseId: res.data.data.id },
    });
  } finally {
    duplicating.value = false;
  }
}

async function markPaid() {
  acting.value = true;
  try {
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/mark-paid`,
    );
    expense.value = res.data.data;
  } finally {
    acting.value = false;
  }
}

async function unmarkPaid() {
  acting.value = true;
  try {
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/unmark-paid`,
    );
    expense.value = res.data.data;
  } finally {
    acting.value = false;
  }
}

async function cancelExpense() {
  if (!confirm(t('invoicing.expense_cancel_confirm'))) return;
  acting.value = true;
  try {
    await api.delete(`/invoicing/companies/${companyId.value}/expenses/${expenseId.value}`);
    await router.push(expenseListTo());
  } finally {
    acting.value = false;
  }
}

async function onFile(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  uploading.value = true;
  const fd = new FormData();
  fd.append('file', file);
  try {
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/attachment`,
      fd,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    );
    expense.value = res.data.data;
  } finally {
    uploading.value = false;
    input.value = '';
  }
}

async function openAttachment() {
  const path = `/api/invoicing/companies/${companyId.value}/expenses/${expenseId.value}/attachment`;
  const blob = await getWebBlob(path);
  const url = URL.createObjectURL(blob);
  window.open(url, '_blank', 'noopener');
}

onMounted(() => {
  rememberCompany(companyId.value);
  load();
});
</script>
