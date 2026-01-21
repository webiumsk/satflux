<template>
  <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 p-6 transition-all hover:border-gray-600">
    <div class="flex flex-col md:flex-row items-start justify-between gap-4">
      <div class="flex-1 w-full">
        <div class="flex items-center flex-wrap gap-2 mb-3">
          <h3 class="text-lg font-bold text-white">{{ apiKey.label }}</h3>
          <span
            v-if="!apiKey.is_active"
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20"
          >
            Revoked
          </span>
          <span
            v-else-if="apiKey.expires_at && new Date(apiKey.expires_at) < new Date()"
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500/10 text-yellow-400 border border-yellow-500/20"
          >
            Expired
          </span>
          <span
            v-else
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20"
          >
            Active
          </span>
        </div>

        <div class="space-y-2">
          <div class="text-sm text-gray-400">
            <span class="font-medium text-gray-500">Permissions:</span>
            <span class="ml-2 text-gray-300">{{ formatPermissions(apiKey.permissions) }}</span>
          </div>
          <div class="text-sm text-gray-400">
            <span class="font-medium text-gray-500">Created:</span>
            <span class="ml-2 text-gray-300">{{ formatDate(apiKey.created_at) }}</span>
          </div>
          <div v-if="apiKey.last_used_at" class="text-sm text-gray-400">
            <span class="font-medium text-gray-500">Last used:</span>
            <span class="ml-2 text-gray-300">{{ formatDate(apiKey.last_used_at) }}</span>
          </div>
          <div v-if="apiKey.callback_url" class="text-sm text-gray-400">
            <span class="font-medium text-gray-500">Callback URL:</span>
            <span class="ml-2 font-mono text-xs text-indigo-300 bg-gray-900/50 px-1.5 py-0.5 rounded border border-gray-700">{{ apiKey.callback_url }}</span>
          </div>
          <div v-if="apiKey.expires_at" class="text-sm text-gray-400">
            <span class="font-medium text-gray-500">Expires:</span>
            <span class="ml-2 text-gray-300">{{ formatDate(apiKey.expires_at) }}</span>
          </div>
        </div>
      </div>

      <div class="flex items-center space-x-3 w-full md:w-auto mt-2 md:mt-0">
        <button
          @click="handleRegenerate"
          :disabled="regenerating"
          class="flex-1 md:flex-none inline-flex justify-center items-center px-3 py-2 border border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-xl text-gray-300 bg-gray-700/50 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-colors"
        >
          <svg v-if="regenerating" class="animate-spin -ml-0.5 mr-2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
             <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
             <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ regenerating ? 'Regenerating...' : 'Regenerate' }}
        </button>
        <button
          @click="confirmDelete"
          :disabled="deleting"
          class="flex-1 md:flex-none inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-xl text-red-400 bg-red-500/10 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 transition-colors border-red-500/20"
        >
          <svg v-if="deleting" class="animate-spin -ml-0.5 mr-2 h-4 w-4 text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
             <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
             <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ deleting ? 'Deleting...' : 'Delete' }}
        </button>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="modal-title"
      role="dialog"
      aria-modal="true"
    >
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="showDeleteModal = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-700">
           <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="sm:flex sm:items-start">
                 <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10 border border-red-500/20">
                    <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                 </div>
                 <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-bold text-white mb-2" id="modal-title">Delete API Key</h3>
                    <div class="mt-2">
                       <p class="text-sm text-gray-400">
                          Are you sure you want to delete the API key <span class="text-white font-medium">"{{ apiKey.label }}"</span>? This action cannot be undone.
                       </p>
                    </div>
                 </div>
              </div>
           </div>
           <div class="bg-gray-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-700/50">
             <button
               @click="handleDelete"
               :disabled="deleting"
               class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
             >
               {{ deleting ? 'Deleting...' : 'Delete' }}
             </button>
             <button
               @click="showDeleteModal = false"
               class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-600 shadow-sm px-4 py-2 bg-transparent text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
             >
               Cancel
             </button>
           </div>
        </div>
      </div>
    </div>

    <!-- Regenerate Confirmation Modal -->
    <div
      v-if="showRegenerateModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="modal-title"
      role="dialog"
      aria-modal="true"
    >
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="showRegenerateModal = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-700">
           <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="sm:flex sm:items-start">
                 <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-orange-900/20 sm:mx-0 sm:h-10 sm:w-10 border border-orange-500/20">
                    <svg class="h-6 w-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                 </div>
                 <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-bold text-white mb-2" id="modal-title">Regenerate API Key</h3>
                    <div class="mt-2">
                       <p class="text-sm text-gray-400">
                          This will create a new API key and revoke the current one. The old API key will stop working immediately.
                       </p>
                    </div>
                 </div>
              </div>
           </div>
           <div class="bg-gray-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-700/50">
             <button
               @click="confirmRegenerate"
               :disabled="regenerating"
               class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-orange-600 text-base font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
             >
               {{ regenerating ? 'Regenerating...' : 'Regenerate' }}
             </button>
             <button
               @click="showRegenerateModal = false"
               class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-600 shadow-sm px-4 py-2 bg-transparent text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
             >
               Cancel
             </button>
           </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import api from '../../services/api';

