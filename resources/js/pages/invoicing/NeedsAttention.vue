<template>
  <InvoicingPageShell :title="t('invoicing.action_center.title')" :subtitle="t('invoicing.action_center.subtitle')">
    <template #header><InvoicingAppHeader :show-filter-bar="false" /></template>
    <template #actions>
      <button type="button" class="invoicing-btn-secondary" :disabled="loading" @click="refresh">
        {{ loading ? t('invoicing.action_center.checking') : t('invoicing.action_center.refresh') }}
      </button>
    </template>
    <p v-if="checkedAt" class="mb-4 text-xs text-gray-600">{{ t('invoicing.action_center.checked_at', { at: formatDate(checkedAt) }) }}</p>
    <p v-if="localFirst" class="mb-4 text-sm text-gray-600">{{ t('invoicing.action_center.local_scope') }}</p>
    <div v-if="partialError" role="alert" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
      {{ t('invoicing.action_center.partial_error') }}
      <button type="button" class="ml-2 underline font-medium" :disabled="loading" @click="refresh">{{ t('invoicing.action_center.retry') }}</button>
    </div>
    <p v-if="restrictedSources.length" class="mb-4 rounded-lg border border-gray-300 bg-white p-4 text-sm text-gray-700">
      {{ t('invoicing.action_center.restricted_sources') }}
    </p>
    <p v-if="allClear" role="status" class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-950">
      {{ t('invoicing.action_center.all_clear') }}
    </p>
    <p v-if="loading" role="status" aria-live="polite" class="mb-4 text-sm text-gray-700">{{ t('invoicing.action_center.checking') }}</p>
    <div class="grid gap-4 lg:grid-cols-2" :aria-busy="loading">
      <section v-for="category in categories" :key="category.key" class="invoicing-card-pad min-w-0" :aria-labelledby="`attention-${category.key}`">
        <div class="flex items-start justify-between gap-3">
          <h2 :id="`attention-${category.key}`" class="text-base font-semibold text-gray-900">{{ t(`invoicing.action_center.category_${category.key}`) }}</h2>
          <span v-if="category.count !== null" class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-sm tabular-nums text-gray-800">{{ category.count }}</span>
        </div>
        <p class="mt-1 text-sm text-gray-600">{{ t(`invoicing.action_center.help_${category.key}`) }}</p>
        <p v-if="category.state === 'unavailable' && !loading" class="mt-3 text-sm text-amber-900">{{ t('invoicing.action_center.unavailable') }}</p>
        <p v-else-if="category.state === 'not_configured'" class="mt-3 text-sm text-gray-600">{{ t('invoicing.action_center.not_configured') }}</p>
        <p v-else-if="!loading && category.items.length === 0 && !category.has_more" class="mt-3 text-sm text-gray-600">{{ t('invoicing.action_center.no_items') }}</p>
        <ul v-if="category.items.length" class="mt-4 divide-y divide-gray-200">
          <li v-for="item in category.items" :key="`${item.kind}:${item.id}`" class="py-3 first:pt-0 last:pb-0">
            <p v-if="item.reference" class="break-words text-sm font-semibold text-gray-900">{{ item.reference }}</p>
            <p class="mt-1 text-sm text-gray-700">{{ t(`invoicing.action_center.reason_${reasonKey(item)}`) }}</p>
            <p v-if="item.amount || item.occurred_at" class="mt-1 text-xs text-gray-600">
              <span v-if="item.amount">{{ item.amount }} {{ item.currency }}</span>
              <span v-if="item.amount && item.occurred_at"> · </span>
              <span v-if="item.occurred_at">{{ formatDate(item.occurred_at) }}</span>
            </p>
            <RouterLink :to="actionTo(category.key, item)" class="mt-2 inline-flex text-sm font-semibold text-indigo-700 underline decoration-1 underline-offset-2 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
              {{ t(`invoicing.action_center.action_${actionKey(category.key, item)}`) }}
            </RouterLink>
          </li>
        </ul>
        <p v-if="category.has_more" class="mt-4 text-sm text-gray-700">{{ t('invoicing.action_center.more_items') }}</p>
        <RouterLink v-if="category.has_more" :to="actionTo(category.key, category.items[0])" class="mt-2 inline-flex text-sm font-semibold text-indigo-700 underline underline-offset-2">
          {{ t('invoicing.action_center.open_all') }}
        </RouterLink>
      </section>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { RouteLocationRaw } from 'vue-router';
import InvoicingPageShell from '@/components/invoicing/InvoicingPageShell.vue';
import InvoicingAppHeader from '@/components/invoicing/InvoicingAppHeader.vue';
import { useInvoicingLayout } from '@/composables/useInvoicingLayout';
import { useInvoicingRelaySync } from '@/composables/useInvoicingRelaySync';
import { useCompanyActionCenter } from '@/composables/useCompanyActionCenter';
import { invoicingDocumentRoutesForType } from '@/composables/useInvoicingDocumentRoutes';
import type { ActionCenterKey, ActionCenterItem } from '@/services/companyActionCenter';

const { t, locale } = useI18n();
const { companyId } = useInvoicingLayout();
const relay = useInvoicingRelaySync();
const { categories, loading, partialError, allClear, checkedAt, restrictedSources, refresh, localFirst } = useCompanyActionCenter(companyId, { relayState: relay.syncState });

function formatDate(raw: string): string {
  const parsed = new Date(raw);
  return Number.isNaN(parsed.getTime()) ? '' : parsed.toLocaleString(locale.value);
}

function reasonKey(item: ActionCenterItem): string {
  const known = ['unmatched_bank', 'remote_bank_review', 'local_email_failed', 'legacy_email_failed', 'integration_email_failed',
    'efaktura_send_failed', 'integration_pending', 'efaktura_pending', 'wise_sync_due', 'relay_unreachable', 'relay_error', 'relay_unknown', 'relay_syncing'];
  return known.includes(item.kind) ? item.kind : 'unknown';
}

function actionKey(key: ActionCenterKey, item: ActionCenterItem): string {
  if (item.kind.startsWith('relay_')) return 'device_sync';
  if (item.kind === 'legacy_email_failed' && localFirst) return 'legacy';
  if (key === 'unmatched_payments') return 'payment';
  if (key === 'failed_sends') return 'sending';
  if (key === 'synchronization') return 'sync';
  return 'incoming';
}

function actionTo(key: ActionCenterKey, item?: ActionCenterItem): RouteLocationRaw {
  const params = { companyId: companyId.value };
  if (item?.kind.startsWith('relay_')) return { name: 'invoicing', query: { sync: '1' } };
  if (item?.kind === 'legacy_email_failed' && localFirst) return { name: 'invoicing' };
  if (item?.kind === 'local_email_failed' && item.document_id) {
    return { name: invoicingDocumentRoutesForType(item.document_type ?? 'invoice').show, params: { ...params, documentId: item.document_id } };
  }
  if (key === 'unmatched_payments') return { name: 'invoicing-payments', params, query: { match_status: 'unmatched' } };
  if (key === 'synchronization') return { name: 'invoicing-payments', params, query: { tab: 'import' } };
  if (item?.kind === 'efaktura_pending') return { name: 'invoicing-expenses', params };
  if (key === 'incoming_documents' || item?.kind === 'integration_email_failed') {
    return { name: 'invoicing-invoices', params, query: { integration_inbox: '1' } };
  }
  if (!localFirst && item?.document_id && item.document_type) {
    return { name: invoicingDocumentRoutesForType(item.document_type).show, params: { ...params, documentId: item.document_id } };
  }
  return { name: 'invoicing-invoices', params };
}
</script>
