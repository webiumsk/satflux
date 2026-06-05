<template>
  <aside class="w-full lg:w-56 shrink-0 space-y-2.5">
    <div
      v-if="statusBanner"
      class="rounded-lg px-3 py-2 text-sm font-medium text-center text-white"
      :class="statusBanner.class"
    >
      {{ statusBanner.label }}
    </div>

    <div v-if="status === 'paid' && paidAt" class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm">
      <div class="text-emerald-800 font-medium mb-1">{{ t('invoicing.expense_status_paid') }}</div>
      <div class="text-xs text-gray-600">{{ formatDate(paidAt) }}</div>
      <div class="text-gray-900 font-semibold mt-1">{{ formatMoney(total, currency) }}</div>
      <button
        type="button"
        class="mt-2 text-xs text-gray-500 hover:text-gray-800 inline-flex items-center gap-1"
        :disabled="busy"
        @click="$emit('unmark-paid')"
      >
        {{ t('invoicing.expense_action_unmark_paid') }}
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <button
      v-else-if="status === 'recorded'"
      type="button"
      class="w-full px-4 py-2.5 border border-emerald-400 text-emerald-800 rounded-lg text-sm font-medium hover:bg-emerald-50 bg-white flex items-center justify-center gap-2"
      :disabled="busy"
      @click="$emit('mark-paid')"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      {{ t('invoicing.expense_bulk_mark_paid') }}
    </button>

    <RouterLink
      v-if="status !== 'cancelled'"
      :to="{ name: 'invoicing-payments', params: { companyId } }"
      class="w-full px-4 py-2.5 border border-indigo-300 text-indigo-800 rounded-lg text-sm font-medium hover:bg-indigo-50 bg-white flex items-center justify-center gap-2 text-center"
    >
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
      </svg>
      {{ t('invoicing.expense_show_match_payment') }}
    </RouterLink>

    <button
      type="button"
      class="w-full px-4 py-2.5 border border-gray-300 text-gray-500 rounded-lg text-sm bg-gray-50 cursor-not-allowed opacity-70 flex items-center justify-center gap-2"
      disabled
      :title="t('invoicing.expense_show_export_bank_soon')"
    >
      <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
      </svg>
      {{ t('invoicing.expense_show_export_bank') }}
    </button>

    <div class="border-t border-gray-200 pt-2 space-y-0.5">
      <RouterLink
        v-if="status !== 'cancelled'"
        :to="{ name: 'invoicing-expense-edit', params: { companyId, expenseId } }"
        class="sidebar-action"
      >
        <svg class="sidebar-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
        {{ t('invoicing.action_change') }}
      </RouterLink>
      <button type="button" class="sidebar-action" :disabled="busy" @click="$emit('duplicate')">
        <svg class="sidebar-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        {{ t('invoicing.expense_action_duplicate') }}
      </button>
      <button
        v-if="status !== 'cancelled'"
        type="button"
        class="sidebar-action sidebar-action--danger"
        :disabled="busy"
        @click="$emit('cancel')"
      >
        <svg class="sidebar-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        {{ t('invoicing.expense_action_cancel') }}
      </button>
    </div>

    <div v-if="status !== 'cancelled'" class="relative pt-1">
      <button
        type="button"
        class="w-full px-4 py-2.5 border-2 border-indigo-500 text-indigo-700 rounded-lg text-sm font-semibold hover:bg-indigo-50 bg-white flex items-center justify-center gap-1.5"
        @click.stop="showIssueMenu = !showIssueMenu"
      >
        {{ t('invoicing.expense_show_issue_menu') }}
        <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      <div v-if="showIssueMenu" class="invoicing-dropdown left-0 right-0 min-w-0 w-full">
        <button
          v-if="hasAttachment"
          type="button"
          class="invoicing-dropdown-item flex items-center gap-2"
          @click="onDownloadAttachment"
        >
          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          {{ t('invoicing.expense_show_download_attachment') }}
        </button>
        <button type="button" class="invoicing-dropdown-item flex items-center gap-2" @click="onDuplicateFromMenu">
          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          {{ t('invoicing.expense_show_issue_duplicate') }}
        </button>
        <button
          type="button"
          class="invoicing-dropdown-item text-gray-400 cursor-not-allowed"
          disabled
        >
          {{ t('invoicing.expense_show_issue_credit_note') }}
        </button>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
  companyId: string;
  expenseId: string;
  status: string;
  isOverdue: boolean;
  paidAt: string | null;
  total: string | number;
  currency: string;
  hasAttachment: boolean;
  busy: boolean;
}>();

const emit = defineEmits<{
  'mark-paid': [];
  'unmark-paid': [];
  duplicate: [];
  cancel: [];
  'download-attachment': [];
}>();

const { t, locale } = useI18n();
const showIssueMenu = ref(false);

const statusBanner = computed(() => {
  if (props.status === 'cancelled') {
    return { label: t('invoicing.expense_status_cancelled'), class: 'bg-gray-500' };
  }
  if (props.status === 'paid') {
    return { label: t('invoicing.expense_status_paid'), class: 'bg-emerald-500' };
  }
  if (props.isOverdue) {
    return { label: t('invoicing.expense_filter_overdue'), class: 'bg-red-500' };
  }
  if (props.status === 'recorded') {
    return { label: t('invoicing.expense_filter_unpaid'), class: 'bg-amber-500' };
  }

  return null;
});

function formatMoney(amount: string | number, currency: string) {
  const n = typeof amount === 'string' ? parseFloat(amount) : amount;

  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency: currency || 'EUR',
  }).format(n || 0);
}

function formatDate(iso: string) {
  const d = (iso || '').slice(0, 10);
  const [y, m, day] = d.split('-');

  return day && m && y ? `${day}.${m}.${y}` : '—';
}

function onDownloadAttachment() {
  showIssueMenu.value = false;
  emit('download-attachment');
}

function onDuplicateFromMenu() {
  showIssueMenu.value = false;
  emit('duplicate');
}

function onDocumentClick() {
  showIssueMenu.value = false;
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<style scoped>
.sidebar-action {
  @apply w-full flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-100 text-left disabled:opacity-50;
}
.sidebar-action--danger {
  @apply text-red-600 hover:bg-red-50;
}
.sidebar-action-icon {
  @apply w-4 h-4 shrink-0 text-gray-400;
}
</style>
