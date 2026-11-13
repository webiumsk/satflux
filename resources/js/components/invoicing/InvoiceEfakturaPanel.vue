<template>
  <section class="rounded-lg border border-indigo-200 bg-indigo-50/50 p-3 text-sm space-y-2">
    <h3 class="font-medium text-indigo-900 text-xs uppercase tracking-wide">
      {{ t('invoicing.efaktura_panel_title') }}
    </h3>

    <p v-if="!canReadHistory" class="text-xs text-gray-700">
      {{ t('invoicing.efaktura_panel_not_configured') }}
      <RouterLink v-if="settingsTo" :to="settingsTo" class="text-indigo-700 hover:text-indigo-900 underline">
        {{ t('invoicing.efaktura_panel_configure_link') }}
      </RouterLink>
    </p>

    <p v-else-if="!eligibleContact && !isEpostman" class="text-xs text-gray-600">
      {{ t('invoicing.efaktura_panel_not_eligible') }}
    </p>

    <template v-else>
      <div v-if="loading" class="text-xs text-gray-500">
        {{ t('common.loading') }}
      </div>

      <div v-else class="space-y-1">
        <div v-if="peppolRow" class="flex items-center gap-2">
          <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass">
            {{ statusLabel }}
          </span>
        </div>
        <p v-else-if="!statusUnavailable" class="text-xs text-gray-600">
          {{ t('invoicing.efaktura_panel_not_sent') }}
        </p>

        <p v-if="peppolRow?.external_id" class="text-xs text-gray-600 font-mono break-all">
          {{ t('invoicing.efaktura_panel_external_id') }}: {{ peppolRow.external_id }}
        </p>

        <p v-if="peppolRow?.submitted_at" class="text-xs text-gray-500">
          {{ t('invoicing.efaktura_panel_submitted_at', { date: formatDate(peppolRow.submitted_at) }) }}
        </p>

        <p v-if="storedDetailMessage" class="text-xs text-red-700 whitespace-pre-wrap">
          {{ storedDetailMessage }}
        </p>
      </div>

      <div v-if="isEpostman && peppolRow" data-testid="epostman-submission-state" role="status" class="space-y-2 text-xs text-gray-700">
        <p v-if="epostmanNeedsReconciliation" class="text-amber-900">{{ t('invoicing.efaktura_panel_reconciliation_required') }}</p>
        <p v-else-if="epostmanState?.submission_state === 'uncertain'" class="text-amber-900">{{ t('invoicing.efaktura_panel_submission_uncertain') }}</p>
        <p v-else-if="peppolRow.status === 'submitted'">{{ t('invoicing.efaktura_panel_epostman_accepted') }}</p>
        <p v-if="epostmanState?.submission_state === 'uncertain' && canSend && typeof epostmanState.retry_until === 'string'">{{ t('invoicing.efaktura_panel_retry_until', { date: formatDate(epostmanState.retry_until) }) }}</p>
        <div v-if="enterpriseStatus" data-testid="epostman-enterprise-status" class="space-y-1">
          <p>{{ t('invoicing.efaktura_status_delivery') }}: {{ t(`invoicing.efaktura_status_value_${enterpriseStatus.status}`) }}</p>
          <p>{{ t('invoicing.efaktura_status_c3') }}: {{ t(`invoicing.efaktura_status_value_${enterpriseStatus.c3}`) }}</p>
          <p>{{ t('invoicing.efaktura_status_c5') }}: {{ t(`invoicing.efaktura_status_value_${enterpriseStatus.c5}`) }}</p>
          <p v-if="enterpriseStatus.checkedAt">{{ t('invoicing.efaktura_status_last_checked', { date: formatDate(enterpriseStatus.checkedAt) }) }}</p>
          <p v-if="enterpriseStatus.error" class="text-amber-900">{{ t(`invoicing.efaktura_status_error_${enterpriseStatus.error}`) }}</p>
          <p v-if="enterpriseStatus.error && enterpriseStatus.checkedAt" class="text-amber-900">{{ t('invoicing.efaktura_status_previous_observation') }}</p>
          <p>{{ t('invoicing.efaktura_status_scope') }}</p>
        </div>
        <p v-else-if="epostmanState?.delivery_status_available !== true">{{ t('invoicing.efaktura_panel_epostman_portal_status') }}</p>
        <p v-if="cacheUnavailable" class="text-amber-900">{{ t('invoicing.efaktura_status_cache_unavailable') }}</p>
        <a :href="epostmanPortalUrl ?? undefined" target="_blank" rel="noopener noreferrer" class="inline-block text-indigo-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">{{ t('invoicing.efaktura_epostman_open_portal') }}</a>
      </div>

      <!-- Preflight: the send would fail server-side without a resolvable
           buyer Peppol ID - explain it upfront instead of a failed submit. -->
      <p v-if="preflightMissingIds" class="text-xs text-amber-600">
        {{ t('invoicing.efaktura_preflight_missing_ids') }}
      </p>

      <div class="flex flex-col gap-2">
        <button v-if="statusUnavailable" type="button" class="w-full invoicing-btn-secondary text-sm" :disabled="busy || loading" @click="retryLoad">
          {{ t('invoicing.efaktura_panel_retry_load') }}
        </button>
        <button
          v-if="canSend && !preflightMissingIds"
          type="button"
          class="w-full invoicing-btn-secondary text-sm"
          :disabled="busy"
          @click="send"
        >
          {{ sendButtonLabel }}
        </button>
        <button
          v-if="canRefreshStatus"
          type="button"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-white bg-white/80"
          :disabled="busy"
          @click="refreshStatus"
        >
          {{ busy ? t('invoicing.efaktura_panel_refreshing') : t('invoicing.efaktura_panel_refresh_status') }}
        </button>
      </div>

      <p v-if="panelError" class="text-xs text-red-600">{{ panelError }}</p>
      <p v-if="panelSuccess" class="text-xs text-emerald-700">{{ panelSuccess }}</p>
    </template>
  </section>
