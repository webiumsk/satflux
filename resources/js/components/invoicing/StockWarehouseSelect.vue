<template>
  <select
    :value="modelValue ?? ''"
    class="invoicing-sf-input-table w-full min-w-[120px]"
    :disabled="disabled"
    @change="onChange"
  >
    <option v-if="showEmpty" value="">{{ placeholder || t('invoicing.warehouse_select') }}</option>
    <option v-for="w in activeWarehouses" :key="w.id" :value="w.id">
      {{ w.name }}{{ w.is_default ? ` (${t('invoicing.warehouse_default_badge')})` : '' }}
    </option>
  </select>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WarehouseRow } from '../../composables/useCompanyWarehouse';

const props = withDefaults(
  defineProps<{
    modelValue: string | null;
    warehouses: WarehouseRow[];
    disabled?: boolean;
    placeholder?: string;
    showEmpty?: boolean;
  }>(),
  {
    disabled: false,
    placeholder: '',
    showEmpty: false,
  }
);

const emit = defineEmits<{
  'update:modelValue': [value: string | null];
}>();

const { t } = useI18n();

const activeWarehouses = computed(() => props.warehouses.filter((w) => w.is_active));

function onChange(e: Event) {
  const value = (e.target as HTMLSelectElement).value;
  emit('update:modelValue', value || null);
}
</script>
