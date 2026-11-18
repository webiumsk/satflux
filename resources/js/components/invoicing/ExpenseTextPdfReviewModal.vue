<template>
  <Teleport to="body">
    <div v-if="open" ref="dialogRoot" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 p-4"
      role="dialog" aria-modal="true" :aria-labelledby="titleId" :aria-describedby="bodyId" tabindex="-1"
      @keydown="onDialogKeydown" @click.self="cancel">
      <div class="relative max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
        <button type="button" class="absolute right-3 top-3 rounded px-2 text-2xl text-gray-500"
          :aria-label="t('common.close')" @click="cancel">×</button>
        <h3 :id="titleId" class="pr-8 text-lg font-semibold text-gray-900">{{ t('invoicing.expense_pdf_text_review_title') }}</h3>
        <p :id="bodyId" class="mt-3 text-sm leading-relaxed text-gray-600">{{ t('invoicing.expense_pdf_text_review_body') }}</p>
        <dl v-if="rows.length" class="mt-4 divide-y divide-gray-100 text-sm">
          <div v-for="row in rows" :key="row.field" class="grid gap-1 py-2 sm:grid-cols-2 sm:gap-4">
            <dt class="text-gray-500">{{ t(row.label) }}</dt>
            <dd class="whitespace-pre-wrap break-words text-gray-900">{{ row.value }}</dd>
          </div>
        </dl>
        <p v-else class="mt-4 text-sm text-gray-600">{{ t('invoicing.expense_pdf_text_review_empty') }}</p>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
          <button type="button" class="invoicing-btn-secondary" @click="cancel">{{ t('invoicing.expense_pdf_text_review_skip') }}</button>
          <button v-if="rows.length" type="button" class="invoicing-btn-primary" @click="confirm">{{ t('invoicing.expense_pdf_text_review_save') }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialogFocus } from '../../composables/useDialogFocus';
import type { ExpenseSavePayload } from '../../evolu/expenseCrud';

export type ExpenseTextPdfReviewValues = Pick<ExpenseSavePayload,
  'supplier_name' | 'supplier_iban' | 'title' | 'external_number' | 'variable_symbol' | 'constant_symbol' | 'specific_symbol'
  | 'issue_date' | 'delivery_date' | 'due_date' | 'total' | 'currency' | 'internal_note'>;
const props = defineProps<{ open: boolean; values: ExpenseTextPdfReviewValues | null }>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();
const { t } = useI18n();
const dialogRoot = ref<HTMLElement | null>(null);
const titleId = useId();
const bodyId = useId();
const fields = [
  ['supplier_name', 'invoicing.expense_supplier_name'], ['supplier_iban', 'invoicing.expense_supplier_iban'],
  ['title', 'invoicing.expense_col_title'], ['external_number', 'invoicing.expense_col_external'],
  ['variable_symbol', 'invoicing.variable_symbol'], ['constant_symbol', 'invoicing.constant_symbol'],
  ['specific_symbol', 'invoicing.specific_symbol'], ['issue_date', 'invoicing.expense_import_field_issue_date'],
  ['delivery_date', 'invoicing.expense_col_delivery'], ['due_date', 'invoicing.expense_col_due'],
  ['total', 'invoicing.expense_col_total'], ['currency', 'invoicing.expense_import_field_currency'],
  ['internal_note', 'invoicing.expense_internal_note'],
] as const;
const rows = computed(() => fields.flatMap(([field, label]) => {
  const value = props.values?.[field];
  return value == null || String(value).trim() === '' ? [] : [{ field, label, value: String(value) }];
}));
function cancel() { if (props.open) emit('cancel'); }
function confirm() { if (props.open && rows.value.length) emit('confirm'); }
const { onDialogKeydown } = useDialogFocus(dialogRoot, () => props.open, cancel);
</script>