</template>

<script setup lang="ts">
import { asApiError } from "../../utils/apiError";
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { enterpriseStatusFromRaw, epostmanKeyIsSet } from '../../utils/epostmanStatus';
import { useI18n } from 'vue-i18n';
import {
  isEfakturaConfigured,
  isSkDomesticContact,
} from '../../composables/useCompanyEfakturaSettings';
import { resolvePeppolEndpoint } from '../../utils/peppolEndpoint';
import {
  fetchEphemeralEfakturaStatus,
  refreshEphemeralEfakturaStatus,
  sendEphemeralEfaktura,
  type EphemeralSnapshotPayload,
} from '../../evolu/ephemeralBridge';
import { ensureBridgeCompanyIdForLocalCompany } from '../../evolu/bridgeCompanyEnsure';
import { invoicingApi } from '../../services/api';

type ComplianceRow = {
  id: string;
  provider: string;
  status: string;
  external_id: string | null;
  submitted_at: string | null;
  response_payload?: Record<string, unknown> | null;
};

const props = defineProps<{
  companyId: string;
  documentId: string;
  company: Record<string, unknown> | null;
  selectedContact: Record<string, unknown> | null;
  localFirst?: boolean;
  ephemeralSnapshot?: EphemeralSnapshotPayload | null;
  evoluDocumentId?: string;
  bridgeCompanyId?: string | null;
}>();

const emit = defineEmits<{
  sent: [];
}>();

const { t, locale } = useI18n();

const loading = ref(false);
const busy = ref(false);
const statusUnavailable = ref(false);
const panelError = ref('');
const panelSuccess = ref('');
const rows = ref<ComplianceRow[]>([]);
const resolvedBridgeCompanyId = ref<string | null>(null);
let contextGeneration = 0;
let loadRequestId = 0;
let active = true;
let retryTimer: ReturnType<typeof setTimeout> | undefined;
let cacheTimer: ReturnType<typeof setTimeout> | undefined;
let cacheLoading = false;
const cacheUnavailable = ref(false);
const now = ref(Date.now());
onBeforeUnmount(() => {
  active = false;
  contextGeneration++;
  clearTimeout(retryTimer);
  clearTimeout(cacheTimer);
  document.removeEventListener('visibilitychange', visibilityChanged);
});

const configured = computed(() => props.company?.id === props.companyId && isEfakturaConfigured(props.company));
const contextKey = computed(() => JSON.stringify([
  props.companyId, props.documentId, props.localFirst, props.evoluDocumentId,
  props.company, props.selectedContact,
]));
const bridgeSyncPending = computed(() => props.localFirst
  && (props.company?.app_settings as Record<string, unknown> | undefined)?.efaktura_inbound_bridge_sync_pending === true);
