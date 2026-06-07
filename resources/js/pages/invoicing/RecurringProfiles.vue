<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader :company-label="companyName" :show-filter-bar="false" />
    </template>

    <template #subheader>
      <div class="invoicing-filter-bar bg-white border-b border-gray-200 sticky top-0 z-20">
        <div :class="[INVOICING_CONTAINER_CLASS, 'flex flex-wrap items-center gap-3 py-3']">
          <div class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
            <button
              v-for="f in filters"
              :key="f.id"
              type="button"
              class="invoicing-filter"
              :class="activeFilter === f.id ? 'invoicing-filter--active' : 'invoicing-filter--idle'"
              @click="setFilter(f.id)"
            >
              {{ f.label }}
            </button>
          </div>
          <RouterLink
            :to="{ name: 'invoicing-recurring-new', params: { companyId } }"
            class="invoicing-btn-primary shrink-0"
          >
            + {{ t('invoicing.new_recurring') }}
          </RouterLink>
        </div>
      </div>
    </template>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="profiles.length === 0" class="invoicing-card-pad text-center text-gray-600">
      {{ t('invoicing.no_recurring') }}
    </div>

    <div v-else class="my-8 invoicing-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="invoice-table w-full min-w-[800px] text-sm text-left">
          <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide border-b border-gray-200">
            <tr>
              <th class="w-4 px-0 py-3" aria-hidden="true"></th>
              <th class="px-4 py-3">{{ t('invoicing.recurring_col_name') }}</th>
              <th class="px-4 py-3">{{ t('invoicing.col_customer') }}</th>
              <th class="px-4 py-3 text-right">{{ t('invoicing.col_amount') }}</th>
              <th class="px-4 py-3">{{ t('invoicing.recurring_col_interval') }}</th>
              <th class="px-4 py-3">{{ t('invoicing.recurring_col_next') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="p in profiles"
              :key="p.id"
              class="border-b border-gray-100 hover:bg-gray-50 group"
            >
              <td class="px-0 py-3 relative w-4">
                <span
                  class="status-corner"
                  :class="p.is_active ? 'status-corner--paid' : 'status-corner--draft'"
                ></span>
              </td>
              <td class="px-4 py-3">
                <RouterLink
                  :to="{ name: 'invoicing-recurring-edit', params: { companyId, profileId: p.id } }"
                  class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                >
                  {{ profileLabel(p) }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-indigo-600">
                {{ p.contact?.name || '—' }}
              </td>
              <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">
                {{ formatMoney(p.total) }} {{ p.currency }}
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ intervalLabel(p.recurrence_interval) }}
              </td>
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                {{ p.next_issue_date ? formatDate(p.next_issue_date) : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-600 mt-4">{{ error }}</p>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import '../../styles/invoicing-theme.css';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import api from '../../services/api';
import { INVOICING_CONTAINER_CLASS, useInvoicingLayout } from '../../composables/useInvoicingLayout';

const { t, locale } = useI18n();
const { companyId, rememberCompany } = useInvoicingLayout();

const loading = ref(true);
const error = ref('');
const profiles = ref<any[]>([]);
const companyName = ref('');
const activeFilter = ref('all');

const filters = computed(() => [
  { id: 'all', label: t('invoicing.filter_all') },
  { id: 'active', label: t('invoicing.recurring_filter_active') },
  { id: 'inactive', label: t('invoicing.recurring_filter_inactive') },
]);

function formatMoney(n: string | number) {
  return Number(n || 0).toLocaleString(locale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(iso: string) {
  const d = new Date(`${iso.slice(0, 10)}T12:00:00`);
  return d.toLocaleDateString(locale.value);
}

function intervalLabel(interval: string) {
  const key =
    interval === 'monthly'
      ? 'invoicing.recurring_interval_monthly'
      : 'invoicing.recurring_interval_yearly';
  return t(key);
}

function profileLabel(p: { title?: string; document_type?: string }) {
  if (p.title) return p.title;
  return p.document_type === 'proforma'
    ? t('invoicing.proforma_title_prefix') + ' #INVOICE_NUMBER#'
    : t('invoicing.invoice_title_prefix') + ' #INVOICE_NUMBER#';
}

function setFilter(id: string) {
  activeFilter.value = id;
  load();
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const [companyRes, listRes] = await Promise.all([
      api.get(`/invoicing/companies/${companyId.value}`),
      api.get(`/invoicing/companies/${companyId.value}/recurring-profiles`, {
        params: { filter: activeFilter.value, per_page: 100 },
      }),
    ]);
    companyName.value = companyRes.data.data?.trade_name || companyRes.data.data?.legal_name || '';
    profiles.value = listRes.data.data ?? [];
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error');
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  rememberCompany(companyId.value);
  load();
});
</script>
