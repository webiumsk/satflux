<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    role="dialog"
    aria-modal="true"
    @click.self="emit('close')"
  >
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full overflow-hidden">
      <div class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between">
        <h3 class="font-semibold">{{ t('invoicing.bank_create_expense_title') }}</h3>
        <button type="button" class="text-white/80 hover:text-white text-2xl leading-none" @click="emit('close')">
          ×
        </button>
      </div>

      <form class="p-5 space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="invoicing-sf-label">
            {{ t('invoicing.expense_col_title') }} <span class="text-red-500">*</span>
          </label>
          <input v-model="form.title" type="text" class="invoicing-sf-input w-full" required />
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">
              {{ t('invoicing.expense_col_total') }} ({{ currency }}) <span class="text-red-500">*</span>
            </label>
            <input v-model.number="form.total" type="number" min="0.01" step="0.01" class="invoicing-sf-input w-full" required />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.variable_symbol') }}</label>
            <input v-model="form.variable_symbol" type="text" class="invoicing-sf-input w-full" />
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.bank_expense_supplier') }}</label>
            <input v-model="form.supplier" type="text" class="invoicing-sf-input w-full" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{ t('invoicing.bank_expense_category') }}</label>
            <input v-model="form.category" type="text" class="invoicing-sf-input w-full" />
          </div>
        </div>

        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.bank_expense_payment_method') }}</label>
          <input type="text" class="invoicing-sf-input w-full bg-gray-50" readonly :value="t('invoicing.bank_expense_payment_bank')" />
        </div>

        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.expense_internal_note') }}</label>
          <textarea v-model="form.internal_note" rows="3" class="invoicing-sf-input w-full"></textarea>
        </div>

        <p v-if="submitError" class="text-sm text-red-600">{{ submitError }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="invoicing-btn-secondary" @click="emit('close')">
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="invoicing-btn-primary" :disabled="saving">
            {{ saving ? t('common.loading') : t('common.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

export type BankExpenseDraft = {
  title: string;
  total: number;
  variable_symbol: string;
  supplier: string;
  category: string;
  internal_note: string;
  issue_date: string;
};

const props = defineProps<{
  open: boolean;
  currency: string;
  initial: BankExpenseDraft;
  saving?: boolean;
  error?: string;
}>();

const emit = defineEmits<{
  close: [];
  submit: [BankExpenseDraft];
}>();

const { t } = useI18n();

const form = reactive<BankExpenseDraft>({ ...props.initial });
const submitError = ref('');

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return;
    Object.assign(form, props.initial);
    submitError.value = props.error || '';
  },
);

watch(
  () => props.error,
  (value) => {
    submitError.value = value || '';
  },
);

function onSubmit() {
  emit('submit', { ...form });
}
</script>
