<template>
  <section
    class="invoicing-card w-full max-w-full p-4 text-sm sm:ml-auto sm:w-fit sm:min-w-[20rem]"
    aria-labelledby="expense-list-summary-heading"
  >
    <h2 id="expense-list-summary-heading" class="font-semibold text-gray-900">
      {{ t('invoicing.expense_summary_title') }}
    </h2>
    <p class="mt-1 text-gray-600">{{ t('invoicing.expense_summary_count', { count: summary.count }) }}</p>
    <div
      v-for="group in summary.currencies"
      :key="group.currency"
      class="mt-3 border-t border-gray-200 pt-3"
    >
      <h3 v-if="summary.currencies.length > 1" class="mb-2 font-medium text-gray-700">
        {{ group.currency }} · {{ t('invoicing.expense_summary_count', { count: group.count }) }}
      </h3>
      <dl class="space-y-2">
        <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
          <dt class="text-gray-600">{{ t('invoicing.expense_summary_total') }}</dt>
          <dd class="ml-auto max-w-full break-all text-right font-semibold tabular-nums text-gray-900">
            {{ formatExpenseSummaryAmount(group.total, group.currency, locale) }}
          </dd>
        </div>
        <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
          <dt class="text-gray-600">{{ t('invoicing.expense_summary_unpaid') }}</dt>
          <dd class="ml-auto max-w-full break-all text-right font-semibold tabular-nums text-gray-900">
            {{ formatExpenseSummaryAmount(group.unpaid_total, group.currency, locale) }}
          </dd>
        </div>
      </dl>
    </div>
  </section>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatExpenseSummaryAmount, type ExpenseSummary } from '../../utils/expenseSummary';

defineProps<{ summary: ExpenseSummary }>();
const { t, locale } = useI18n();
</script>