const epostmanPortalUrl = computed(() => {
  const baseUrl = (props.company?.app_settings as Record<string, unknown> | undefined)?.efaktura_sapi_base_url;
  if (typeof baseUrl !== 'string') return null;
  try {
    const parsed = new URL(baseUrl.trim());
    return ['https://postman.slovakodata.com', 'https://testpostman.slovakodata.com'].includes(parsed.origin)
      && parsed.username === '' && parsed.password === '' && parsed.pathname === '/'
      && parsed.search === '' && parsed.hash === '' ? parsed.origin : null;
  } catch { return null; }
});
const isEpostman = computed(() => epostmanPortalUrl.value !== null);
const moduleEnabled = computed(() => props.company?.id === props.companyId
  && (props.company.app_settings as Record<string, unknown> | undefined)?.efaktura_enabled === true);
const canReadHistory = computed(() => configured.value || (isEpostman.value && moduleEnabled.value));
const eligibleContact = computed(() => isSkDomesticContact(props.selectedContact));
const preflightMissingIds = computed(() => props.selectedContact !== null
  && resolvePeppolEndpoint({
    peppol_participant_id: props.selectedContact.peppol_participant_id as string | null,
    tax_id: props.selectedContact.tax_id as string | null,
    registration_number: props.selectedContact.registration_number as string | null,
    country: props.selectedContact.country as string | null,
  }) === null);
const settingsTo = computed(() => props.companyId
  ? { name: 'invoicing-company', params: { companyId: props.companyId } } : null);
const peppolRow = computed(() => rows.value.find((row) => row.provider === 'peppol') ?? rows.value[0] ?? null);
const epostmanState = computed(() => {
  const state = peppolRow.value?.response_payload?.epostman;
  return state && typeof state === 'object' && !Array.isArray(state) ? state as Record<string, unknown> : null;
});
const enterpriseStatus = computed(() => enterpriseStatusFromRaw(epostmanState.value?.enterprise_status));
const epostmanNeedsReconciliation = computed(() => {
  const state = epostmanState.value;
  if (state?.reconciliation_required === true) return true;
  return state?.submission_state === 'uncertain' && typeof state.retry_until === 'string'
    && Number.isFinite(Date.parse(state.retry_until)) && Date.parse(state.retry_until) <= now.value;
});
watch(epostmanState, (state) => {
  clearTimeout(retryTimer);
  now.value = Date.now();
  const retryUntil = typeof state?.retry_until === 'string' ? Date.parse(state.retry_until) : NaN;
  if (Number.isFinite(retryUntil) && retryUntil > now.value) {
    retryTimer = setTimeout(() => { now.value = Date.now(); }, Math.min(retryUntil - now.value + 1, 2147483647));
  }
});
const statusLabel = computed(() => {
  if (isEpostman.value && epostmanState.value?.submission_state === 'uncertain') {
    return t('invoicing.efaktura_panel_status_uncertain');
  }
  if (isEpostman.value && peppolRow.value?.status === 'submitted') {
    return t('invoicing.efaktura_panel_status_accepted');
  }
  const status = peppolRow.value?.status ?? 'pending';
  const key = `invoicing.efaktura_panel_status_${status}`;
  const translated = t(key);
  return translated === key ? status : translated;
});
const statusBadgeClass = computed(() => {
  if (isEpostman.value && epostmanState.value?.submission_state === 'uncertain') return 'bg-amber-100 text-amber-900';
  switch (peppolRow.value?.status) {
    case 'approved': return 'bg-emerald-100 text-emerald-800';
    case 'submitted': return 'bg-blue-100 text-blue-800';
    case 'failed':
    case 'rejected': return 'bg-red-100 text-red-800';
    case 'skipped': return 'bg-gray-100 text-gray-700';
    default: return 'bg-amber-100 text-amber-900';
  }
});
const canRefreshStatus = computed(() => {
  if (loading.value || bridgeSyncPending.value || peppolRow.value?.status !== 'submitted') return false;
  if (isEpostman.value) return moduleEnabled.value && epostmanKeyIsSet(props.company)
    && epostmanState.value?.submission_state === 'accepted' && epostmanState.value.delivery_status_available === true;
  return configured.value && eligibleContact.value;
});
const canSend = computed(() => {
  if (!configured.value || !eligibleContact.value || loading.value || statusUnavailable.value || bridgeSyncPending.value
    || (props.localFirst && !resolvedBridgeCompanyId.value)) return false;
  if (!peppolRow.value) return true;
  if (isEpostman.value) {
    if (epostmanState.value?.retry_allowed !== true || epostmanState.value.reconciliation_required === true) return false;
    const retryUntil = epostmanState.value.retry_until;
    return typeof retryUntil === 'string' && Number.isFinite(Date.parse(retryUntil)) && Date.parse(retryUntil) > now.value;
  }
  return ['pending', 'failed', 'rejected', 'skipped'].includes(peppolRow.value.status);
});
const sendButtonLabel = computed(() => {
  if (busy.value) return t('invoicing.efaktura_panel_sending');
  if (isEpostman.value && peppolRow.value) return t('invoicing.efaktura_panel_retry_submission');
  return t(peppolRow.value ? 'invoicing.efaktura_panel_resend' : 'invoicing.efaktura_panel_send');
});
const storedDetailMessage = computed(() => complianceDetailMessage(peppolRow.value));

