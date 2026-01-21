<template>
  <div v-if="loading && invoices.length === 0" class="text-center py-12">
    <svg class="animate-spin mx-auto h-10 w-10 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <p class="text-gray-400 mt-4">Loading invoices...</p>
  </div>

  <div v-else class="space-y-6">
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
      <div class="px-6 py-5 border-b border-gray-700 bg-gray-800/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
           <h1 class="text-2xl font-bold text-white">Invoices</h1>
           <p class="text-sm text-gray-400 mt-1">View and manage your store transactions.</p>
        </div>
        
        <div class="flex items-center gap-3">
             <button
              @click="handleExportInvoices"
              :disabled="exportingInvoices"
              class="inline-flex items-center px-4 py-2 border border-gray-600 shadow-sm text-sm font-medium rounded-lg text-white bg-gray-700 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <svg v-if="!exportingInvoices" class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              <svg v-else class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ exportingInvoices ? 'Exporting...' : 'Export to CSV' }}
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
              Status
            </label>
            <select
              id="invoice-status-filter"
              v-model="filters.status"
              @change="fetchInvoices"
              class="block w-full border border-gray-600 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-700 text-white"
            >
              <option value="">All Statuses</option>
              <option value="New">New</option>
              <option value="Paid">Paid (Partially)</option>
              <option value="Settled">Settled</option>
              <option value="Invalid">Invalid</option>
              <option value="Expired">Expired</option>
            </select>
          </div>

          <!-- Date From -->
          <div class="md:col-span-1">
            <label for="invoice-date-from" class="block text-xs font-medium text-gray-400 mb-1 uppercase tracking-wider">
              From Date
            </label>
            <input
              id="invoice-date-from"
              v-model="filters.date_from"
              type="date"
              @change="fetchInvoices"
              class="block w-full border border-gray-600 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-700 text-white placeholder-gray-500"
            />
          </div>

          <!-- Date To -->
          <div class="md:col-span-1">
            <label for="invoice-date-to" class="block text-xs font-medium text-gray-400 mb-1 uppercase tracking-wider">
              To Date
            </label>
            <input
              id="invoice-date-to"
              v-model="filters.date_to"
              type="date"
              @change="fetchInvoices"
             class="block w-full border border-gray-600 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-700 text-white placeholder-gray-500"
            />
          </div>
          
           <!-- Filter Actions -->
           <div class="md:col-span-1 flex items-end">
               <button
                  v-if="hasFilters"
                  @click="clearFilters"
                  class="w-full py-2 px-4 border border-gray-600 rounded-lg text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors"
                >
                  Clear Filters
                </button>
           </div>
        </div>

        <div v-if="error" class="rounded-xl bg-red-500/10 border border-red-500/20 p-4 mb-6">
          <div class="flex">
             <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
             <div class="text-sm text-red-400">{{ error }}</div>
          </div>
        </div>

        <div v-if="exportSuccess" class="rounded-xl bg-green-500/10 border border-green-500/20 p-4 mb-6">
           <div class="flex">
              <svg class="h-5 w-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
              <div class="text-sm text-green-400">{{ exportSuccess }}</div>
           </div>
        </div>
        
        <div v-if="exportError" class="rounded-xl bg-red-500/10 border border-red-500/20 p-4 mb-6">
           <div class="flex">
              <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <div class="text-sm text-red-400">{{ exportError }}</div>
           </div>
        </div>

        <div v-if="invoices.length === 0 && !loading" class="text-center py-16 bg-gray-700/30 rounded-xl border border-dashed border-gray-600">
          <svg class="mx-auto h-12 w-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-white">No invoices found</h3>
          <p class="mt-1 text-sm text-gray-400">Try adjusting your filters or create a new invoice.</p>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-gray-700">
          <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-700/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Invoice ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
              </tr>
            </thead>
            <tbody class="bg-gray-800 divide-y divide-gray-700">
              <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-700/50 transition-colors cursor-pointer" @click="handleViewInvoice(invoice)">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-400 font-mono">{{ invoice.invoice_id ? invoice.invoice_id.substring(0, 8) + '...' : invoice.id.substring(0, 8) + '...' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">{{ formatDate(invoice.created_time || invoice.created_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full border"
                    :class="getStatusClass(invoice.status)">
                    {{ invoice.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-medium">{{ formatAmount(invoice.amount, invoice.currency) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../services/api';

const props = defineProps({
  store: {
    type: Object,
    required: true,
  },
});

const router = useRouter();
const loading = ref(false);
const invoices = ref<any[]>([]);
const error = ref('');
const exportingInvoices = ref(false);
const exportSuccess = ref('');
const exportError = ref('');

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
  error.value = '';
  try {
    const params: any = {};
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.date_from) params.date_from = filters.value.date_from;
    if (filters.value.date_to) params.date_to = filters.value.date_to;
    
    const response = await api.get(`/stores/${props.store.id}/invoices`, { params });
    invoices.value = response.data.data || [];
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load invoices.';
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

function handleViewInvoice(invoice: any) {
  // router.push({ name: 'stores-invoices-show', params: { id: props.store.id, invoiceId: invoice.id } });
  // Placeholder:
  console.log('View invoice', invoice);
}

// Reuse logic from Show.vue for export but adapted
async function handleExportInvoices() {
  if (!props.store) return;
  
  exportingInvoices.value = true;
  exportError.value = '';
  exportSuccess.value = '';
  
  try {
     const params: any = {};
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
        exportSuccess.value = 'Export job queued. Check Exports tab.';
        setTimeout(() => { exportSuccess.value = ''; }, 5000);
      }
    } else if (contentType.includes('text/csv')) {
      const blob = new Blob([response.data], { type: 'text/csv' });
      const urlObj = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = urlObj;
       const contentDisposition = response.headers['content-disposition'] || '';
      let filename = 'invoices.csv';
       if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
        if (filenameMatch) filename = filenameMatch[1];
      }
      link.setAttribute('download', filename);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      exportSuccess.value = 'CSV exported successfully.';
      setTimeout(() => { exportSuccess.value = ''; }, 3000);
    }
  } catch (err: any) {
     exportError.value = 'Failed to export invoices.';
     setTimeout(() => { exportError.value = ''; }, 5000);
  } finally {
    exportingInvoices.value = false;
  }
}

function formatDate(dateString: string): string {
  if (!dateString) return '-';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return '-';
  
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}.${month}.${year}`;
}

function formatAmount(amount: string | number, currency: string = 'USD'): string {
  if (!amount) return '-';
  try {
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (isNaN(numAmount)) return '-';
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency || 'USD',
    }).format(numAmount);
  } catch (error) {
    return `${amount} ${currency || 'USD'}`;
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
