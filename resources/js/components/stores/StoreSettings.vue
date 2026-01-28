<template>
  <div v-if="loading && !settings" class="text-center py-12">
    <svg class="animate-spin mx-auto h-10 w-10 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <p class="text-gray-400 mt-4">{{ t('common.loading') }}</p>
  </div>

  <div v-else-if="settings" class="space-y-6">
    <!-- Main Settings -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700">
      <div class="px-6 py-5 border-b border-gray-700 bg-gray-800/50">
        <h1 class="text-2xl font-bold text-white">{{ t('stores.store_settings') }}</h1>
        <p class="text-sm text-gray-400 mt-1">{{ t('stores.manage_store_config') }}</p>
      </div>

      <div class="px-6 py-8">
        <form @submit.prevent="handleSettingsSubmit" class="space-y-6">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.store_name') }}</label>
            <input
              id="name"
              v-model="settingsForm.name"
              type="text"
              required
              class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
            />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
             <div>
                <label for="default_currency" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.default_currency') }}</label>
                <input
                  id="default_currency"
                  v-model="settingsForm.default_currency"
                  type="text"
                  list="currency-selection-suggestion"
                  required
                  :placeholder="t('stores.currency_placeholder')"
                  class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                />
                <datalist id="currency-selection-suggestion">
                  <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                    {{ currency.code }} - {{ currency.name }}
                  </option>
                </datalist>
             </div>

             <div>
                <label for="timezone" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.timezone') }}</label>
                <Select
                  id="timezone"
                  v-model="settingsForm.timezone"
                  :options="timezoneOptions"
                  placeholder="Select timezone"
                />
             </div>
          </div>

          <div>
            <label for="preferred_exchange" class="block text-sm font-medium text-gray-300 mb-1">
              {{ t('stores.preferred_price_source') }}
            </label>
            <Select
              id="preferred_exchange"
              v-model="settingsForm.preferred_exchange"
              :options="exchanges"
              placeholder="Select preferred exchange"
            />
            <p class="mt-2 text-xs text-gray-500">{{ t('stores.recommended_price_source') }}</p>
          </div>

          <div v-if="error" class="rounded-xl bg-red-500/10 border border-red-500/20 p-4">
             <div class="flex">
                <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div class="text-sm text-red-400">{{ error }}</div>
             </div>
          </div>

          <div v-if="success" class="rounded-xl bg-green-500/10 border border-green-500/20 p-4">
             <div class="flex">
                <svg class="h-5 w-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <div class="text-sm text-green-400">{{ success }}</div>
             </div>
          </div>

          <div class="flex justify-end pt-4">
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-indigo-600/20"
            >
              <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>

              </svg>
              {{ saving ? t('auth.saving') : t('stores.save_changes') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Logo Management -->
    <div class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700">
      <div class="px-6 py-5 border-b border-gray-700 bg-gray-800/50">
        <h2 class="text-lg font-bold text-white">{{ t('stores.store_logo') }}</h2>
      </div>
      <div class="px-6 py-8">
        <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
           <div v-if="storeLogoUrl" class="relative group">
              <div class="w-32 h-32 rounded-xl bg-gray-700 flex items-center justify-center overflow-hidden border border-gray-600">
                 <img :src="storeLogoUrl" alt="Store logo" class="w-full h-full object-contain" />
              </div>
              <button
                type="button"
                @click="handleDeleteLogo"
                :disabled="deletingLogo"
                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow-lg hover:bg-red-600 transition-colors"
                :title="t('stores.delete_logo')"
              >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
           </div>
           
           <div v-else class="w-32 h-32 rounded-xl bg-gray-700/50 flex items-center justify-center border-2 border-dashed border-gray-600 text-gray-500">
              <span class="text-sm">{{ t('stores.no_logo') }}</span>
           </div>

           <div class="flex-1 w-full max-w-sm">
             <label class="block text-sm font-medium text-gray-300 mb-2">
                 {{ storeLogoUrl ? t('stores.update_logo') : t('stores.upload_logo') }}
             </label>
             <div class="relative">
                <input
                  ref="logoInputRef"
                  type="file"
                  accept="image/*"
                  @change="handleLogoUpload"
                  class="block w-full text-sm text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer bg-gray-700/50 rounded-lg border border-gray-600 focus:outline-none"
                />
             </div>
             <p class="mt-2 text-xs text-gray-500">Supported formats: PNG, JPG, GIF. Max size: 2MB.</p>
           </div>
        </div>

        <div v-if="logoError" class="mt-4 rounded-xl bg-red-500/10 border border-red-500/20 p-4">
           <div class="flex">
              <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <div class="text-sm text-red-400">{{ logoError }}</div>
           </div>
        </div>
        <div v-if="logoSuccess" class="mt-4 rounded-xl bg-green-500/10 border border-green-500/20 p-4">
           <div class="flex">
              <svg class="h-5 w-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
              <div class="text-sm text-green-400">{{ logoSuccess }}</div>
           </div>
        </div>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="bg-red-900/10 shadow-xl rounded-2xl border border-red-900/30 overflow-hidden">
      <div class="px-6 py-5 border-b border-red-900/20 bg-red-900/20">
        <h2 class="text-lg font-bold text-red-400">Danger Zone</h2>
      </div>
      <div class="px-6 py-6">
        <h3 class="text-sm font-bold text-white mb-2">Delete Store</h3>
        <p class="text-sm text-gray-400 mb-6 max-w-xl">
          This will permanently delete the store <strong>{{ store?.name }}</strong> and all its data, including invoices, apps, and settings. This action cannot be undone.
        </p>
        <button
          type="button"
          @click="showDeleteStoreModal = true"
          class="inline-flex items-center px-4 py-2 border border-red-500/50 text-sm font-medium rounded-lg text-red-400 bg-red-500/10 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 focus:ring-offset-gray-900 transition-colors"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          Delete Store
        </button>
      </div>
    </div>
  </div>

  <!-- Delete Store Confirmation Modal -->
  <div
    v-if="showDeleteStoreModal"
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
  >
     <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true" @click="showDeleteStoreModal = false"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-700">
           <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="sm:flex sm:items-start">
                 <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                 </div>
                 <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">Delete Store</h3>
                    <div class="mt-2">
                       <p class="text-sm text-gray-400">
                          Are you sure you want to delete <strong>{{ store?.name }}</strong>? This action cannot be undone.
                       </p>
                       <p class="text-sm text-gray-400 mt-2">
                          Please type <span class="font-mono text-red-400 bg-red-900/20 px-1 rounded">DELETE</span> to confirm:
                       </p>
                       <input
                          v-model="deleteStoreConfirmText"
                          type="text"
                          class="mt-3 block w-full border border-gray-600 rounded-lg shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm bg-gray-700 text-white placeholder-gray-500"
                          placeholder="Type DELETE"
                           @keyup.enter="handleDeleteStore"
                       />
                       <p v-if="deleteStoreError" class="mt-2 text-sm text-red-500">{{ deleteStoreError }}</p>
                    </div>
                 </div>
              </div>
           </div>
           <div class="bg-gray-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-700/50">
              <button
                 type="button"
                 @click="handleDeleteStore"
                 :disabled="deletingStore || deleteStoreConfirmText !== 'DELETE'"
                 class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                 {{ deletingStore ? 'Deleting...' : 'Delete Store' }}
              </button>
              <button
                 type="button"
                 @click="showDeleteStoreModal = false; deleteStoreConfirmText = ''"
                 class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-600 shadow-sm px-4 py-2 bg-transparent text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
              >
                 Cancel
              </button>
           </div>
        </div>
     </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useStoresStore } from '../../store/stores';
import api from '../../services/api';
import { currencies } from '../../data/currencies';
import { exchanges } from '../../data/exchanges';
import Select from '../ui/Select.vue';

const { t } = useI18n();

const props = defineProps({
  store: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['update-store']);

const router = useRouter();
const storesStore = useStoresStore();

const loading = ref(false);
const saving = ref(false);
const settings = ref<any>(null);
const error = ref('');
const success = ref('');

const settingsForm = ref({
  name: '',
  default_currency: 'EUR',
  timezone: 'UTC',
  preferred_exchange: '',
});

// Logo management
const logoInputRef = ref<HTMLInputElement | null>(null);
const storeLogoUrl = ref<string | null>(null);
const uploadingLogo = ref(false);
const deletingLogo = ref(false);
const logoError = ref('');
const logoSuccess = ref('');

// Delete store modal
const showDeleteStoreModal = ref(false);
const deleteStoreConfirmText = ref('');
const deletingStore = ref(false);
const deleteStoreError = ref('');

// Timezones (same list as before)
const timezones = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Phoenix',
  'America/Toronto',
  'America/Vancouver',
  'America/Sao_Paulo',
  'America/Argentina/Buenos_Aires',
  'America/Santiago',
  'America/Mexico_City',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'Europe/Rome',
  'Europe/Madrid',
  'Europe/Amsterdam',
  'Europe/Brussels',
  'Europe/Vienna',
  'Europe/Prague',
  'Europe/Warsaw',
  'Europe/Stockholm',
  'Europe/Copenhagen',
  'Europe/Helsinki',
  'Europe/Athens',
  'Europe/Istanbul',
  'Europe/Moscow',
  'Europe/Kiev',
  'Asia/Dubai',
  'Asia/Tokyo',
  'Asia/Shanghai',
  'Asia/Hong_Kong',
  'Asia/Singapore',
  'Asia/Seoul',
  'Asia/Bangkok',
  'Asia/Jakarta',
  'Asia/Manila',
  'Asia/Kolkata',
  'Asia/Karachi',
  'Asia/Dhaka',
  'Asia/Tehran',
  'Asia/Jerusalem',
  'Australia/Sydney',
  'Australia/Melbourne',
  'Australia/Brisbane',
  'Australia/Perth',
  'Pacific/Auckland',
  'Pacific/Honolulu',
];

const timezoneOptions = timezones.map(tz => ({ label: tz, value: tz }));

onMounted(async () => {
  await fetchSettings();
});

async function fetchSettings() {
  if (!props.store) return;
  
  loading.value = true;
  try {
    const response = await api.get(`/stores/${props.store.id}/settings`);
    settings.value = response.data.data;
    settingsForm.value.name = settings.value.name;
    settingsForm.value.default_currency = settings.value.default_currency || 'EUR';
    settingsForm.value.timezone = settings.value.timezone || 'UTC';
    settingsForm.value.preferred_exchange = settings.value.preferred_exchange || '';
    storeLogoUrl.value = settings.value.logo_url || null;
  } catch (err) {
    console.error('Failed to fetch settings:', err);
  } finally {
    loading.value = false;
  }
}

async function handleSettingsSubmit() {
  if (!props.store) return;
  
  error.value = '';
  success.value = '';
  saving.value = true;

  try {
    await api.put(`/stores/${props.store.id}/settings`, settingsForm.value);
    success.value = 'Settings updated successfully';
    emit('update-store'); // Notify parent to refresh store data if needed
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to update settings.';
    if (err.response?.data?.errors) {
      const errors = Object.values(err.response.data.errors).flat();
      error.value = errors.join(', ');
    }
  } finally {
    saving.value = false;
    // Clear success message
    if (success.value) {
      setTimeout(() => { success.value = ''; }, 3000);
    }
  }
}

async function handleLogoUpload(event: Event) {
  if (!props.store) return;
  
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  uploadingLogo.value = true;
  logoError.value = '';
  logoSuccess.value = '';

  try {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post(`/stores/${props.store.id}/logo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (response.data?.data?.imageUrl) {
      storeLogoUrl.value = response.data.data.imageUrl;
    } else if (response.data?.imageUrl) {
      storeLogoUrl.value = response.data.imageUrl;
    }

    logoSuccess.value = 'Logo uploaded successfully';
    emit('update-store');

    if (logoInputRef.value) logoInputRef.value.value = '';
    setTimeout(() => { logoSuccess.value = ''; }, 3000);
  } catch (err: any) {
    logoError.value = err.response?.data?.message || 'Failed to upload logo';
  } finally {
    uploadingLogo.value = false;
  }
}

async function handleDeleteLogo() {
  if (!props.store) return;
  
  if (!confirm('Are you sure you want to delete the store logo?')) return;

  deletingLogo.value = true;
  logoError.value = '';
  logoSuccess.value = '';

  try {
    await api.delete(`/stores/${props.store.id}/logo`);
    storeLogoUrl.value = null;
    logoSuccess.value = 'Logo deleted successfully';
    emit('update-store');
    setTimeout(() => { logoSuccess.value = ''; }, 3000);
  } catch (err: any) {
    logoError.value = err.response?.data?.message || 'Failed to delete logo';
  } finally {
    deletingLogo.value = false;
  }
}

async function handleDeleteStore() {
  if (!props.store) return;
  
  if (deleteStoreConfirmText.value !== 'DELETE') {
    deleteStoreError.value = 'Please type DELETE to confirm';
    return;
  }

  deletingStore.value = true;
  deleteStoreError.value = '';

  try {
    await storesStore.deleteStore(props.store.id);
    showDeleteStoreModal.value = false;
    router.push({ name: 'home' });
  } catch (err: any) {
    deleteStoreError.value = err.response?.data?.message || 'Failed to delete store';
    deletingStore.value = false;
  }
}
</script>
