<template>
  <aside v-if="suggestions.length || failed" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status" aria-live="polite">
    <template v-if="suggestions.length">
      <p class="font-semibold">{{ t('invoicing.expense_duplicate_warning') }}</p>
      <p class="mt-1">{{ t('invoicing.expense_duplicate_advisory') }}</p>
      <ul class="mt-2 space-y-1">
        <li v-for="suggestion in suggestions" :key="suggestion.expense.id">
          <RouterLink
            :to="{ name: 'invoicing-expense-show', params: { companyId, expenseId: suggestion.expense.id } }"
            class="font-medium underline"
            target="_blank"
            rel="noopener noreferrer"
          >{{ suggestion.expense.internal_number }}</RouterLink>
          · {{ suggestion.expense.external_number || suggestion.expense.title }}
          · {{ suggestion.expense.total }} {{ suggestion.expense.currency }}
          <span class="block text-xs">{{ t(`invoicing.expense_duplicate_reason_${suggestion.reason}`) }}</span>
        </li>
      </ul>
    </template>
    <p v-else>{{ t('invoicing.expense_duplicate_check_failed') }}</p>
  </aside>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Evolu } from '@evolu/common/local-first';
import type { InvoicingLocalSchema } from '../../evolu/schema';
import type { EvoluExpenseRow } from '../../evolu/expenseMap';
import { bankPaymentCurrency, positiveBankAmount } from '../../evolu/bankPaymentValues';
import { companyShareRoutingKey, waitCompanyShareRegistryReady } from '../../evolu/companyShareRegistry';
import { resolveMutationOwner } from '../../evolu/ownerScope';
import { localExpenseDuplicateSuggestions, type ExpenseDuplicateDraft, type ExpenseDuplicateSuggestion } from '../../evolu/expenseDuplicateSuggestions';
import { invoicingApi } from '../../services/api';

const props = defineProps<{
  companyId: string;
  draft: ExpenseDuplicateDraft;
  excludeId?: string | undefined;
  localFirst: boolean;
  localRows: readonly EvoluExpenseRow[];
  evolu: Evolu<InvoicingLocalSchema> | null;
}>();
const { t } = useI18n();
const suggestions = ref<ExpenseDuplicateSuggestion[]>([]);
const failed = ref(false);
let request = 0;
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

/** Invalidate every old read, including adapters that ignore AbortSignal. */
function cancel() {
  request++;
  clearTimeout(timer);
  controller?.abort();
}
onBeforeUnmount(cancel);
watch(() => [props.companyId, props.excludeId, props.localFirst, props.draft,
  props.localRows, companyShareRoutingKey(props.companyId)], () => {
  cancel();
  suggestions.value = [];
  failed.value = false;
  const { companyId, excludeId, localFirst, draft, localRows, evolu } = props;
  if (!companyId || !positiveBankAmount(String(draft.total)) || !bankPaymentCurrency(draft.currency)
    || (!draft.external_number?.trim() && !(draft.title?.trim() && draft.issue_date))) return;
  const generation = request;
  const captured = { ...draft };
  const readController = new AbortController();
  controller = readController;
  timer = setTimeout(async () => {
    const deadline = setTimeout(() => readController.abort(), 10_000);
    try {
      let rows: ExpenseDuplicateSuggestion[];
      if (localFirst) {
        if (!evolu) throw new Error('local_context_unavailable');
        await waitCompanyShareRegistryReady(readController.signal);
        const owner = await evolu.appOwner;
        if (generation !== request) return;
        const ownerId = resolveMutationOwner('expense', { companyId }, undefined) ?? owner.id;
        rows = localExpenseDuplicateSuggestions(companyId, ownerId, captured, localRows, excludeId);
      } else {
        rows = await invoicingApi.expenses.duplicateSuggestions<ExpenseDuplicateSuggestion>(companyId,
          { ...captured, ...(excludeId ? { exclude_id: excludeId } : {}) }, { signal: readController.signal });
      }
      if (generation === request) suggestions.value = rows;
    } catch {
      if (generation === request) failed.value = true;
    } finally {
      clearTimeout(deadline);
    }
  }, localFirst ? 0 : 300);
}, { deep: true, immediate: true });
</script>
