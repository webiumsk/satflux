<template>
  <div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
      <h3 class="text-lg font-medium text-gray-900">Recent Invoices</h3>
              <a
        href="#"
        @click.prevent="emit('view-all')"
        class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
      >
        View All
      </a>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Date
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Invoice ID
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Status
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Amount
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-if="invoices.length === 0">
            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
              No invoices yet
            </td>
          </tr>
          <tr
            v-for="invoice in invoices"
            :key="invoice.id"
            class="hover:bg-gray-50 cursor-pointer"
            @click="emit('view-invoice', invoice)"
          >
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
              {{ formatDate(invoice.created_time) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
              {{ invoice.invoice_id?.substring(0, 8) || invoice.id?.substring(0, 8) }}...
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                :class="getStatusClass(invoice.status)"
              >
                {{ formatStatus(invoice.status) }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
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

defineProps<Props>();

const emit = defineEmits<{
  'view-all': [];
  'view-invoice': [invoice: Invoice];
}>();

function formatDate(dateString: string | number): string {
  if (!dateString) return 'N/A';
  
  let date: Date;
  
  // If it's a number, it's likely a Unix timestamp
  if (typeof dateString === 'number') {
    // BTCPay API returns Unix timestamps in seconds, but JavaScript Date expects milliseconds
    // If the number is less than a reasonable timestamp in milliseconds (year 2000), assume it's in seconds
    date = dateString < 946684800000 ? new Date(dateString * 1000) : new Date(dateString);
  } else {
    // If it's a string, try to parse it
    const parsed = parseFloat(dateString);
    if (!isNaN(parsed) && parsed < 946684800000) {
      // It's a string representation of a Unix timestamp in seconds
      date = new Date(parsed * 1000);
    } else {
      // It's an ISO string or other date format
      date = new Date(dateString);
    }
  }
  
  // Check if date is valid
  if (isNaN(date.getTime())) return 'N/A';
  
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
  if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
  
  // European format: DD.MM.YYYY
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}.${month}.${year}`;
}

function formatStatus(status: string): string {
  const statusMap: Record<string, string> = {
    'Settled': 'Settled',
    'Complete': 'Complete',
    'Processing': 'Processing',
    'Expired': 'Expired',
    'Invalid': 'Invalid',
  };
  return statusMap[status] || status || 'Unknown';
}

function getStatusClass(status: string): string {
  const classMap: Record<string, string> = {
    'Settled': 'bg-green-100 text-green-800',
    'Complete': 'bg-green-100 text-green-800',
    'Processing': 'bg-yellow-100 text-yellow-800',
    'Expired': 'bg-gray-100 text-gray-800',
    'Invalid': 'bg-red-100 text-red-800',
  };
  return classMap[status] || 'bg-gray-100 text-gray-800';
}

function formatAmount(amount: string | number | null, currency: string | null): string {
  if (!amount) return 'N/A';
  const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
  if (isNaN(numAmount)) return 'N/A';
  
  const formattedAmount = numAmount.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  
  return `${formattedAmount} ${currency || 'USD'}`;
}
</script>

