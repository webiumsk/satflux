<template>
  <div
    v-if="transaction"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="bank-expense-match-title"
    @click.self="requestClose"
    @keydown.esc.prevent="requestClose"
    @keydown.tab="trapFocus"
  >
    <div ref="panel" tabindex="-1" class="bg-white rounded-lg shadow-xl max-w-lg w-full p-4 space-y-3 max-h-[90vh] overflow-auto">
      <h3 id="bank-expense-match-title" class="font-semibold">{{ t('invoicing.bank_match_expense') }}</h3>
      <p class="text-sm text-gray-700">
        {{ transaction.amount }} {{ transaction.currency }} · {{ transaction.variable_symbol || transaction.reference || '-' }}
      </p>
      <p class="text-sm text-gray-600">{{ t('invoicing.bank_match_expense_hint') }}</p>
      <p v-if="loading" role="status" class="text-sm">{{ t('common.loading') }}</p>
      <p v-else-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
      <ul v-else class="space-y-2 text-sm">
        <li v-for="suggestion in suggestions" :key="suggestion.expense.id" class="flex flex-wrap items-center justify-between gap-3 rounded border p-3">
          <div class="min-w-0">
            <p class="font-medium break-words">{{ suggestion.expense.internal_number }} · {{ suggestion.expense.external_number || suggestion.expense.title }}</p>
            <p>{{ suggestion.expense.total }} {{ suggestion.expense.currency }}</p>
            <p class="text-xs text-gray-600">{{ t(`invoicing.bank_expense_reason_${suggestion.reason}`) }}</p>
          </div>
          <button type="button" class="invoicing-btn-primary shrink-0" :disabled="saving" @click="emit('confirm', suggestion.expense.id)">
            {{ t('invoicing.bank_match_confirm') }}
          </button>
        </li>
      </ul>
      <p v-if="!loading && !error && suggestions.length === 0" class="text-sm text-gray-600">{{ t('invoicing.bank_no_expense_suggestions') }}</p>
      <button type="button" class="invoicing-btn-secondary w-full" :disabled="saving" @click="requestClose">{{ t('common.cancel') }}</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BankExpenseMatchSuggestion } from '../../evolu/bankExpenseManualMatching';
import type { BankTxApiRow } from '../../evolu/bankMap';

const props = defineProps<{
  transaction: BankTxApiRow | null;
  suggestions: BankExpenseMatchSuggestion[];
  loading: boolean;
  saving: boolean;
  error: string;
}>();
const emit = defineEmits<{ close: []; confirm: [expenseId: string] }>();
const { t } = useI18n();
const panel = ref<HTMLElement | null>(null);
let previousFocus: HTMLElement | null = null;
watch(() => props.transaction?.id, async (id) => {
  if (id) {
    previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    await nextTick();
    panel.value?.focus();
  } else previousFocus?.focus();
}, { immediate: true });
watch(() => props.saving, (saving) => {
  if (saving && props.transaction) panel.value?.focus();
}, { flush: 'sync' });
onBeforeUnmount(() => previousFocus?.focus());

/** Keep a dispatched financial operation visible until its outcome is known. */
function requestClose() {
  if (!props.saving) emit('close');
}

/** Wrap keyboard navigation within the modal, including its initially focused panel. */
function trapFocus(event: KeyboardEvent) {
  const buttons = Array.from(panel.value?.querySelectorAll<HTMLButtonElement>('button:not([disabled])') ?? []);
  const first = buttons[0];
  const last = buttons[buttons.length - 1];
  if (!first || !last) { event.preventDefault(); panel.value?.focus(); return; }
  if (event.shiftKey && (document.activeElement === first || document.activeElement === panel.value)) {
    event.preventDefault(); last.focus();
  } else if (!event.shiftKey && (document.activeElement === last || document.activeElement === panel.value)) {
    event.preventDefault(); first.focus();
  }
}
</script>
