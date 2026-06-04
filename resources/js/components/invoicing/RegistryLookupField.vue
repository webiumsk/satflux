<template>
  <div class="flex flex-wrap items-end gap-3">
    <div class="flex-1 min-w-[200px] relative">
      <label class="invoicing-sf-label">
        {{ label }}
        <span v-if="required" class="text-red-500">*</span>
      </label>
      <input
        :value="modelValue"
        type="text"
        :required="required"
        class="invoicing-sf-input"
        autocomplete="off"
        :placeholder="placeholder"
        @input="onInput"
        @focus="onFocus"
        @blur="onBlur"
      />
      <p v-if="lookupEnabled" class="text-xs text-gray-500 mt-1">{{ lookupHint }}</p>
      <p v-else class="text-xs text-gray-500 mt-1">{{ manualHint }}</p>

      <div
        v-if="lookupEnabled && showSuggestions && suggestions.length"
        class="absolute z-40 left-0 right-0 mt-1 rounded-lg border border-gray-200 bg-white shadow-lg max-h-64 overflow-y-auto"
      >
        <button
          v-for="item in suggestions"
          :key="`${item.ico}-${item.name}`"
          type="button"
          class="w-full text-left px-3 py-2.5 hover:bg-indigo-50 border-b border-gray-100 last:border-0"
          @mousedown.prevent="emit('pick', item)"
        >
          <span class="font-medium text-gray-900 block">{{ item.name }}</span>
          <span class="text-xs text-gray-500">
            {{ entityIdLabel }} {{ item.ico }}
            <template v-if="item.address_line"> · {{ item.address_line }}</template>
          </span>
        </button>
      </div>
      <p v-if="lookupEnabled && registryLoading" class="text-xs text-indigo-600 mt-1">
        {{ t('invoicing.registry_searching') }}
      </p>
      <p v-if="lookupEnabled && registryError" class="text-xs text-amber-700 mt-1">
        {{ t('invoicing.registry_unavailable') }}
      </p>
    </div>

    <div class="w-36 min-w-[8.5rem]">
      <label class="invoicing-sf-label">{{ t('invoicing.registry_country') }}</label>
      <select
        :value="registryCountry"
        class="invoicing-sf-input"
        @change="onRegistryCountryChange"
      >
        <optgroup
          v-for="group in groupedOptions"
          :key="group.key"
          :label="t(`invoicing.${group.labelKey}`)"
        >
          <option
            v-for="opt in group.options"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}{{ opt.autocomplete ? '' : ' *' }}
          </option>
        </optgroup>
      </select>
      <p class="text-xs text-gray-400 mt-0.5">{{ t('invoicing.registry_manual_marker') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
  REGISTRY_COUNTRY_GROUPS,
  type RegistryCountryOption,
} from '../../config/registryCountries';
import type { RegistrySummary } from '../../composables/useCompanyRegistryLookup';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    registryCountry: string;
    label: string;
    placeholder?: string;
    required?: boolean;
    suggestions: RegistrySummary[];
    registryOptions: RegistryCountryOption[];
    registryLoading?: boolean;
    registryError?: string;
    showSuggestions?: boolean;
  }>(),
  {
    placeholder: undefined,
    required: false,
    registryLoading: false,
    registryError: '',
    showSuggestions: false,
  }
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
  'update:registryCountry': [value: string];
  input: [];
  focus: [];
  blur: [];
  'registry-country-change': [];
  pick: [item: RegistrySummary];
}>();

const { t } = useI18n();

const selectedOption = computed(() =>
  props.registryOptions.find((o) => o.value === props.registryCountry)
);

const lookupEnabled = computed(() => selectedOption.value?.autocomplete ?? false);

const lookupHint = computed(() => {
  if (selectedOption.value?.provider === 'subjekt') {
    return t('invoicing.registry_lookup_hint');
  }
  return t('invoicing.registry_lookup_hint_openregistry');
});

const manualHint = computed(() => {
  const v = props.registryCountry;
  if (v === 'us') return t('invoicing.registry_lookup_us_manual');
  if (['de', 'at', 'hu', 'pt'].includes(v)) return t('invoicing.registry_lookup_manual_vies');
  if (['gi', 'pa', 'ky'].includes(v)) return t('invoicing.registry_lookup_offshore_manual');
  return t('invoicing.registry_lookup_manual');
});

const entityIdLabel = computed(() => {
  if (props.registryCountry === 'sk' || props.registryCountry === 'cz') {
    return t('invoicing.registry_entity_ico');
  }
  return t('invoicing.registry_entity_id');
});

const groupedOptions = computed(() => {
  const byGroup = new Map<string, RegistryCountryOption[]>();
  for (const opt of props.registryOptions) {
    const list = byGroup.get(opt.group) ?? [];
    list.push(opt);
    byGroup.set(opt.group, list);
  }
  return Array.from(byGroup.entries()).map(([key, options]) => ({
    key,
    labelKey: REGISTRY_COUNTRY_GROUPS[key] ?? key,
    options,
  }));
});

function onInput(e: Event) {
  emit('update:modelValue', (e.target as HTMLInputElement).value);
  emit('input');
}

function onFocus() {
  emit('focus');
}

function onBlur() {
  emit('blur');
}

function onRegistryCountryChange(e: Event) {
  emit('update:registryCountry', (e.target as HTMLSelectElement).value);
  emit('registry-country-change');
}
</script>
