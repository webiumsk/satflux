<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
    role="dialog"
    aria-modal="true"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-xl p-6 relative max-h-[90vh] overflow-y-auto">
      <button
        type="button"
        class="absolute top-3 right-3 text-gray-400 hover:text-gray-700 text-2xl leading-none"
        :title="t('common.close')"
        @click="$emit('close')"
      >
        ×
      </button>

      <h3 class="text-lg font-semibold text-gray-900 pr-8">{{ t('invoicing.expense_isdoc_detected_title') }}</h3>
      <p class="mt-3 text-sm text-gray-600 leading-relaxed">
        {{ bodyText }}
      </p>

      <p v-if="quotaHint" class="mt-2 text-xs text-gray-500">{{ quotaHint }}</p>

      <div v-if="!canExtract && packs.length" class="mt-5 space-y-3">
        <p class="text-sm font-medium text-gray-800">{{ t('invoicing.expense_isdoc_packs_title') }}</p>
        <p class="text-xs text-gray-500">{{ t('invoicing.expense_isdoc_packs_vat') }}</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <button
            v-for="pack in packs"
            :key="pack.credits"
            type="button"
            class="rounded-lg border border-gray-200 p-3 text-center hover:border-indigo-400 hover:bg-indigo-50 transition-colors"
            :disabled="purchasing"
            @click="$emit('purchase', pack.credits)"
          >
            <span class="block text-lg font-semibold text-gray-900">{{ pack.credits }}</span>
            <span class="block text-xs text-gray-500">{{ t('invoicing.expense_isdoc_pack_docs') }}</span>
            <span class="block text-sm font-medium text-indigo-700 mt-1">{{ formatEur(pack.price_eur) }}</span>
          </button>
        </div>
        <p class="text-xs text-gray-500">{{ t('invoicing.expense_isdoc_packs_pay_btcpay') }}</p>
      </div>

      <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-end">
        <button type="button" class="invoicing-btn-secondary" :disabled="extracting" @click="$emit('skip')">
          {{ t('invoicing.expense_isdoc_extract_skip') }}
        </button>
        <button
          v-if="canExtract"
          type="button"
          class="invoicing-btn-primary"
          :disabled="extracting"
          @click="$emit('confirm')"
        >
          {{ extracting ? t('common.loading') : t('invoicing.expense_isdoc_extract_confirm') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

export type IsdocPack = { credits: number; price_eur: number };

export type IsdocExtractQuota = {
  unlimited: boolean;
  can_extract: boolean;
  free_remaining?: number;
  free_limit?: number;
  purchased_credits?: number;
  packs?: IsdocPack[];
  used?: number;
  limit?: number | null;
  remaining?: number | null;
};

const props = defineProps<{
  open: boolean;
  extracting?: boolean;
  purchasing?: boolean;
  quota: IsdocExtractQuota | null;
}>();

defineEmits<{
  close: [];
  skip: [];
  confirm: [];
  purchase: [credits: number];
}>();

const { t } = useI18n();

const canExtract = computed(() => props.quota?.can_extract ?? true);

const packs = computed(() => props.quota?.packs ?? []);

const bodyText = computed(() => {
  if (props.quota?.unlimited) {
    return t('invoicing.expense_isdoc_detected_body_unlimited');
  }
  if (!canExtract.value) {
    return t('invoicing.expense_isdoc_quota_exceeded');
  }
  const freeRemaining = props.quota?.free_remaining ?? props.quota?.remaining ?? 0;
  const freeLimit = props.quota?.free_limit ?? props.quota?.limit ?? 20;
  const purchased = props.quota?.purchased_credits ?? 0;
  if (purchased > 0 && freeRemaining > 0) {
    return t('invoicing.expense_isdoc_detected_body_mixed', {
      freeRemaining,
      freeLimit,
      purchased,
    });
  }
  if (purchased > 0) {
    return t('invoicing.expense_isdoc_detected_body_purchased', { purchased });
  }
  return t('invoicing.expense_isdoc_detected_body', { remaining: freeRemaining, limit: freeLimit });
});

const quotaHint = computed(() => {
  if (props.quota?.unlimited) return '';
  const parts: string[] = [];
  const freeRem = props.quota?.free_remaining ?? 0;
  const purchased = props.quota?.purchased_credits ?? 0;
  if (freeRem > 0) parts.push(t('invoicing.expense_isdoc_hint_free', { count: freeRem }));
  if (purchased > 0) parts.push(t('invoicing.expense_isdoc_hint_purchased', { count: purchased }));
  return parts.join(' · ');
});

function formatEur(amount: number) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' }).format(amount);
}
</script>
