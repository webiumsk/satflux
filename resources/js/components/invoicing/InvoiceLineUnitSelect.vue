<template>
  <div class="flex flex-col gap-1 w-full min-w-[4.5rem]">
    <select
      :value="selectValue"
      class="invoicing-sf-input-table text-center w-full"
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
      ref="customInput"
      v-model="model"
      type="text"
      class="invoicing-sf-input-table text-center w-full"
      :placeholder="t('invoicing.unit_custom_placeholder')"
      :disabled="disabled"
      maxlength="32"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
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
const customMode = ref(false);
const customInput = ref<HTMLInputElement | null>(null);

function syncCustomModeFromModel(value: string | undefined) {
  const u = value?.trim() ?? '';
  if (u !== '' && !isPresetInvoiceLineUnit(u)) {
    customMode.value = true;
    return;
  }
  if (isPresetInvoiceLineUnit(u)) {
    customMode.value = false;
  }
}

watch(() => model.value, syncCustomModeFromModel, { immediate: true });

const isCustom = computed(() => customMode.value);

const selectValue = computed(() => {
  if (customMode.value) {
    return INVOICE_LINE_UNIT_CUSTOM;
  }
  const u = model.value?.trim() ?? '';
  if (!u) {
    return '';
  }
  if (isPresetInvoiceLineUnit(u)) {
    return u;
  }
  return INVOICE_LINE_UNIT_CUSTOM;
});

async function onSelectChange(e: Event) {
  const v = (e.target as HTMLSelectElement).value;
  if (v === INVOICE_LINE_UNIT_CUSTOM) {
    customMode.value = true;
    if (isPresetInvoiceLineUnit(model.value ?? '')) {
      model.value = '';
    }
    await nextTick();
    customInput.value?.focus();
    return;
  }
  customMode.value = false;
  model.value = v;
}
</script>
