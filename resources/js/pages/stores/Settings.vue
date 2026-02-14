<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-6">
        <router-link
          :to="`/stores/${storeId}`"
          class="text-indigo-600 hover:text-indigo-500 text-sm"
        >
          {{ t('settings.back_to_store') }}
        </router-link>
      </div>

      <div v-if="loading && !settings" class="text-center py-12">
        <p class="text-gray-500">{{ t('settings.loading_settings') }}</p>
      </div>

      <div v-else-if="settings" class="bg-white shadow rounded-lg">
        <div class="px-6 py-5 border-b border-gray-200">
          <h1 class="text-3xl font-bold text-gray-900">{{ t('settings.title') }}</h1>
        </div>

        <div class="px-6 py-5 space-y-8">
          <!-- Editable Fields -->
          <div>
            <h2 class="text-lg font-medium text-gray-900 mb-4">{{ t('settings.store_information') }}</h2>
            <form @submit.prevent="handleSubmit" class="space-y-6">
              <div>
                <label for="name" class="block text-sm font-medium text-gray-700">{{ t('settings.store_name') }}</label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  required
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
              </div>

              <div>
                <label for="default_currency" class="block text-sm font-medium text-gray-700">{{ t('settings.default_currency') }}</label>
                <input
                  id="default_currency"
                  v-model="form.default_currency"
                  type="text"
                  list="currency-selection-suggestion"
                  required
                  :placeholder="t('settings.currency_placeholder')"
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
                <datalist id="currency-selection-suggestion">
                  <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                    {{ currency.code }} - {{ currency.name }}
                  </option>
                </datalist>
              </div>

              <div>
                <label for="timezone" class="block text-sm font-medium text-gray-700">{{ t('settings.timezone') }}</label>
                <Select
                  id="timezone"
                  v-model="form.timezone"
                  :options="timezoneOptions"
                  placeholder="Select timezone"
                />
              </div>

              <div>
                <label for="preferred_exchange" class="block text-sm font-medium text-gray-700">
                  {{ t('settings.preferred_price_source') }}
                </label>
                <Select
                  id="preferred_exchange"
                  v-model="form.preferred_exchange"
                  :options="exchanges"
                  placeholder="Select preferred exchange"
                />
                <p class="mt-2 text-sm text-gray-500">{{ t('settings.price_source_description') }}</p>
              </div>

              <div class="flex justify-end">
                <button
                  type="submit"
                  :disabled="saving"
                  class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                  {{ saving ? t('settings.saving') : t('settings.save_changes') }}
                </button>
              </div>
            </form>
          </div>

          <!-- Read-only Fields -->
          <div class="border-t border-gray-200 pt-8">
            <h2 class="text-lg font-medium text-gray-900 mb-4">{{ t('settings.additional_settings') }}</h2>
            <p class="text-sm text-gray-600 mb-4">
              {{ t('settings.additional_settings_description') }}
            </p>
          </div>
        </div>
      </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import api from '../../services/api';
import { useFlashStore } from '../../store/flash';
import { currencies } from '../../data/currencies';
import { exchanges } from '../../data/exchanges';
import Select from '../../components/ui/Select.vue';

const { t } = useI18n();

const route = useRoute();
const storeId = route.params.id as string;

const loading = ref(false);
const saving = ref(false);
const settings = ref<any>(null);
const flashStore = useFlashStore();

const form = ref({
  name: '',
  default_currency: 'EUR',
  timezone: 'UTC',
  preferred_exchange: '',
});

// Common timezones - same as in Create.vue
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

async function fetchSettings() {
  loading.value = true;
  try {
    const response = await api.get(`/stores/${storeId}/settings`);
    settings.value = response.data.data;
    form.value.name = settings.value.name;
    form.value.default_currency = settings.value.default_currency || 'EUR';
    form.value.timezone = settings.value.timezone || 'UTC';
    form.value.preferred_exchange = settings.value.preferred_exchange || '';
  } finally {
    loading.value = false;
  }
}

async function handleSubmit() {
  saving.value = true;
  flashStore.clear();
  try {
    await api.put(`/stores/${storeId}/settings`, form.value);
    flashStore.success(t('settings.settings_updated'));
    await fetchSettings();
  } catch (err: any) {
    const msg = err.response?.data?.message || t('settings.failed_to_update');
    const errors = err.response?.data?.errors ? Object.values(err.response.data.errors).flat() : [];
    flashStore.error(errors.length ? errors.join(', ') : msg);
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  fetchSettings();
});
</script>








