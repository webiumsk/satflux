<template>
  <div class="space-y-6">
    <h2 class="text-lg font-semibold text-white">{{ t('stores.settings_tab_rates') }}</h2>
    <p class="text-sm text-gray-400 max-w-2xl">{{ t('stores.settings_tab_rates_desc') }}</p>
    <div>
      <label for="preferred_exchange" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.preferred_price_source') }}</label>
      <Select
        id="preferred_exchange"
        v-model="form.preferred_exchange"
        :options="exchanges"
        :placeholder="t('stores.settings_select')"
      />
      <p class="mt-2 text-xs text-gray-500">{{ t('stores.recommended_price_source') }}</p>
    </div>
    <div class="space-y-4 pt-4 border-t border-gray-700">
      <div class="flex items-center gap-2 flex-wrap">
        <h3 class="text-base font-semibold text-white">{{ t('stores.settings_additional_rates') }}</h3>
        <button
          v-if="!canEditRatesOptions"
          type="button"
          @click="$emit('update:showRatesProNotice', !showRatesProNotice)"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-colors"
        >
          {{ t('stores.available_in_pro') }}
          <svg
            class="w-3.5 h-3.5 transition-transform duration-200"
            :class="{ 'rotate-180': showRatesProNotice }"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div
          v-if="!canEditRatesOptions && showRatesProNotice"
          class="mb-3 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-sm text-amber-200"
        >
          <p class="mb-2">{{ t('stores.pos_advanced_options_pro_only') }}</p>
          <a
            :href="'/account'"
            class="inline-flex items-center font-medium text-amber-300 hover:text-amber-200 underline underline-offset-2"
          >
            {{ t('stores.upgrade_to_pro') }}
          </a>
        </div>
      </Transition>
      <div
        :class="[
          'space-y-4',
          !canEditRatesOptions && 'pointer-events-none opacity-75 select-none',
        ]"
      >
        <div>
          <label for="additional_tracked_rates" class="block text-sm font-medium text-gray-300 mb-1">{{ t('stores.settings_additional_rates_label') }}</label>
          <input
            id="additional_tracked_rates"
            :value="(form.additional_tracked_rates || []).join(', ')"
            type="text"
            placeholder="USD, EUR, JPY"
            class="appearance-none block w-full px-4 py-3 border border-gray-600 rounded-xl shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
            @input="onAdditionalRatesInput($event)"
          />
          <p class="mt-1 text-xs text-gray-500">{{ t('stores.settings_additional_rates_desc') }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Select from '../ui/Select.vue';
import { exchanges } from '../../data/exchanges';

const props = defineProps<{
  form: Record<string, any>;
  canEditRatesOptions: boolean;
  showRatesProNotice: boolean;
}>();

defineEmits<{
  'update:showRatesProNotice': [value: boolean];
}>();

const { t } = useI18n();

function onAdditionalRatesInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value;
  props.form.additional_tracked_rates = raw.split(',').map((s: string) => s.trim()).filter(Boolean);
}
</script>