interface Props {
  apiKey: {
    id: string;
    label: string;
    permissions: string[];
    callback_url?: string;
    is_active: boolean;
    last_used_at?: string;
    expires_at?: string;
    created_at: string;
  };
  storeId: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  regenerate: [];
  delete: [];
  refresh: [];
}>();

const deleting = ref(false);
const regenerating = ref(false);
const showDeleteModal = ref(false);
const showRegenerateModal = ref(false);

function formatDate(dateString: string): string {
  if (!dateString) return '-';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return '-';
  
  // European format: DD.MM.YYYY HH:mm
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${day}.${month}.${year} ${hours}:${minutes}`;
}

function formatPermissions(permissions: string[]): string {
  if (!permissions || permissions.length === 0) return 'None';
  
  const permissionLabels: Record<string, string> = {
    'btcpay.store.canviewinvoices': 'View invoices',
    'btcpay.store.cancreateinvoice': 'Create an invoice',
    'btcpay.store.canmodifyinvoices': 'Modify invoices',
    'btcpay.store.webhooks.canmodifywebhooks': "Modify selected stores' webhooks",
    'btcpay.store.canviewstoresettings': 'View your stores',
    'btcpay.store.cancreatenonapprovedpullpayments': 'Create non-approved pull payments in selected stores',
  };

  return permissions
    .map(p => permissionLabels[p] || p)
    .join(', ');
}

function confirmDelete() {
  showDeleteModal.value = true;
}

async function handleDelete() {
  deleting.value = true;
  try {
    await api.delete(`/stores/${props.storeId}/api-keys/${props.apiKey.id}`);
    emit('delete');
    showDeleteModal.value = false;
  } catch (err: any) {
    console.error('Failed to delete API key:', err);
    alert(err.response?.data?.message || 'Failed to delete API key');
  } finally {
    deleting.value = false;
  }
}

function handleRegenerate() {
  showRegenerateModal.value = true;
}

async function confirmRegenerate() {
  regenerating.value = true;
  try {
    const response = await api.post(`/stores/${props.storeId}/api-keys/${props.apiKey.id}/regenerate`);
    
    // Show the new API key in a modal (similar to create flow)
    alert(`New API Key: ${response.data.data.api_key}\n\n⚠️ Save this API key now. You won't be able to see it again!`);
    
    emit('regenerate');
    showRegenerateModal.value = false;
  } catch (err: any) {
    console.error('Failed to regenerate API key:', err);
    alert(err.response?.data?.message || 'Failed to regenerate API key');
  } finally {
    regenerating.value = false;
  }
}
</script>