/** Capture all request inputs; a late response never targets the next company/document. */
function captureContext() {
  const generation = contextGeneration;
  const key = contextKey.value;
  return {
    companyId: props.companyId,
    documentId: props.documentId,
    localFirst: props.localFirst === true,
    evoluDocumentId: props.evoluDocumentId,
    bridgeCompanyId: resolvedBridgeCompanyId.value,
    isEpostman: isEpostman.value,
    snapshot: props.ephemeralSnapshot,
    isCurrent: () => active && generation === contextGeneration && key === contextKey.value,
  };
}
type PanelContext = ReturnType<typeof captureContext>;

watch(
  contextKey,
  () => {
    contextGeneration++;
    clearTimeout(cacheTimer);
    cacheUnavailable.value = false;
    loadRequestId++;
    rows.value = [];
    resolvedBridgeCompanyId.value = null;
    panelError.value = '';
    panelSuccess.value = '';
    statusUnavailable.value = false;
    busy.value = false;
    loading.value = true;
    void bootstrap(captureContext());
  },
  { immediate: true },
);

async function bootstrap(context: PanelContext) {
  if (!context.documentId || !canReadHistory.value) {
    if (context.isCurrent()) loading.value = false;
    return;
  }
  if (bridgeSyncPending.value) {
    panelError.value = t('invoicing.efaktura_epostman_bridge_save_failed');
    loading.value = false;
    return;
  }
  if (context.localFirst) {
    const result = await ensureBridgeCompanyIdForLocalCompany(context.companyId);
    if (!context.isCurrent()) return;
    if (!result.ok || !result.bridgeCompanyId) {
      panelError.value = t('invoicing.efaktura_panel_bridge_not_configured');
      loading.value = false;
      statusUnavailable.value = true;
      return;
    }
    resolvedBridgeCompanyId.value = result.bridgeCompanyId;
  }
  await loadCompliance(captureContext());
}

function retryLoad() {
  if (busy.value || loading.value) return;
  panelError.value = '';
  loading.value = true;
  void bootstrap(captureContext());
}

async function loadCompliance(context: PanelContext, cacheOnly = false) {
  if (!context.isCurrent()) return;
  const requestId = ++loadRequestId;
  const isCurrent = () => context.isCurrent() && requestId === loadRequestId;
  if (!cacheOnly) loading.value = true;
  try {
    if (context.localFirst) {
      if (!context.evoluDocumentId || !context.bridgeCompanyId) throw new Error('Missing local document bridge');
      const fetched = await fetchEphemeralEfakturaStatus(context.evoluDocumentId, context.bridgeCompanyId);
      if (isCurrent()) rows.value = fetched;
    } else {
      const fetched = await invoicingApi.documents.efaktura.compliance<ComplianceRow>(context.companyId, context.documentId);
      if (isCurrent()) rows.value = fetched;
    }
    if (isCurrent()) { statusUnavailable.value = false; cacheUnavailable.value = false; }
  } catch {
    if (isCurrent() && cacheOnly) cacheUnavailable.value = true;
    if (isCurrent() && !cacheOnly) {
      statusUnavailable.value = true;
      if (!panelError.value) panelError.value = t('invoicing.efaktura_panel_status_unavailable');
    }
  } finally {
    if (isCurrent()) { loading.value = false; scheduleCacheRead(); }
  }
}

