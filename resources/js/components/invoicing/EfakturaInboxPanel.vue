<template>
  <section
    v-if="showPanel"
    class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50/60 p-4"
    data-testid="efaktura-inbox-panel"
  >
    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm font-medium text-indigo-950">
        {{ t('invoicing.efaktura_inbox_title') }}
        <span v-if="items.length" class="ml-1 text-xs font-normal text-indigo-800">({{ items.length }})</span>
      </p>
      <button
        type="button"
        class="invoicing-btn-secondary shrink-0 text-sm"
        :disabled="loading"
        @click="refresh"
      >
        {{ t('invoicing.efaktura_inbox_refresh') }}
      </button>
    </div>

    <p class="mt-2 text-xs text-indigo-950">{{ t('invoicing.efaktura_inbox_original_notice') }}</p>
    <p v-if="healthWarning" role="status" data-testid="efaktura-inbound-health" class="mt-3 rounded border border-amber-200 bg-amber-50 p-2 text-sm text-amber-950">
      {{ t(`invoicing.efaktura_inbox_health_${healthWarning}`) }}
      <span v-if="health?.last_poll_at"> {{ t('invoicing.efaktura_inbound_last_poll', { date: formatDate(health.last_poll_at) }) }}</span>
    </p>
    <p v-if="error" role="alert" class="mt-3 text-sm text-red-700">{{ error }}</p>

    <ul v-if="items.length" class="mt-3 space-y-2">
      <li
        v-for="item in items"
        :key="item.inbox_id"
        class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-indigo-100 bg-white px-3 py-2"
      >
        <div class="min-w-0">
          <p class="text-sm font-medium text-gray-900 truncate">
            {{ item.summary.supplier_name || t('invoicing.efaktura_inbox_unknown_supplier') }}
          </p>
          <p class="text-xs text-gray-600 mt-0.5">
            <span v-if="item.summary.external_number">{{ item.summary.external_number }} · </span>
            <span v-if="item.summary.total">{{ item.summary.total }} {{ item.summary.currency }}</span>
            <span v-if="item.created_at"> · {{ formatDate(item.created_at) }}</span>
          </p>
        </div>
        <div class="flex shrink-0 gap-2">
          <button
            type="button"
            class="invoicing-btn-secondary"
            :disabled="busyId !== null"
            :aria-expanded="selectedDetail?.inbox_id === item.inbox_id"
            @click="viewItem(item)"
          >
            {{ t('invoicing.efaktura_inbox_view') }}
          </button>
          <button
            type="button"
            class="invoicing-btn-secondary"
            :disabled="busyId !== null"
            @click="downloadItem(item)"
          >
            {{ t('invoicing.efaktura_inbox_download_original') }}
          </button>
          <button
            type="button"
            class="invoicing-btn-primary"
            :disabled="busyId !== null"
            @click="importItem(item)"
          >
            {{ t('invoicing.efaktura_inbox_import') }}
          </button>
        </div>
        <div v-if="selectedDetail?.inbox_id === item.inbox_id" class="w-full border-t border-gray-100 pt-3 text-sm text-gray-800">
          <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div><dt class="text-xs text-gray-500">{{ t('invoicing.efaktura_inbox_document_number') }}</dt><dd>{{ selectedDetail.summary.external_number || '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ t('invoicing.efaktura_inbox_document_type') }}</dt><dd>{{ t(selectedDetail.draft?.document_type === 'credit_note' ? 'invoicing.efaktura_inbox_credit_note' : 'invoicing.efaktura_inbox_invoice') }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ t('invoicing.efaktura_inbox_issue_date') }}</dt><dd>{{ draftDate('issue_date') }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ t('invoicing.efaktura_inbox_due_date') }}</dt><dd>{{ draftDate('due_date') }}</dd></div>
          </dl>
          <div v-if="lineItems.length" class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead><tr><th scope="col" class="p-1">{{ t('invoicing.efaktura_inbox_line_description') }}</th><th scope="col" class="p-1">{{ t('invoicing.efaktura_inbox_line_quantity') }}</th><th scope="col" class="p-1">{{ t('invoicing.efaktura_inbox_line_amount') }}</th></tr></thead>
              <tbody><tr v-for="(line, index) in lineItems" :key="index"><td class="p-1 break-words">{{ line.description }}</td><td class="p-1">{{ line.quantity }}</td><td class="p-1">{{ line.amount }}</td></tr></tbody>
            </table>
          </div>
          <p v-if="selectedDetail.draft?.internal_note" class="mt-2 whitespace-pre-wrap break-words">{{ selectedDetail.draft.internal_note }}</p>
          <details v-if="selectedDetail.ubl" class="mt-3">
            <summary class="cursor-pointer text-indigo-700 underline">{{ t('invoicing.efaktura_inbox_xml_preview') }}</summary>
            <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-all rounded bg-gray-50 p-2 text-xs">{{ selectedDetail.ubl }}</pre>
          </details>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { asApiError } from '../../utils/apiError';
import { useInvoicingEvolu } from '@/evolu/client';
import {
  fetchEfakturaInboxDetail,
  importEfakturaInboxEntry,
  type EfakturaInboxEntry,
  type EfakturaInboxEntryDetail,
} from '@/evolu/efakturaInboxImport';
import { refreshEfakturaInboxLive, useEfakturaInboxLive } from '@/evolu/efakturaInboxLive';

/**
 * Received e-invoices waiting on the bridge company for this local-first
 * company. Renders only while there is something to act on (or an error to
 * show) - the live loop in efakturaInboxLive.ts keeps `items` fresh.
 */
const props = defineProps<{
  companyId: string;
}>();

const emit = defineEmits<{
  imported: [];
}>();

const { t, locale } = useI18n();
const evolu = useInvoicingEvolu();
const { entries: items, bridgeCompanyId, health, receivingEnabled } = useEfakturaInboxLive();

const loading = ref(false);
const error = ref('');
const busyId = ref<string | null>(null);
const selectedDetail = ref<EfakturaInboxEntryDetail | null>(null);
const lineItems = computed(() => {
  const xml = selectedDetail.value?.ubl;
  if (!xml || xml.length > 1_048_576 || /<!DOCTYPE|<!ENTITY/i.test(xml)) return [];
  const document = new DOMParser().parseFromString(xml, 'application/xml');
  if (document.getElementsByTagName('parsererror').length) return [];
  const text = (node: Element, name: string) => node.getElementsByTagNameNS('*', name)[0]?.textContent?.slice(0, 512) ?? '';
  const lines = [...document.getElementsByTagNameNS('*', 'InvoiceLine'), ...document.getElementsByTagNameNS('*', 'CreditNoteLine')];
  return lines.slice(0, 100).map((line) => ({
    description: text(line, 'Name') || text(line, 'Description'),
    quantity: text(line, 'InvoicedQuantity') || text(line, 'CreditedQuantity'),
    amount: text(line, 'LineExtensionAmount'),
  }));
});
let generation = 0;
watch([() => props.companyId, bridgeCompanyId], () => {
  generation++;
  selectedDetail.value = null;
  error.value = '';
  busyId.value = null;
  loading.value = false;
});

const healthWarning = computed(() => receivingEnabled.value && health.value?.state !== 'ok' ? health.value?.state ?? 'unavailable' : null);
const showPanel = computed(() => items.value.length > 0 || error.value !== '' || healthWarning.value !== null);

function currentAction(companyId: string, bridgeId: string, startedGeneration: number): boolean {
  return props.companyId === companyId && bridgeCompanyId.value === bridgeId && generation === startedGeneration;
}

function draftDate(key: string): string {
  const value = selectedDetail.value?.draft?.[key];
  return typeof value === 'string' && value !== '' ? formatDate(value) : '-';
}

function formatDate(iso: string): string {
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleDateString(locale.value);
}

function describeError(rawError: unknown): string {
  const e = asApiError(rawError);
  return e?.response?.data?.message ?? (rawError instanceof Error ? rawError.message : t('common.error_generic'));
}

async function refresh(): Promise<void> {
  const companyId = props.companyId;
  const bridgeId = bridgeCompanyId.value;
  const startedGeneration = generation;
  loading.value = true;
  error.value = '';
  try {
    await refreshEfakturaInboxLive(true, { throwOnError: true });
  } catch (rawError) {
    if (bridgeId && currentAction(companyId, bridgeId, startedGeneration)) error.value = describeError(rawError);
  } finally {
    if (generation === startedGeneration) loading.value = false;
  }
}

async function importItem(item: EfakturaInboxEntry): Promise<void> {
  const bridgeId = bridgeCompanyId.value;
  const companyId = props.companyId;
  const startedGeneration = generation;
  if (!bridgeId || busyId.value !== null) return;
  busyId.value = item.inbox_id;
  error.value = '';
  try {
    const result = await importEfakturaInboxEntry(evolu, companyId, bridgeId, item);
    if (!currentAction(companyId, bridgeId, startedGeneration)) return;
    if (!result.ok) {
      error.value = t(`invoicing.efaktura_inbox_error_${result.error}`);
      return;
    }
    items.value = items.value.filter((row) => row.inbox_id !== item.inbox_id);
    if (selectedDetail.value?.inbox_id === item.inbox_id) selectedDetail.value = null;
    emit('imported');
  } catch (rawError) {
    if (currentAction(companyId, bridgeId, startedGeneration)) error.value = describeError(rawError);
  } finally {
    if (currentAction(companyId, bridgeId, startedGeneration)) busyId.value = null;
  }
}

async function readOriginal(item: EfakturaInboxEntry, download: boolean): Promise<void> {
  const bridgeId = bridgeCompanyId.value;
  const companyId = props.companyId;
  const startedGeneration = generation;
  if (!bridgeId || busyId.value !== null) return;
  busyId.value = item.inbox_id;
  error.value = '';
  try {
    const detail = await fetchEfakturaInboxDetail(bridgeId, item.inbox_id);
    if (!currentAction(companyId, bridgeId, startedGeneration)) return;
    if (!download) {
      selectedDetail.value = detail;
      return;
    }
    if (!detail.ubl) {
      error.value = t('invoicing.efaktura_inbox_error_original_unavailable');
      return;
    }
    const url = URL.createObjectURL(new Blob([detail.ubl], { type: 'application/xml;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `efaktura-${item.external_document_id.replace(/[^A-Za-z0-9._-]/g, '-').slice(0, 80) || 'original'}.xml`;
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (rawError) {
    if (currentAction(companyId, bridgeId, startedGeneration)) error.value = describeError(rawError);
  } finally {
    if (currentAction(companyId, bridgeId, startedGeneration)) busyId.value = null;
  }
}

async function viewItem(item: EfakturaInboxEntry): Promise<void> {
  if (selectedDetail.value?.inbox_id === item.inbox_id) {
    selectedDetail.value = null;
    return;
  }
  await readOriginal(item, false);
}

async function downloadItem(item: EfakturaInboxEntry): Promise<void> {
  await readOriginal(item, true);
}
</script>
