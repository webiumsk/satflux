<template>
  <section
    v-if="showPanel"
    class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50/60 p-4"
  >
    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm font-medium text-indigo-950">
        {{ t('invoicing.integration_inbox_title') }}
      </p>
      <button
        type="button"
        class="invoicing-btn-secondary shrink-0 text-sm"
        :disabled="loading"
        @click="refresh"
      >
        {{ t('invoicing.integration_inbox_refresh') }}
      </button>
    </div>

    <p v-if="error" class="mt-3 text-sm text-red-700">{{ error }}</p>

    <p v-if="items.length" class="mt-3 text-xs text-indigo-900">
      {{ t('invoicing.integration_inbox_relay_hint') }}
    </p>

    <p
      v-if="showAutoIssueHint"
      class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
    >
      {{ t('invoicing.integration_inbox_auto_issue_hint') }}
      <RouterLink
        :to="{ name: 'invoicing-company', params: { companyId } }"
        class="ml-1 font-medium underline"
      >
        {{ t('invoicing.integration_inbox_auto_issue_hint_link') }}
      </RouterLink>
    </p>

    <ul v-if="items.length" class="mt-3 space-y-2">
      <li
        v-for="item in items"
        :key="item.inbox_id"
        class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-indigo-100 bg-white px-3 py-2"
      >
        <div class="min-w-0">
          <p class="text-sm font-medium text-gray-900 truncate">
            <span v-if="item.woocommerce_order_id">
              {{ t('invoicing.integration_inbox_order', { id: item.woocommerce_order_id }) }}
            </span>
            <span v-else>{{ t('invoicing.integration_inbox_unlinked_order') }}</span>
          </p>
          <p class="text-xs text-gray-600 mt-0.5">
            {{ item.summary.buyer_name || t('invoicing.integration_inbox_unknown_buyer') }}
            · {{ item.summary.line_count }} {{ t('invoicing.integration_inbox_lines') }}
            · {{ item.summary.currency }}
          </p>
        </div>
        <div class="flex shrink-0 gap-2">
          <button
            type="button"
            class="invoicing-btn-secondary"
            :disabled="busyId === item.inbox_id"
            @click="dismissItem(item)"
          >
            {{ t('invoicing.integration_inbox_dismiss') }}
          </button>
          <button
            type="button"
            class="invoicing-btn-primary"
            :disabled="busyId === item.inbox_id"
            @click="importItem(item)"
          >
            {{ t('invoicing.integration_inbox_import') }}
          </button>
        </div>
      </li>
    </ul>

  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { VatPolicyCompany } from '@/composables/useCompanyVatPolicy';
import { useInvoicingEvolu } from '@/evolu/client';
import {
  dismissIntegrationInboxItem,
  fetchIntegrationInbox,
  importIntegrationInboxEntry,
  IntegrationInboxPathError,
  reconcileIntegrationInboxWithLocalDocuments,
  type IntegrationInboxEntry,
} from '@/evolu/integrationInboxImport';
import { isPaidWooCommercePayload } from '@/evolu/integrationInboxPaid';
import { ensureEvoluBoundToAccountSeed } from '@/evolu/bootstrap';
import { pullInvoicingFromRelay } from '@/evolu/relaySyncWait';

const props = defineProps<{
  companyId: string;
  linkedStoreId?: string | null;
  company: VatPolicyCompany;
  enabled: boolean;
}>();

const emit = defineEmits<{
  imported: [];
}>();

const { t } = useI18n();
const evolu = useInvoicingEvolu();

const items = ref<IntegrationInboxEntry[]>([]);

// A PAID order sitting in the inbox WITHOUT a number means headless
// auto-issue did not run - most likely the opt-in toggle in company
// settings is off. Point the merchant straight at it.
const showAutoIssueHint = computed(() =>
  items.value.some(
    (item) =>
      !String(item.payload?.number ?? '').trim()
      && isPaidWooCommercePayload(item.payload),
  ),
);
const loading = ref(false);
const error = ref('');
const busyId = ref<string | null>(null);

const showPanel = computed(
  () => props.enabled && (items.value.length > 0 || error.value !== ''),
);

function mapImportError(code: string): string {
  switch (code) {
    case 'contact_create_failed':
      return t('invoicing.integration_inbox_import_contact_failed');
    case 'lines_required':
      return t('invoicing.integration_inbox_import_lines_failed');
    default:
      return t('invoicing.integration_inbox_import_failed');
  }
}

async function refresh(): Promise<void> {
  if (!props.enabled || !props.companyId) {
    items.value = [];
    return;
  }
  loading.value = true;
  error.value = '';
  try {
    await ensureEvoluBoundToAccountSeed();
    await pullInvoicingFromRelay(evolu, {
      companyId: props.companyId,
      timeoutMs: 20_000,
    });
    const fetched = await fetchIntegrationInbox(props.companyId, props.linkedStoreId);
    items.value = await reconcileIntegrationInboxWithLocalDocuments(
      evolu,
      props.companyId,
      fetched,
      props.linkedStoreId,
    );
  } catch (e: unknown) {
    if (e instanceof IntegrationInboxPathError && e.code === 'store_required') {
      error.value = t('invoicing.integration_inbox_store_required');
      return;
    }
    const err = e as { response?: { data?: { message?: string } } };
    error.value = err?.response?.data?.message || t('errors.generic');
  } finally {
    loading.value = false;
  }
}

async function importItem(item: IntegrationInboxEntry): Promise<void> {
  busyId.value = item.inbox_id;
  error.value = '';
  try {
    const result = await importIntegrationInboxEntry(
      evolu,
      props.companyId,
      item,
      props.company,
      props.linkedStoreId,
    );
    if (!result.ok) {
      error.value = mapImportError(result.error);
      return;
    }
    items.value = items.value.filter((row) => row.inbox_id !== item.inbox_id);
    emit('imported');
  } catch (e: unknown) {
    if (e instanceof IntegrationInboxPathError && e.code === 'store_required') {
      error.value = t('invoicing.integration_inbox_store_required');
      return;
    }
    const err = e as { response?: { data?: { message?: string } } };
    error.value = err?.response?.data?.message || t('errors.generic');
  } finally {
    busyId.value = null;
  }
}

async function dismissItem(item: IntegrationInboxEntry): Promise<void> {
  // An auto-issued entry already carries an allocated invoice number (and
  // the customer may have received the PDF) - dismissing it drops that
  // number from the books, so it needs an explicit confirmation.
  const stampedNumber = String(item.payload?.number ?? '').trim();
  if (
    stampedNumber !== ''
    && !window.confirm(t('invoicing.integration_inbox_dismiss_issued_confirm', { number: stampedNumber }))
  ) {
    return;
  }
  busyId.value = item.inbox_id;
  error.value = '';
  try {
    await dismissIntegrationInboxItem(props.companyId, item.inbox_id, props.linkedStoreId);
    items.value = items.value.filter((row) => row.inbox_id !== item.inbox_id);
  } catch (e: unknown) {
    if (e instanceof IntegrationInboxPathError && e.code === 'store_required') {
      error.value = t('invoicing.integration_inbox_store_required');
      return;
    }
    const err = e as { response?: { data?: { message?: string } } };
    error.value = err?.response?.data?.message || t('errors.generic');
  } finally {
    busyId.value = null;
  }
}

onMounted(() => {
  void refresh();
});

watch(
  () => [props.companyId, props.linkedStoreId, props.enabled] as const,
  () => {
    void refresh();
  },
);
</script>
