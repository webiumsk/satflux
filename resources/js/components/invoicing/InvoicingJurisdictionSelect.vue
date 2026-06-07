<template>
  <select :value="modelValue" class="invoicing-sf-input" @change="onChange">
    <option v-for="j in COMPANY_JURISDICTION_VALUES" :key="j" :value="j">
      {{ t(`invoicing.${JURISDICTION_I18N_KEYS[j]}`) }}
    </option>
  </select>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import {
  COMPANY_JURISDICTION_VALUES,
  JURISDICTION_I18N_KEYS,
  type CompanyJurisdictionValue,
} from '../../config/companyJurisdiction';

defineProps<{
  modelValue: CompanyJurisdictionValue | string;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: CompanyJurisdictionValue];
  change: [];
}>();

const { t } = useI18n();

function onChange(e: Event) {
  const value = (e.target as HTMLSelectElement).value as CompanyJurisdictionValue;
  emit('update:modelValue', value);
  emit('change');
}
</script>
