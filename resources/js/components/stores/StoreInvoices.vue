<template>
  <div v-if="loading && invoices.length === 0" class="text-center py-12">
    <svg class="animate-spin mx-auto h-10 w-10 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <p class="text-gray-400 mt-4">{{ t('stores.loading_invoices') }}</p>
  </div>

  <div v-else class="space-y-6">
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700">
      <div class="px-6 py-5 border-b border-gray-700 bg-gray-800/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
           <h1 class="text-2xl font-bold text-white">{{ t('stores.invoices') }}</h1>
           <p class="text-sm text-gray-400 mt-1">{{ t('stores.view_manage_transactions') }}</p>
        </div>
        
        <div class="flex items-center gap-3 flex-wrap">
             <button
              @click="handleExportInvoices('csv')"
              :disabled="exportingInvoices"
              class="inline-flex items-center px-4 py-2 border border-gray-600 shadow-sm text-sm font-medium rounded-lg text-white bg-gray-700 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <svg v-if="!exportingInvoices" class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              <svg v-else class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ exportingInvoices ? t('stores.exporting') : t('stores.export_to_csv') }}
            </button>
             <button
              v-if="canUseXlsx"
              @click="handleExportInvoices('xlsx')"
              :disabled="exportingInvoices"
              class="inline-flex items-center px-4 py-2 border border-gray-600 shadow-sm text-sm font-medium rounded-lg text-white bg-gray-700 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <svg v-if="!exportingInvoices" class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              <svg v-else class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ exportingInvoices ? t('stores.exporting') : t('stores.export_to_xlsx') }}
            </button>
             <button
              v-else
              @click="showXlsxUpgradeModal = true"
              class="inline-flex items-center px-4 py-2 border border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-500 bg-gray-800 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 transition-all"
              :title="t('stores.pro_feature_tooltip')"
            >
              <svg class="w-4 h-4 mr-2 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
              {{ t('stores.export_to_xlsx') }}
              <span class="ml-2 text-xs px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 inline-flex items-center"><ProPlanBadge /></span>
            </button>
             
             <!-- Create Invoice Button (Future implementation) -->
             <!-- <button class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Create Invoice
             </button> -->
        </div>
      </div>

      <div class="px-6 py-5">
        <!-- Filters -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Status Filter -->
          <div class="md:col-span-1">
            <label for="invoice-status-filter" class="block text-xs font-medium text-gray-400 mb-1 uppercase tracking-wider">
              {{ t('stores.status') }}
            </label>
            <Select
              id="invoice-status-filter"
              v-model="filters.status"
              :options="statusOptions"
              @change="fetchInvoices"
              :placeholder="t('stores.all_statuses')"
            />
          </div>

          <!-- Date From -->
          <div class="md:col-span-1">
            <label for="invoice-date-from" class="block text-xs font-medium text-gray-400 mb-1 uppercase tracking-wider">
              {{ t('stores.from_date') }}
            </label>
            <DatePicker
              id="invoice-date-from"
              v-model="filters.date_from"
              @change="fetchInvoices"
              :placeholder="t('stores.from_date')"
            />
          </div>

          <!-- Date To -->
          <div class="md:col-span-1">
            <label for="invoice-date-to" class="block text-xs font-medium text-gray-400 mb-1 uppercase tracking-wider">
              {{ t('stores.to_date') }}
            </label>
            <DatePicker
              id="invoice-date-to"
              v-model="filters.date_to"
              @change="fetchInvoices"
              :placeholder="t('stores.to_date')"
              position="right"
            />
          </div>
          
           <!-- Filter Actions -->
           <div class="md:col-span-1 flex items-end">
               <button
                  v-if="hasFilters"
                  @click="clearFilters"
                  class="w-full py-2 px-4 border border-gray-600 rounded-lg text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors"
                >
                  {{ t('stores.clear_filters') }}
                </button>
           </div>
        </div>


        <div v-if="invoices.length === 0 && !loading" class="text-center py-16 bg-gray-700/30 rounded-xl border border-dashed border-gray-600">
          <svg class="mx-auto h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-white">{{ t('stores.no_invoices_found') }}</h3>
          <p class="mt-1 text-sm text-gray-400">{{ t('stores.adjust_filters_create_invoice') }}</p>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-gray-700">
          <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-700/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">{{ t('stores.invoice_id') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">{{ t('stores.date') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">{{ t('stores.status') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">{{ t('stores.amount') }}</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider"></th>
              </tr>
            </thead>
            <tbody class="bg-gray-800 divide-y divide-gray-700">
              <template v-for="invoice in invoices" :key="invoice.id">
                <tr 
                  class="hover:bg-gray-700/50 transition-colors cursor-pointer" 
                  @click="toggleExpand(invoice.id)"
                  :class="{ 'bg-gray-700/30': expandedInvoiceId === invoice.id }"
                >
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-400 font-mono">
                    {{ (invoice.invoice_id || invoice.id).substring(0, 8) }}...
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    {{ formatDate(invoice.created_time || invoice.created_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full border"
                      :class="getStatusClass(invoice.status)">
                      {{ invoice.status }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-medium">
                    {{ formatAmount(invoice.amount, invoice.currency) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                    <svg 
                      class="h-5 w-5 text-gray-500 transition-transform duration-200" 
                      :class="{ 'rotate-180': expandedInvoiceId === invoice.id }"
                      fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </td>
                </tr>
                
                <!-- Expanded Detail -->
                <tr v-if="expandedInvoiceId === invoice.id" class="bg-gray-900/50">
                  <td colspan="5" class="px-6 py-6 border-t border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <!-- Basic Info -->
                      <div class="space-y-4">
                        <div>
                          <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Invoice ID</p>
                          <div class="flex items-center gap-2">
                             <code class="text-xs text-gray-300 break-all font-mono bg-gray-800 px-2 py-1 rounded">{{ invoice.invoice_id || invoice.id }}</code>
                             <button @click.stop="copyToClipboard(invoice.invoice_id || invoice.id)" class="text-indigo-400 hover:text-indigo-300">
                               <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                             </button>
                          </div>
                        </div>
                        <div>
                          <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Status</p>
                          <p class="text-sm font-medium text-white">{{ invoice.status }}</p>
                        </div>
                      </div>

                      <!-- Payment Info -->
                      <div class="space-y-4">
                        <div>
                          <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Precision Amount</p>
                          <p class="text-sm font-bold text-white">{{ invoice.amount }} <span class="text-gray-500 font-normal">{{ invoice.currency }}</span></p>
                        </div>
                        <div>
                          <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold mb-1">Created At</p>
                          <p class="text-sm text-gray-300">{{ formatDate(invoice.created_time || invoice.created_at) }}</p>
                        </div>
                      </div>

                      <!-- Actions removed as per user request -->
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <UpgradeModal
      :show="showXlsxUpgradeModal"
      :message="t('stores.xlsx_export_available_in_pro_message')"
      :limits="[]"
      recommended-plan="pro"
      :upgrade-button-text="t('stores.upgrade_to_pro')"
      @close="showXlsxUpgradeModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../store/auth';
import { useFlashStore } from '../../store/flash';
import api from '../../services/api';
import DatePicker from '../ui/DatePicker.vue';
import Select from '../ui/Select.vue';
import UpgradeModal from './UpgradeModal.vue';
import ProPlanBadge from './ProPlanBadge.vue';

const { t } = useI18n();
const authStore = useAuthStore();
const flashStore = useFlashStore();

const planCode = computed(() => (authStore.user?.plan?.code ?? 'free') as string);
const userRole = computed(() => authStore.user?.role ?? '');
const canUseXlsx = computed(() =>
  planCode.value === 'pro' || planCode.value === 'enterprise' || userRole.value === 'admin' || userRole.value === 'support'
);
const showXlsxUpgradeModal = ref(false);

const props = defineProps({
  store: {
    type: Object,
    required: true,
  },
});

const loading = ref(false);
const invoices = ref<any[]>([]);
const exportingInvoices = ref(false);

const expandedInvoiceId = ref<string | null>(null);

const statusOptions = computed(() => [
  { label: t('stores.all_statuses'), value: '' },
  { label: t('stores.new'), value: 'New' },
  { label: t('stores.paid_partially'), value: 'Paid' },
  { label: t('stores.settled'), value: 'Settled' },
  { label: t('stores.invalid'), value: 'Invalid' },
  { label: t('stores.expired'), value: 'Expired' },
]);

const filters = ref({
  status: '',
  date_from: '',
  date_to: '',
});

const hasFilters = computed(() => {
  return filters.value.status !== '' || 
         filters.value.date_from !== '' || 
         filters.value.date_to !== '';
});

onMounted(() => {
  fetchInvoices();
});

watch(() => props.store.id, () => {
    fetchInvoices();
});

async function fetchInvoices() {
  if (!props.store) return;
  
  loading.value = true;
  try {
    const params: any = {};
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.date_from) params.date_from = filters.value.date_from;
    if (filters.value.date_to) params.date_to = filters.value.date_to;
    
    const response = await api.get(`/stores/${props.store.id}/invoices`, { params });
    invoices.value = response.data.data || [];
  } catch (err: any) {
    flashStore.error(err.response?.data?.message || t('stores.failed_to_load_invoices'));
    invoices.value = [];
  } finally {
    loading.value = false;
  }
}

function clearFilters() {
  filters.value = {
    status: '',
    date_from: '',
    date_to: '',
  };
  fetchInvoices();
}

function toggleExpand(id: string) {
  if (expandedInvoiceId.value === id) {
    expandedInvoiceId.value = null;
  } else {
    expandedInvoiceId.value = id;
  }
}

async function copyToClipboard(text: string) {
  try {
    await navigator.clipboard.writeText(text);
    // Could add a toast here
  } catch (err) {
    console.error('Failed to copy text: ', err);
  }
}

async function handleExportInvoices(format: 'csv' | 'xlsx') {
  if (!props.store) return;
  
  exportingInvoices.value = true;
  flashStore.clear();
  
  try {
    const params: any = { format };
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.date_from) params.date_from = filters.value.date_from;
    if (filters.value.date_to) params.date_to = filters.value.date_to;
    
    const response = await api.get(`/stores/${props.store.id}/invoices/export`, {
      params,
      responseType: 'arraybuffer',
    });
    
    const contentType = response.headers['content-type'] || '';
    
    if (contentType.includes('application/json')) {
      const text = new TextDecoder().decode(response.data);
      const jsonData = JSON.parse(text);
      if (jsonData.type === 'asynchronous') {
        flashStore.success(t('stores.export_queued_check_reports'));
      }
    } else if (contentType.includes('text/csv')) {
      const blob = new Blob([response.data], { type: 'text/csv' });
      downloadBlob(blob, response.headers, 'invoices.csv');
      flashStore.success(t('stores.export_csv_success'));
    } else if (contentType.includes('spreadsheet') || contentType.includes('vnd.openxmlformats')) {
      const blob = new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      downloadBlob(blob, response.headers, 'invoices.xlsx');
      flashStore.success(t('stores.export_xlsx_success'));
    }
  } catch (err: any) {
    if (err.response?.status === 403) {
      showXlsxUpgradeModal.value = true;
    } else {
      flashStore.error(err.response?.data?.message || t('stores.export_failed'));
    }
  } finally {
    exportingInvoices.value = false;
  }
}

function downloadBlob(blob: Blob, headers: any, defaultFilename: string) {
  const contentDisposition = headers['content-disposition'] || '';
  let filename = defaultFilename;
  if (contentDisposition) {
    const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
    if (filenameMatch) filename = filenameMatch[1];
  }
  const urlObj = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = urlObj;
  link.setAttribute('download', filename);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(urlObj);
}

function formatDate(dateInput: string | number): string {
  if (!dateInput) return '-';
  
  let date: Date;
  if (typeof dateInput === 'number' || !isNaN(Number(dateInput))) {
    const timestamp = typeof dateInput === 'number' ? dateInput : Number(dateInput);
    // BTCPay uses seconds for createdTime, JS needs milliseconds
    // Checking if it's likely seconds (10 digits) or milliseconds (13 digits)
    date = new Date(timestamp < 10000000000 ? timestamp * 1000 : timestamp);
  } else {
    date = new Date(dateInput);
  }
  
  if (isNaN(date.getTime())) return '-';
  
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${day}.${month}.${year} ${hours}:${minutes}`;
}

function formatAmount(amount: string | number, currency: string = 'USD'): string {
  if (!amount) return '-';
  const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
  if (isNaN(numAmount)) return '-';
  const code = (currency || 'USD').toUpperCase();
  if (code === 'SATS') {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(numAmount) + ' sats';
  }
  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: code,
    }).format(numAmount);
  } catch {
    return `${numAmount} ${currency || 'USD'}`;
  }
}

function getStatusClass(status: string): string {
  const statusLower = status?.toLowerCase() || '';
  if (statusLower === 'paid' || statusLower === 'settled' || statusLower === 'complete') {
    return 'bg-green-500/10 text-green-400 border-green-500/20';
  } else if (statusLower === 'pending' || statusLower === 'processing' || statusLower === 'new') {
    return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
  } else if (statusLower === 'expired' || statusLower === 'invalid' || statusLower === 'failed') {
    return 'bg-red-500/10 text-red-400 border-red-500/20';
  }
  return 'bg-gray-700 text-gray-300 border-gray-600';
}
</script>
