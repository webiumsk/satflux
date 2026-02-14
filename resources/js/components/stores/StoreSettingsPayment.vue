<template>
  <div class="space-y-6">
    <h2 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">{{ t('stores.settings_tab_payment') }}</h2>
    <p class="text-sm text-gray-400 max-w-2xl">{{ t('stores.settings_tab_payment_desc') }}</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label for="default_currency" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.default_currency') }}</label>
        <input
          id="default_currency"
          v-model="form.default_currency"
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
          v-model="form.timezone"
          :options="timezoneOptions"
          :placeholder="t('stores.settings_select_timezone')"
        />
      </div>
    </div>
    <div class="space-y-4 pt-4 border-t border-gray-700">
      <div class="flex flex-wrap gap-x-8 gap-y-4 items-start">
        <label class="flex items-center">
          <input v-model="form.anyone_can_create_invoice" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
          <span class="ml-2 text-sm text-gray-300">{{ t('stores.settings_allow_anyone_invoice') }}</span>
        </label>
        <div>
          <label class="flex items-center">
            <input v-model="form.show_recommended_fee" type="checkbox" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-600 bg-gray-800 rounded" />
            <span class="ml-2 text-sm text-gray-300">{{ t('stores.settings_show_recommended_fee') }}</span>
          </label>
          <p v-if="form.show_recommended_fee" class="mt-1 ml-7 text-xs text-gray-500">{{ t('stores.settings_fee_btc_ltc_only') }}</p>
        </div>
      </div>
      <div v-if="form.show_recommended_fee" class="max-w-xs">
        <label for="recommended_fee_block_target" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.settings_recommended_fee_blocks') }}</label>
        <input
          id="recommended_fee_block_target"
          v-model.number="form.recommended_fee_block_target"
          type="number"
          min="1"
          class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
        />
      </div>
    </div>
    <!-- Payment options: Pro only -->
    <div class="space-y-4 pt-4 border-t border-gray-700">
      <div class="flex items-center gap-2 flex-wrap">
        <h3 class="text-base font-semibold text-white">{{ t('stores.settings_payment_options') }}</h3>
        <button
          v-if="!canEditPaymentOptions"
          type="button"
          @click="$emit('show-upgrade')"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
        >
          {{ t('stores.available_in_pro') }}
        </button>
      </div>
      <div
        :class="[
          'space-y-6',
          !canEditPaymentOptions && 'pointer-events-none opacity-75 select-none',
        ]"
      >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="network_fee_mode" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.settings_add_network_fee') }}</label>
            <Select
              id="network_fee_mode"
              v-model="form.network_fee_mode"
              :options="networkFeeModeOptions"
              :placeholder="t('stores.settings_select')"
            />
          </div>
          <div>
            <label for="invoice_expiration" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.settings_invoice_expires') }}</label>
            <input
              id="invoice_expiration"
              v-model.number="form.invoice_expiration"
              type="number"
              min="1"
              class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
            />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="payment_tolerance" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.settings_consider_paid_less') }}</label>
            <input
              id="payment_tolerance"
              v-model.number="form.payment_tolerance"
              type="number"
              min="0"
              step="0.01"
              class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
            />
          </div>
          <div>
            <label for="refund_bolt11_expiration" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.settings_bolt11_refund_days') }}</label>
            <input
              id="refund_bolt11_expiration"
              v-model.number="form.refund_bolt11_expiration"
              type="number"
              min="1"
              class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
            />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="monitoring_expiration" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.settings_payment_invalid_after') }}</label>
            <input
              id="monitoring_expiration"
              v-model.number="form.monitoring_expiration"
              type="number"
              min="0"
              class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
            />
          </div>
        </div>
        <div>
          <label for="speed_policy" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.settings_invoice_settled_when') }}</label>
          <Select
            id="speed_policy"
            v-model="form.speed_policy"
            :options="speedPolicyOptions"
            :placeholder="t('stores.settings_select')"
          />
        </div>
        <div>
          <label for="lightning_description_template" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.settings_lightning_description_template') }}</label>
          <input
            id="lightning_description_template"
            v-model="form.lightning_description_template"
            type="text"
            class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Select from '../ui/Select.vue';
import { currencies } from '../../data/currencies';

const props = defineProps<{
  form: Record<string, any>;
  canEditPaymentOptions: boolean;
  timezoneOptions: { label: string; value: string }[];
  speedPolicyOptions: { label: string; value: string }[];
  networkFeeModeOptions: { label: string; value: string }[];
}>();

defineEmits<{
  'show-upgrade': [];
}>();

const { t } = useI18n();
</script>
