<template>
  <div>
    <select
      :value="selectValue"
      class="invoicing-sf-input-table text-center w-full min-w-[4.5rem]"
      :disabled="disabled"
      @change="onSelectChange"
    >
      <option value=""></option>
      <option v-for="opt in INVOICE_LINE_UNIT_PRESETS" :key="opt.value" :value="opt.value">
        {{ opt.labelKey ? t(`invoicing.${opt.labelKey}`) : opt.label ?? opt.value }}
      </option>
      <option :value="INVOICE_LINE_UNIT_CUSTOM">{{ t('invoicing.unit_custom') }}</option>
    </select>
    <input
      v-if="isCustom"
      v-model="model"
      type="text"
      class="invoicing-sf-input-table text-center w-full mt-1"
      :placeholder="t('invoicing.unit_custom_placeholder')"
      :disabled="disabled"
      maxlength="32"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
  INVOICE_LINE_UNIT_CUSTOM,
  INVOICE_LINE_UNIT_PRESETS,
  isPresetInvoiceLineUnit,
} from '../../composables/useInvoiceLineUnits';

const model = defineModel<string>({ default: '' });

defineProps<{
  disabled?: boolean;
}>();

const { t } = useI18n();

const isCustom = computed(() => {
  const u = model.value?.trim() ?? '';
  return u !== '' && !isPresetInvoiceLineUnit(u);
});

const selectValue = computed(() => {
  const u = model.value?.trim() ?? '';
  if (!u) return '';
  if (isPresetInvoiceLineUnit(u)) return u;
  return INVOICE_LINE_UNIT_CUSTOM;
});

function onSelectChange(e: Event) {
  const v = (e.target as HTMLSelectElement).value;
  if (v === INVOICE_LINE_UNIT_CUSTOM) {
    if (isPresetInvoiceLineUnit(model.value ?? '')) {
      model.value = '';
    }
    return;
  }
  model.value = v;
}
</script>
