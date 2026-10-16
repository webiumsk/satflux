<template>
  <section
    class="rounded-xl border p-4 text-sm space-y-3"
    :class="status === 'blocked' ? 'border-amber-500/30 bg-amber-500/10 text-amber-100' : 'border-gray-600 bg-gray-900/40 text-gray-300'"
    role="status"
    aria-live="polite"
    data-testid="cashu-wallet-safety"
  >
    <h2 class="font-semibold">{{ t('stores.cashu_wallet_safety_title') }}</h2>
    <p data-testid="cashu-wallet-safety-status">{{ t(`stores.cashu_wallet_safety_${status}`) }}</p>
    <dl v-if="status !== 'unavailable'" class="grid gap-2 sm:grid-cols-2">
      <div>
        <dt>{{ t('stores.cashu_wallet_safety_payment_records') }}</dt>
        <dd class="font-semibold tabular-nums" data-testid="cashu-safety-payment-count">{{ paymentCount ?? t('stores.cashu_wallet_safety_unknown') }}</dd>
      </div>
      <div>
        <dt>{{ t('stores.cashu_wallet_safety_change_records') }}</dt>
        <dd class="font-semibold tabular-nums" data-testid="cashu-safety-change-count">{{ changeCount ?? t('stores.cashu_wallet_safety_unknown') }}</dd>
      </div>
    </dl>
    <template v-if="status !== 'unavailable'">
      <dl v-if="paymentPartition" class="grid gap-2 sm:grid-cols-3" data-testid="cashu-safety-payment-breakdown">
        <div><dt>{{ t('stores.cashu_safety_failed_payments') }}</dt><dd class="font-semibold tabular-nums" data-testid="cashu-safety-failed-count">{{ paymentPartition.failed }}</dd></div>
        <div><dt>{{ t('stores.cashu_safety_other_non_terminal') }}</dt><dd class="font-semibold tabular-nums" data-testid="cashu-safety-other-count">{{ paymentPartition.other }}</dd></div>
        <div><dt>{{ t('stores.cashu_safety_terminal_recovery') }}</dt><dd class="font-semibold tabular-nums" data-testid="cashu-safety-terminal-count">{{ paymentPartition.terminal }}</dd></div>
      </dl>
      <p v-else data-testid="cashu-safety-breakdown-unknown">{{ t('stores.cashu_safety_breakdown_unknown') }}</p>
      <p class="text-xs opacity-80">{{ t('stores.cashu_safety_breakdown_hint') }}</p>
      <p class="text-xs opacity-80">{{ t('stores.cashu_payment_diagnostics_hint') }}</p>
    </template>
    <p v-if="status === 'blocked' && !hasReportedRecords" data-testid="cashu-safety-unexplained">{{ t('stores.cashu_wallet_safety_unexplained') }}</p>
    <p v-if="status === 'blocked'">{{ t('stores.cashu_wallet_safety_help') }}</p>
    <p>{{ t('stores.cashu_wallet_safety_snapshot') }}</p>
    <p v-if="status !== 'unavailable'" class="text-xs opacity-80">{{ t('stores.cashu_wallet_safety_records_hint') }}</p>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CashuWalletSafety } from '../../../services/api';

const props = defineProps<{
  safety?: unknown;
  /** A rejected write remains blocked/unavailable even when diagnostics are missing or inconsistent. */
  refusalStatus?: 'blocked' | 'unavailable' | null;
}>();
const { t } = useI18n();
const snapshot = computed(() => props.safety && typeof props.safety === 'object'
  ? props.safety as Partial<CashuWalletSafety> : null);
const status = computed(() => {
  if (props.refusalStatus) return props.refusalStatus;
  const value = snapshot.value?.status;
  return value === 'safe' || value === 'blocked' ? value : 'unavailable';
});
function recordCount(value: unknown): number | null {
  return typeof value === 'number' && Number.isInteger(value) && value >= 0 && value <= 2147483647 ? value : null;
}
const paymentCount = computed(() => recordCount(snapshot.value?.unresolved_payments));
const changeCount = computed(() => recordCount(snapshot.value?.unresolved_change));
const paymentPartition = computed(() => {
  const failed = recordCount(snapshot.value?.failed_payments);
  const other = recordCount(snapshot.value?.other_non_terminal_payments);
  const terminal = recordCount(snapshot.value?.terminal_recovery_payments);
  return failed !== null && other !== null && terminal !== null && paymentCount.value !== null && failed + other + terminal === paymentCount.value
    ? { failed, other, terminal } : null;
});
const hasReportedRecords = computed(() => (paymentCount.value ?? 0) > 0 || (changeCount.value ?? 0) > 0);
</script>