/** Only reads Satflux's cache. Remote polling belongs to the server scheduler. */
function scheduleCacheRead() {
  clearTimeout(cacheTimer);
  if (!active || document.visibilityState === 'hidden' || !isEpostman.value
    || !canReadHistory.value || bridgeSyncPending.value || peppolRow.value?.status !== 'submitted') return;
  const context = captureContext();
  cacheTimer = setTimeout(async () => {
    if (!context.isCurrent() || busy.value || cacheLoading || document.visibilityState === 'hidden') {
      scheduleCacheRead();
      return;
    }
    cacheLoading = true;
    try { await loadCompliance(context, true); } finally { cacheLoading = false; }
  }, 30000);
}
function visibilityChanged() { scheduleCacheRead(); }
document.addEventListener('visibilitychange', visibilityChanged);

async function refreshStatus() {
  if (!canRefreshStatus.value || busy.value) return;
  const context = captureContext();
  loadRequestId++;
  clearTimeout(cacheTimer);
  busy.value = true;
  panelError.value = '';
  panelSuccess.value = '';
  try {
    if (context.localFirst) {
      if (!context.evoluDocumentId || !context.bridgeCompanyId) return;
      const fetched = await refreshEphemeralEfakturaStatus(context.evoluDocumentId, context.bridgeCompanyId);
      if (!context.isCurrent()) return;
      rows.value = fetched;
    } else {
      await invoicingApi.documents.efaktura.refresh(context.companyId, context.documentId);
      if (!context.isCurrent()) return;
      await loadCompliance(context);
    }
    if (context.isCurrent() && !statusUnavailable.value) panelSuccess.value = t('invoicing.efaktura_panel_refresh_success');
  } catch (rawError) {
    if (context.isCurrent()) panelError.value = context.isEpostman
      ? t('invoicing.efaktura_status_check_failed') : extractError(rawError);
  } finally {
    if (context.isCurrent()) { busy.value = false; scheduleCacheRead(); }
  }
}

async function send() {
  if (!canSend.value || preflightMissingIds.value || busy.value) return;
  const context = captureContext();
  loadRequestId++;
  clearTimeout(cacheTimer);
  busy.value = true;
  panelError.value = '';
  panelSuccess.value = '';
  try {
    if (context.localFirst) {
      if (!context.evoluDocumentId || !context.bridgeCompanyId || !context.snapshot) {
        panelError.value = t('invoicing.efaktura_panel_bridge_not_configured');
        return;
      }
      const data = await sendEphemeralEfaktura(context.snapshot, context.evoluDocumentId, context.bridgeCompanyId,
        { requireBridge: context.isEpostman });
      if (!context.isCurrent()) return;
      rows.value = data ? [data] : [];
      panelSuccess.value = context.isEpostman ? '' : data.message || t('invoicing.efaktura_panel_send_success');
    } else {
      const data = await invoicingApi.documents.efaktura.send<{ message?: string }>(context.companyId, context.documentId);
      if (!context.isCurrent()) return;
      panelSuccess.value = context.isEpostman ? '' : data.message || t('invoicing.efaktura_panel_send_success');
      await loadCompliance(context);
    }
    if (context.isCurrent()) emit('sent');
  } catch (rawError) {
    if (!context.isCurrent()) return;
    panelError.value = extractError(rawError);
    await loadCompliance(context);
  } finally {
    if (context.isCurrent()) { busy.value = false; scheduleCacheRead(); }
  }
}

function complianceDetailMessage(row: ComplianceRow | null): string {
  if (!row || !['failed', 'rejected', 'skipped'].includes(row.status)) {
    return '';
  }

  const payload = row.response_payload;
  if (payload && typeof payload === 'object') {
    for (const key of ['message', 'error', 'detail']) {
      const value = payload[key];
      if (typeof value === 'string' && value.trim() !== '') {
        return value;
      }
    }
  }

  return '';
}

function extractError(rawError: unknown) {
  const e = asApiError(rawError);
  const errors = e.response?.data?.errors;
  if (errors && typeof errors === 'object') {
    for (const value of Object.values(errors)) {
      if (Array.isArray(value) && typeof value[0] === 'string') {
        return value[0];
      }
    }
  }

  return e.response?.data?.message || t('common.error');
}

function formatDate(iso: string) {
  const d = new Date(iso.includes('T') ? iso : `${iso}T12:00:00`);
  return d.toLocaleString(locale.value);
}
</script>
