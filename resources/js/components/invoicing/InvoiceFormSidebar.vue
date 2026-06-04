<template>
  <aside class="w-full lg:w-56 shrink-0 space-y-2">
    <div
      v-if="documentStatus === 'draft'"
      class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"
    >
      {{ t('invoicing.draft_issue_hint') }}
    </div>

    <button
      v-if="canIssue"
      type="button"
      class="w-full invoicing-btn-primary"
      :disabled="busy"
      @click="$emit('issue')"
    >
      {{ t('invoicing.action_issue') }}
    </button>

    <button
      v-if="showCreateFinalInvoice"
      type="button"
      class="w-full px-4 py-2 border border-indigo-400 text-indigo-800 rounded-lg text-sm font-medium hover:bg-indigo-50 bg-white"
      :disabled="busy"
      @click="$emit('create-final-invoice')"
    >
      {{ t('invoicing.action_create_final_invoice') }}
    </button>

    <button
      v-if="showApproveQuote"
      type="button"
      class="w-full px-4 py-2 border border-emerald-400 text-emerald-800 rounded-lg text-sm font-medium hover:bg-emerald-50 bg-white"
      :disabled="busy"
      @click="$emit('approve-quote')"
    >
      {{ t('invoicing.action_approve_quote') }}
    </button>

    <button
      v-if="showRejectQuote"
      type="button"
      class="w-full px-4 py-2 border border-gray-400 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-50 bg-white"
      :disabled="busy"
      @click="$emit('reject-quote')"
    >
      {{ t('invoicing.action_reject_quote') }}
    </button>

    <button
      v-if="showCreateInvoiceFromQuote"
      type="button"
      class="w-full px-4 py-2 border border-indigo-400 text-indigo-800 rounded-lg text-sm font-medium hover:bg-indigo-50 bg-white"
      :disabled="busy"
      @click="$emit('create-invoice-from-quote')"
    >
      {{ t('invoicing.action_create_invoice_from_quote') }}
    </button>

    <button
      v-if="canSendEmail"
      type="button"
      class="w-full invoicing-btn-primary"
      :disabled="busy"
      @click="$emit('send-email')"
    >
      {{ t('invoicing.send') }}
    </button>

    <button
      v-if="canPdf"
      type="button"
      class="w-full invoicing-btn-secondary"
      :disabled="busy"
      @click="$emit('pdf')"
    >
      {{ t('invoicing.action_pdf') }}
    </button>

    <button
      v-if="canEuExport"
      type="button"
      class="w-full invoicing-btn-secondary"
      :disabled="busy"
      @click="$emit('isdoc')"
    >
      {{ t('invoicing.action_isdoc') }}
    </button>

    <button
      v-if="canEuExport"
      type="button"
      class="w-full invoicing-btn-secondary"
      :disabled="busy"
      @click="$emit('ubl')"
    >
      {{ t('invoicing.action_ubl') }}
    </button>

    <div v-if="documentStatus === 'paid'" class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm">
      <div class="text-emerald-800 font-medium mb-1">{{ t('invoicing.status_paid') }}</div>
      <div v-if="paidAt" class="text-xs text-gray-600">{{ formatDate(paidAt) }}</div>
      <div class="text-gray-900 font-semibold mt-1">{{ formatMoney(amountPaid) }}</div>
      <p class="text-xs text-gray-700 mt-2">
        <span class="text-gray-500">{{ t('invoicing.payment_method_label') }}:</span>
        {{ paymentMethodText }}
      </p>
      <p v-if="bankPaymentBookedDate" class="text-xs text-gray-500 mt-0.5">
        {{ bankPaymentBookedDate }}
      </p>
      <RouterLink
        v-if="bankMatch"
        :to="{ name: 'invoicing-payments', params: { companyId } }"
        class="text-xs text-indigo-700 hover:text-indigo-900 hover:underline mt-1 inline-block"
      >
        {{ t('invoicing.view_bank_payments') }}
      </RouterLink>
      <button
        v-if="!isLocked"
        type="button"
        class="mt-2 text-xs text-gray-500 hover:text-gray-800"
        :disabled="busy"
        @click="$emit('unmark-paid')"
      >
        {{ t('invoicing.remove_payment') }} ×
      </button>
    </div>

    <button
      v-else-if="canMarkPaid"
      type="button"
      class="w-full px-4 py-2 border border-emerald-400 text-emerald-800 rounded-lg text-sm hover:bg-emerald-50 bg-white"
      :disabled="busy"
      @click="$emit('mark-paid')"
    >
      {{ t('invoicing.action_mark_paid') }}
    </button>

    <div class="border-t border-gray-200 pt-2 space-y-1">
      <RouterLink
        v-if="canUpdate"
        :to="{ name: editRouteName, params: { companyId, documentId } }"
        class="sidebar-action"
      >
        <span class="sidebar-action-icon">✎</span>
        {{ t('invoicing.action_edit') }}
      </RouterLink>
      <button type="button" class="sidebar-action" :disabled="busy" @click="$emit('duplicate')">
        <span class="sidebar-action-icon">⧉</span>
        {{ t('invoicing.action_duplicate') }}
      </button>
      <button
        v-if="documentStatus === 'draft'"
        type="button"
        class="sidebar-action sidebar-action--danger"
        :disabled="busy"
        @click="$emit('delete')"
      >
        <span class="sidebar-action-icon">🗑</span>
        {{ t('invoicing.action_delete') }}
      </button>
      <button
        v-if="documentStatus === 'issued'"
        type="button"
        class="sidebar-action sidebar-action--danger"
        :disabled="busy"
        @click="$emit('cancel')"
      >
        <span class="sidebar-action-icon">×</span>
        {{ t('invoicing.action_cancel') }}
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
  defineProps<{
    mode?: 'show';
    companyId: string;
    documentId?: string;
    documentStatus: string;
    isLocked: boolean;
    canUpdate: boolean;
    canPdf: boolean;
    canEuExport?: boolean;
    canMarkPaid: boolean;
    canIssue?: boolean;
    canSendEmail?: boolean;
    showCreateFinalInvoice?: boolean;
    showApproveQuote?: boolean;
    showRejectQuote?: boolean;
    showCreateInvoiceFromQuote?: boolean;
    editRouteName?: string;
    busy: boolean;
    paidAt?: string | null;
    amountPaid?: number | null;
    bankMatch?: {
      match_type?: string;
      matched_at?: string;
      transaction?: { booked_at?: string };
    } | null;
    paidViaBtcpay?: boolean;
  }>(),
  {
    canSendEmail: false,
    canEuExport: false,
    canIssue: false,
    showCreateFinalInvoice: false,
    showApproveQuote: false,
    showRejectQuote: false,
    showCreateInvoiceFromQuote: false,
    editRouteName: 'invoicing-invoice-edit',
    bankMatch: null,
    paidViaBtcpay: false,
  }
);

