<template>
  <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
    <div class="px-6 py-5 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 backdrop-blur-sm">
      <h3 class="text-xl font-bold text-white flex items-center gap-2">
        <svg class="h-5 w-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        </svg>
        Recent Invoices
      </h3>
      <a
        href="#"
        @click.prevent="emit('view-all')"
        class="text-sm font-semibold text-indigo-400 hover:text-indigo-300 transition-colors flex items-center gap-1"
      >
        View All
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
      </a>
    </div>
    <div class="overflow-x-auto custom-scrollbar">
      <table class="min-w-full divide-y divide-gray-700">
        <thead class="bg-gray-900/50">
          <tr>
            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-400 uppercase tracking-widest">
              Date
            </th>
            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-400 uppercase tracking-widest">
              Invoice ID
            </th>
            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-400 uppercase tracking-widest">
              Status
            </th>
            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-400 uppercase tracking-widest">
              Amount
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-700 bg-gray-800/30">
          <tr v-if="invoices.length === 0">
            <td colspan="4" class="px-6 py-12 text-center">
              <div class="flex flex-col items-center justify-center text-gray-500">
                <svg class="w-12 h-12 mb-2 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <p class="font-medium">No invoices yet</p>
              </div>
            </td>
          </tr>
          <tr
            v-for="invoice in invoices"
            :key="invoice.id"
            class="hover:bg-gray-700/30 transition-colors cursor-pointer group"
            @click="emit('view-invoice', invoice)"
          >
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300 font-medium">
              {{ formatDate(invoice.created_time) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center gap-2">
                <span class="text-sm font-mono text-gray-400 bg-gray-900/50 px-2 py-1 rounded border border-gray-700 group-hover:border-indigo-500/30 group-hover:text-indigo-300 transition-all">
                    {{ invoice.invoice_id?.substring(0, 8) || invoice.id?.substring(0, 8) }}
                </span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full border"
                :class="getStatusClass(invoice.status)"
              >
                {{ formatStatus(invoice.status) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-white">
              {{ formatAmount(invoice.amount, invoice.currency) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Invoice {
  id: string;
  invoice_id: string;
  status: string;
  amount: string;
  currency: string;
  created_time: string;
}

interface Props {
  invoices: Invoice[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
  'view-all': [];
  'view-invoice': [invoice: Invoice];
}>();

function formatDate(dateString: string | number): string {
  if (!dateString) return 'N/A';
  
  let date: Date;
  
  if (typeof dateString === 'number') {
    date = dateString < 946684800000 ? new Date(dateString * 1000) : new Date(dateString);
  } else {
    const parsed = parseFloat(dateString);
    if (!isNaN(parsed) && parsed < 946684800000) {
      date = new Date(parsed * 1000);
    } else {
      date = new Date(dateString);
    }
  }
  
  if (isNaN(date.getTime())) return 'N/A';
  
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  
  return date.toLocaleDateString('sk-SK');
}

function formatStatus(status: string): string {
  return status || 'Unknown';
}

function getStatusClass(status: string): string {
  const normalizedStatus = status?.toLowerCase();
  switch (normalizedStatus) {
    case 'settled':
    case 'complete':
      return 'bg-green-500/10 text-green-400 border-green-500/20';
    case 'processing':
      return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
    case 'expired':
      return 'bg-gray-500/10 text-gray-400 border-gray-500/20';
    case 'invalid':
      return 'bg-red-500/10 text-red-400 border-red-500/20';
    default:
      return 'bg-gray-500/10 text-gray-400 border-gray-500/20';
  }
}

function formatAmount(amount: string | number | null, currency: string | null): string {
  if (!amount) return 'N/A';
  const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
  if (isNaN(numAmount)) return 'N/A';
  
  return new Intl.NumberFormat('sk-SK', {
    style: 'currency',
    currency: currency || 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numAmount);
}
</script>
