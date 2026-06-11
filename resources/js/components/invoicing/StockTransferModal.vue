<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="emit('close')">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-900">{{ t('invoicing.stock_transfer_title') }}</h2>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.stock_transfer_from') }}</label>
        <select v-model="fromId" class="invoicing-sf-input w-full">
          <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
        </select>
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.stock_transfer_to') }}</label>
        <select v-model="toId" class="invoicing-sf-input w-full">
          <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
        </select>
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.stock_transfer_quantity') }}</label>
        <input v-model.number="quantity" type="number" min="0.0001" step="any" class="invoicing-sf-input w-full" />
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.stock_history_note') }}</label>
        <input v-model="note" type="text" class="invoicing-sf-input w-full" />
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="invoicing-btn-secondary" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="button" class="invoicing-btn-primary" :disabled="saving" @click="submit">
          {{ t('invoicing.stock_transfer_submit') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WarehouseRow } from '../../composables/useCompanyWarehouse';
import api from '../../services/api';

const props = defineProps<{
  open: boolean;
  companyId: string;
  stockItemId: string;
  warehouses: WarehouseRow[];
}>();

const emit = defineEmits<{
  close: [];
  transferred: [];
}>();

const { t } = useI18n();

const fromId = ref('');
const toId = ref('');
const quantity = ref(1);
const note = ref('');
const saving = ref(false);
const error = ref('');

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return;
    error.value = '';
    const active = props.warehouses.filter((w) => w.is_active);
    fromId.value = active[0]?.id ?? '';
    toId.value = active[1]?.id ?? active[0]?.id ?? '';
    quantity.value = 1;
    note.value = '';
  }
);

async function submit() {
  if (!fromId.value || !toId.value || fromId.value === toId.value) {
    error.value = t('invoicing.stock_transfer_invalid');
    return;
  }
  const qty = Number(quantity.value);
  if (!Number.isFinite(qty) || qty <= 0) {
    error.value = t('invoicing.stock_transfer_invalid_quantity');
    return;
  }
  saving.value = true;
  error.value = '';
  try {
    await api.post(`/invoicing/companies/${props.companyId}/stock-items/${props.stockItemId}/transfer`, {
      from_warehouse_id: fromId.value,
      to_warehouse_id: toId.value,
      quantity: qty,
      note: note.value.trim() || null,
    });
    emit('transferred');
    emit('close');
  } catch (e: unknown) {
    error.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message || t('errors.generic');
  } finally {
    saving.value = false;
  }
}
</script>