const paymentMethodText = computed(() => {
  if (props.bankMatch) {
    return props.bankMatch.match_type === 'auto'
      ? t('invoicing.payment_method_bank_auto')
      : t('invoicing.payment_method_bank_manual');
  }
  if (props.paidViaBtcpay) {
    return t('invoicing.payment_method_btcpay');
  }

  return t('invoicing.payment_method_manual');
});

const bankPaymentBookedDate = computed(() => {
  const booked = props.bankMatch?.transaction?.booked_at;
  if (!booked) {
    return '';
  }
  return t('invoicing.payment_method_bank_payment_date', { date: formatDate(booked) });
});

defineEmits<{
  'send-email': [];
  pdf: [];
  isdoc: [];
  ubl: [];
  duplicate: [];
  delete: [];
  cancel: [];
  'mark-paid': [];
  'unmark-paid': [];
  'create-final-invoice': [];
  'approve-quote': [];
  'reject-quote': [];
  'create-invoice-from-quote': [];
  issue: [];
}>();

const { t, locale } = useI18n();

function formatMoney(n: number | null | undefined) {
  return Number(n || 0).toLocaleString(locale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(iso: string) {
  const d = new Date(iso.includes('T') ? iso : `${iso}T12:00:00`);
  return d.toLocaleDateString(locale.value);
}
</script>

<style scoped>
.sidebar-action {
  @apply w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-100 text-left disabled:opacity-50;
}
.sidebar-action--danger {
  @apply text-red-600 hover:bg-red-50;
}
.sidebar-action-icon {
  @apply w-5 text-center text-gray-400;
}
</style>
