<template>
  <div class="bg-white shadow rounded-lg p-6">
    <div class="flex items-start justify-between">
      <div class="flex-1">
        <div class="flex items-center">
          <h3 class="text-lg font-medium text-gray-900">{{ apiKey.label }}</h3>
          <span
            v-if="!apiKey.is_active"
            class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"
          >
            Revoked
          </span>
          <span
            v-else-if="apiKey.expires_at && new Date(apiKey.expires_at) < new Date()"
            class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
          >
            Expired
          </span>
          <span
            v-else
            class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
          >
            Active
          </span>
        </div>

        <div class="mt-2 space-y-1">
          <div class="text-sm text-gray-600">
            <span class="font-medium">Permissions:</span>
            <span class="ml-2">{{ formatPermissions(apiKey.permissions) }}</span>
          </div>
          <div class="text-sm text-gray-600">
            <span class="font-medium">Created:</span>
            <span class="ml-2">{{ formatDate(apiKey.created_at) }}</span>
          </div>
          <div v-if="apiKey.last_used_at" class="text-sm text-gray-600">
            <span class="font-medium">Last used:</span>
            <span class="ml-2">{{ formatDate(apiKey.last_used_at) }}</span>
          </div>
          <div v-if="apiKey.callback_url" class="text-sm text-gray-600">
            <span class="font-medium">Callback URL:</span>
            <span class="ml-2 font-mono text-xs">{{ apiKey.callback_url }}</span>
          </div>
          <div v-if="apiKey.expires_at" class="text-sm text-gray-600">
            <span class="font-medium">Expires:</span>
            <span class="ml-2">{{ formatDate(apiKey.expires_at) }}</span>
          </div>
        </div>
      </div>

      <div class="ml-4 flex items-center space-x-2">
        <button
          @click="handleRegenerate"
          :disabled="regenerating"
          class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50"
        >
          {{ regenerating ? 'Regenerating...' : 'Regenerate' }}
        </button>
        <button
          @click="confirmDelete"
          :disabled="deleting"
          class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
        >
          {{ deleting ? 'Deleting...' : 'Delete' }}
        </button>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click.self="showDeleteModal = false"
    >
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">
            Delete API Key
          </h3>
          <p class="text-sm text-gray-600 mb-4">
            Are you sure you want to delete the API key "{{ apiKey.label }}"? This action cannot be undone.
          </p>
          <div class="flex justify-end space-x-3">
            <button
              @click="showDeleteModal = false"
              class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              @click="handleDelete"
              :disabled="deleting"
              class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:opacity-50"
            >
              {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Regenerate Confirmation Modal -->
    <div
      v-if="showRegenerateModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click.self="showRegenerateModal = false"
    >
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">
            Regenerate API Key
          </h3>
          <p class="text-sm text-gray-600 mb-4">
            This will create a new API key and revoke the current one. The old API key will stop working immediately.
          </p>
          <div class="flex justify-end space-x-3">
            <button
              @click="showRegenerateModal = false"
              class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              @click="confirmRegenerate"
              :disabled="regenerating"
              class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 disabled:opacity-50"
            >
              {{ regenerating ? 'Regenerating...' : 'Regenerate' }}
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

