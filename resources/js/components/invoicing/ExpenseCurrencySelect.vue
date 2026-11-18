<template>
  <select v-model="currency" required class="invoicing-sf-input w-full">
    <option disabled value="">{{ t('invoicing.expense_select_invoice_currency') }}</option>
    <option v-for="code in options" :key="code" :value="code">{{ code }}</option>
  </select>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { currencies } from '../../data/currencies';

const { t } = useI18n();
const props = defineProps<{ previousInvoiceCurrency?: string | null }>();
const currency = defineModel<string>({ required: true });
const invoiceCurrencies = currencies.map(entry => entry.code).filter(code => code.length === 3);
const options = computed(() => {
  const codes = ['EUR', 'CZK', 'USD'];
  if (props.previousInvoiceCurrency != null) {
    codes.push(...invoiceCurrencies, props.previousInvoiceCurrency);
  }
  return [...new Set([...codes, currency.value].filter(Boolean))];
});
</script>
