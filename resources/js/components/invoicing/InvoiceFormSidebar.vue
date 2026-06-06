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
        v-if="canUnmarkPaid"
        type="button"
        class="mt-2 text-xs text-amber-800 hover:text-amber-950 inline-flex items-center gap-1"
        :disabled="busy"
        @click="$emit('unmark-paid')"
      >
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
        </svg>
        {{ t('invoicing.remove_payment') }}
      </button>
      <p v-if="canUnmarkPaid" class="text-xs text-gray-500 mt-1">
        {{ t('invoicing.unmark_paid_edit_hint') }}
      </p>
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
        <svg class="sidebar-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
        {{ t('invoicing.action_edit') }}
      </RouterLink>
      <button type="button" class="sidebar-action" :disabled="busy" @click="$emit('duplicate')">
        <svg class="sidebar-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        {{ t('invoicing.action_duplicate') }}
      </button>
      <button
        v-if="canDelete"
        type="button"
        class="sidebar-action sidebar-action--danger"
        :disabled="busy"
        @click="$emit('delete')"
      >
        <svg class="sidebar-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        {{ t('invoicing.action_delete') }}
      </button>
      <button
        v-if="canCancel"
        type="button"
        class="sidebar-action sidebar-action--danger"
        :disabled="busy"
        @click="$emit('cancel')"
      >
        <svg class="sidebar-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
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
    canDelete?: boolean;
    canCancel?: boolean;
    canUnmarkPaid?: boolean;
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
    canDelete: false,
    canCancel: false,
    canUnmarkPaid: false,
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
