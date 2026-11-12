<template>
  <form class="space-y-6 max-w-2xl" @submit.prevent="save">
    <fieldset :disabled="saving || pollingInbound || !loadedCompanyMatches" class="space-y-6 min-w-0">
    <div
      class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 space-y-2"
      role="status"
    >
      <p>{{ t('invoicing.efaktura_intro') }}</p>
      <p class="text-xs">{{ t('invoicing.efaktura_cpds_note') }}</p>
    </div>
    <p
      v-if="inboundOnly"
      data-testid="efaktura-inbound-only-notice"
      class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"
    >
      {{ t('invoicing.efaktura_inbound_only_notice') }}
    </p>

    <label class="flex items-start gap-2 text-sm text-gray-800">
      <input
        v-model="form.efaktura_enabled"
        type="checkbox"
        class="mt-0.5 rounded border-gray-300 text-indigo-600"
      />
      <span>{{ t('invoicing.efaktura_enabled') }}</span>
    </label>

    <p v-if="bridgeSaveFailed" role="alert" data-testid="efaktura-sync-retry-alert" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
      {{ t('invoicing.efaktura_epostman_bridge_save_failed') }}
    </p>

    <template v-if="form.efaktura_enabled">
      <!-- Step 1: choose the CPDS (digitalny postar) -->
      <section class="rounded-lg border border-gray-200 p-4 space-y-3">
        <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
          <span :class="stepBadgeClass(step1Done)">{{ step1Done ? '✓' : '1' }}</span>
          {{ t('invoicing.efaktura_step1_title') }}
        </h3>
        <i18n-t keypath="invoicing.efaktura_step1_portal" tag="p" class="text-xs text-gray-600">
          <template #portal>
            <a
              href="https://vpds.financnasprava.sk"
              target="_blank"
              rel="noopener noreferrer"
              class="text-indigo-600 hover:text-indigo-500 underline"
            >vpds.financnasprava.sk</a>
          </template>
        </i18n-t>

        <div>
          <label for="efaktura-cpds-preset" class="invoicing-sf-label">{{ t('invoicing.efaktura_cpds_preset_label') }}</label>
          <select id="efaktura-cpds-preset" v-model="selectedPresetId" class="invoicing-sf-input" @change="applyPreset">
            <option value="">{{ t('invoicing.efaktura_cpds_preset_choose') }}</option>
            <option v-for="preset in cpdsPresets" :key="preset.id" :value="preset.id">{{ preset.name }}</option>
            <option value="custom">{{ t('invoicing.efaktura_cpds_preset_other') }}</option>
          </select>
        </div>

        <div v-if="selectedPresetId === 'custom'">
          <label for="efaktura-base-url" class="invoicing-sf-label">{{ t('invoicing.efaktura_sapi_base_url') }}</label>
          <input
            id="efaktura-base-url"
            v-model="form.efaktura_sapi_base_url"
            type="url"
            class="invoicing-sf-input font-mono text-sm"
            :placeholder="t('invoicing.efaktura_sapi_base_url_placeholder')"
            required
            @input="handleBaseUrlInput"
          />
          <p class="text-xs text-gray-500 mt-1">{{ t('invoicing.efaktura_sapi_base_url_hint') }}</p>
        </div>
        <p v-else-if="form.efaktura_sapi_base_url" class="text-xs text-gray-500 font-mono">
          {{ form.efaktura_sapi_base_url }}
        </p>
        <div v-if="isEpostman" data-testid="epostman-setup" class="space-y-2 text-xs text-gray-700">
          <p>{{ t(isEpostmanTest ? 'invoicing.efaktura_epostman_activation_test' : 'invoicing.efaktura_epostman_activation') }}</p>
          <a :href="epostmanPortalUrl" target="_blank" rel="noopener noreferrer" class="inline-block text-indigo-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">{{ t('invoicing.efaktura_epostman_open_portal') }}</a>
          <p v-if="derivedParticipantId" class="font-medium">{{ t('invoicing.efaktura_peppol_derived_hint', { id: derivedParticipantId }) }}</p>
          <a
            href="https://postman.slovakodata.com/help/zaciname/onboarding/"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-block text-indigo-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
          >{{ t('invoicing.efaktura_epostman_setup_link') }}</a>
          <p v-if="!hasEpostmanIdentity" role="status" class="font-medium text-amber-900">{{ t('invoicing.efaktura_epostman_missing_identity') }}</p>
          <p v-if="isEpostmanTest" class="font-medium text-amber-900">{{ t('invoicing.efaktura_epostman_test_notice') }}</p>
        </div>
      </section>

      <!-- Step 2: credentials + connection test -->
      <section class="rounded-lg border border-gray-200 p-4 space-y-3">
        <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
          <span :class="stepBadgeClass(step2Done)">{{ step2Done ? '✓' : '2' }}</span>
          {{ t('invoicing.efaktura_step2_title') }}
        </h3>

        <div v-if="isEpostman" data-testid="epostman-credentials-guide" class="rounded-lg bg-gray-50 p-3 text-xs text-gray-700 space-y-2">
          <ol class="list-decimal pl-4 space-y-1">
            <li>{{ t('invoicing.efaktura_epostman_sapi_create') }}</li>
            <li>{{ t('invoicing.efaktura_epostman_sapi_copy') }}</li>
            <li>{{ t('invoicing.efaktura_epostman_sapi_finish') }}</li>
          </ol>
          <p>{{ t('invoicing.efaktura_epostman_sapi_not_api_key') }}</p>
          <a href="https://postman.slovakodata.com/help/portal/nastavenia/" target="_blank" rel="noopener noreferrer" class="inline-block text-indigo-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">{{ t('invoicing.efaktura_epostman_sapi_guide') }}</a>
        </div>

        <div>
          <label for="efaktura-client-id" class="invoicing-sf-label">{{ t('invoicing.efaktura_sapi_client_id') }}</label>
          <input
            id="efaktura-client-id"
            v-model="form.efaktura_sapi_client_id"
            type="text"
            class="invoicing-sf-input font-mono text-sm"
            autocomplete="off"
            required
            @input="invalidateConnectionTest"
          />
        </div>

        <div>
          <label for="efaktura-client-secret" class="invoicing-sf-label">{{ t('invoicing.efaktura_sapi_client_secret') }}</label>
          <input
            id="efaktura-client-secret"
            v-model="form.efaktura_sapi_client_secret"
            type="password"
            class="invoicing-sf-input font-mono text-sm"
            autocomplete="new-password"
            :placeholder="secretSet && !connectionChanged ? t('invoicing.efaktura_sapi_client_secret_placeholder_set') : t('invoicing.efaktura_sapi_client_secret_placeholder')"
            @input="invalidateConnectionTest"
          />
          <p v-if="secretSet && !connectionChanged && !form.efaktura_sapi_client_secret" class="text-xs text-emerald-700 mt-1">
            {{ t('invoicing.efaktura_sapi_client_secret_saved') }}
          </p>
        </div>

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="invoicing-btn-secondary text-sm"
            :disabled="testingConnection || saving"
            @click="testConnection"
          >
            {{ testingConnection ? t('invoicing.efaktura_testing_connection') : t('invoicing.efaktura_test_connection') }}
          </button>
          <p v-if="testMessage" class="text-xs" :class="testFailed ? 'text-red-600' : 'text-emerald-700'">
            {{ testMessage }}
          </p>
        </div>
        <p v-if="!testMessage && form.efaktura_connection_tested_at" class="text-xs text-gray-500">
          {{ t('invoicing.efaktura_connection_tested_at', { date: formatPollDate(form.efaktura_connection_tested_at) }) }}
        </p>
      </section>

      <!-- Step 3: options -->
      <section class="rounded-lg border border-gray-200 p-4 space-y-3">
        <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
          <span :class="stepBadgeClass(false)">3</span>
          {{ t(isEpostman ? 'invoicing.efaktura_epostman_step3_title' : 'invoicing.efaktura_step3_title') }}
        </h3>

        <div v-if="isEpostman" data-testid="epostman-connection-status" role="status" aria-live="polite" class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 space-y-2">
          <p class="font-medium">{{ t(`invoicing.efaktura_epostman_status_${receivingStatus}`) }}</p>
          <p class="text-xs">{{ t('invoicing.efaktura_epostman_test_scope') }}</p>
          <p v-if="receivingStatus === 'saved'" class="text-xs">{{ t('invoicing.efaktura_epostman_waiting_first_check') }}</p>
          <p v-else-if="receivingStatus === 'empty'" class="text-xs">{{ t('invoicing.efaktura_epostman_waiting_document') }}</p>
          <p class="text-xs">{{ t('invoicing.efaktura_epostman_expenses_next', { inbox: t('invoicing.efaktura_inbox_title') }) }}</p>
          <router-link :to="{ name: 'invoicing-expenses', params: { companyId: props.companyId } }" class="inline-block text-xs text-indigo-700 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">{{ t('invoicing.efaktura_epostman_open_expenses') }}</router-link>
        </div>

        <div class="space-y-2">
          <!-- Issuing is a full-payer obligation - inbound-only companies never see auto-send. -->
          <label v-if="!inboundOnly && !isEpostman" class="flex items-start gap-2 text-sm text-gray-700">
            <input
              v-model="form.efaktura_auto_send"
              type="checkbox"
              class="mt-0.5 rounded border-gray-300 text-indigo-600"
            />
            <span>{{ t('invoicing.efaktura_auto_send') }}</span>
          </label>
          <p v-if="isEpostman" data-testid="epostman-receiving-only" class="text-xs text-amber-900">{{ t('invoicing.efaktura_epostman_receiving_only') }}</p>
          <label class="flex items-start gap-2 text-sm text-gray-700">
            <input
              v-model="form.efaktura_inbound_enabled"
              type="checkbox"
              class="mt-0.5 rounded border-gray-300 text-indigo-600"
            />
            <span>{{ t('invoicing.efaktura_inbound_enabled') }}</span>
          </label>
          <p class="text-xs text-gray-500">{{ t('invoicing.efaktura_inbound_hint') }}</p>
          <p v-if="localFirst && form.efaktura_inbound_enabled" class="text-xs text-amber-800">
            {{ t('invoicing.efaktura_inbound_local_first_notice') }}
          </p>

          <div v-if="form.efaktura_inbound_enabled" class="rounded-lg border border-gray-200 bg-white p-3 space-y-2">
            <p v-if="form.efaktura_inbound_last_poll_at" class="text-xs text-gray-600">
              {{ t('invoicing.efaktura_inbound_last_poll', { date: formatPollDate(form.efaktura_inbound_last_poll_at) }) }}
            </p>
            <p v-else class="text-xs text-gray-500">{{ t('invoicing.efaktura_inbound_never_polled') }}</p>
            <p
              v-if="form.efaktura_inbound_last_poll_stats"
              class="text-xs text-gray-600"
            >
              {{
                t('invoicing.efaktura_inbound_last_poll_stats', {
                  imported: form.efaktura_inbound_last_poll_stats.imported,
                  acknowledged: form.efaktura_inbound_last_poll_stats.acknowledged,
                  skipped: form.efaktura_inbound_last_poll_stats.skipped,
                  failed: form.efaktura_inbound_last_poll_stats.failed,
                })
              }}
            </p>
            <button
              type="button"
              class="invoicing-btn-secondary text-sm"
              :disabled="pollingInbound || saving || hasUnsavedSettings || bridgeSaveFailed || !hasSavedConnection"
              @click="pollInboundNow"
            >
              {{ pollingInbound ? t('invoicing.efaktura_inbound_polling') : t('invoicing.efaktura_inbound_poll_now') }}
            </button>
            <p v-if="hasUnsavedSettings" class="text-xs text-gray-600">{{ t('invoicing.efaktura_epostman_save_before_poll') }}</p>
            <p v-if="pollMessage" class="text-xs" :class="pollError ? 'text-red-600' : 'text-emerald-700'">
              {{ pollMessage }}
            </p>
          </div>
        </div>

        <!-- Advanced: explicit Peppol ID override; the DIC/ICO derivation
             covers the normal case, so merchants never see the syntax. -->
        <details v-if="!inboundOnly && !isEpostman" class="pt-1">
          <summary class="text-xs text-gray-600 cursor-pointer select-none">
            {{ t('invoicing.efaktura_advanced') }}
          </summary>
          <div class="mt-2">
            <label for="efaktura-peppol-id" class="invoicing-sf-label">{{ t('invoicing.efaktura_peppol_participant_id') }}</label>
            <input
              id="efaktura-peppol-id"
              v-model="form.efaktura_peppol_participant_id"
              type="text"
              class="invoicing-sf-input font-mono text-sm"
              :placeholder="derivedParticipantId ?? t('invoicing.efaktura_peppol_participant_id_placeholder')"
            />
            <p v-if="derivedParticipantId && !form.efaktura_peppol_participant_id" class="text-xs text-emerald-700 mt-1">
              {{ t('invoicing.efaktura_peppol_derived_hint', { id: derivedParticipantId }) }}
            </p>
            <p v-else class="text-xs text-gray-500 mt-1">{{ t('invoicing.efaktura_peppol_participant_id_hint') }}</p>
          </div>
        </details>
      </section>
    </template>

    <div class="pt-2 border-t border-gray-100">
      <button type="submit" class="invoicing-btn-primary" :disabled="saving">
        {{ t('invoicing.save_my_details') }}
      </button>
      <p class="text-xs text-gray-500 mt-2">{{ t('invoicing.profile_save_note') }}</p>
      <p v-if="saveError" class="text-sm text-red-600 mt-2">{{ saveError }}</p>
    </div>
    </fieldset>
  </form>
</template>

<script setup lang="ts">
import { asApiError } from "../../utils/apiError";
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type {
  EfakturaCompanyMode,
  EfakturaInboundPollStats,
} from '../../composables/useCompanyEfakturaSettings';
import { asCompanyId } from '../../composables/useInvoicingCompany';
import { useEfakturaFeature } from '../../composables/useEfakturaFeature';
import { allCompaniesDetailQuery, useInvoicingEvolu } from '../../evolu/client';
import { evoluCompanyToApi, type EvoluCompanyRow } from '../../evolu/companyMap';
import { updateLocalEfakturaSettings } from '../../evolu/companySettingsCrud';
import { matchCompanyByIdentity } from '../../evolu/duplicateCompanies';
import { ensureBridgeCompanyIdForLocalCompany } from '../../evolu/bridgeCompanyEnsure';
import { refreshEfakturaInboxLive, reloadEfakturaInboxLive } from '../../evolu/efakturaInboxLive';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import { invoicingApi } from '../../services/api';
import { useStoresStore } from '../../store/stores';
import { useInvoicingSaveFeedback } from '../../composables/useInvoicingSaveFeedback';
import { formatPeppolEndpoint, resolvePeppolEndpoint } from '../../utils/peppolEndpoint';
import {
  efakturaSecretIsSet,
  efakturaSettingsFromCompany,
  type CompanyEfakturaSettingsState,
} from '../../composables/useCompanyEfakturaSettings';

const props = withDefaults(
  defineProps<{
    companyId: string;
    company: (Record<string, unknown> & { app_settings?: Record<string, unknown> }) | null;
    /**
     * 'inbound_only' for SK non-payers: they must receive e-invoices but
     * may not issue them, so auto-send and the Peppol sender ID are hidden
     * and auto-send is forced off in the payload (server rejects it too).
     */
    mode?: EfakturaCompanyMode;
  }>(),
  { mode: 'full' },
);

const emit = defineEmits<{
  updated: [company: Record<string, unknown>];
}>();

const { t, locale } = useI18n();
const { notifySaved } = useInvoicingSaveFeedback();
const localFirst = isInvoicingLocalFirst();
const evolu = localFirst ? useInvoicingEvolu() : null;
const storesStore = useStoresStore();
const efakturaFeature = useEfakturaFeature();
const builtInEpostmanPresets = computed(() => [
  { id: 'epostman-production', name: t('invoicing.efaktura_epostman_production'), base_url: 'https://postman.slovakodata.com' },
  { id: 'epostman-test', name: t('invoicing.efaktura_epostman_sandbox'), base_url: 'https://testpostman.slovakodata.com' },
]);
const cpdsPresets = computed(() => [
  ...builtInEpostmanPresets.value,
  ...efakturaFeature.cpdsPresets.value.filter((preset) => !builtInEpostmanPresets.value.some(
    (builtIn) => builtIn.id === preset.id || normalizeBaseUrl(builtIn.base_url) === normalizeBaseUrl(preset.base_url),
  )),
]);
const saving = ref(false);
const saveError = ref('');
const secretSet = ref(false);
const pollingInbound = ref(false);
const pollMessage = ref('');
const pollError = ref(false);
const testingConnection = ref(false);
const testMessage = ref('');
const testFailed = ref(false);
const selectedPresetId = ref('');
const credentialBaseUrl = ref('');
const credentialsDiscarded = ref(false);
const bridgeSaveFailed = ref(false);
const savedSettings = ref<CompanyEfakturaSettingsState>(efakturaSettingsFromCompany(null));
const form = reactive<CompanyEfakturaSettingsState>(efakturaSettingsFromCompany(null));
let connectionGeneration = 0;
let saveRequestId = 0;
let pollRequestId = 0;
let testRequestId = 0;
const synchronizedCompanyId = ref(props.companyId);
let active = true;
onBeforeUnmount(() => { active = false; connectionGeneration++; });
const loadedCompanyMatches = computed(() => localFirst
  || (props.company?.id === props.companyId && synchronizedCompanyId.value === props.companyId));
const inboundOnly = computed(() => props.mode === 'inbound_only');
const isEpostman = computed(() => ['https://postman.slovakodata.com', 'https://testpostman.slovakodata.com']
  .includes(normalizeBaseUrl(form.efaktura_sapi_base_url)));
const isEpostmanTest = computed(() => normalizeBaseUrl(form.efaktura_sapi_base_url) === 'https://testpostman.slovakodata.com');
const connectionChanged = computed(() => normalizeBaseUrl(form.efaktura_sapi_base_url)
  !== normalizeBaseUrl(String(props.company?.app_settings?.efaktura_sapi_base_url ?? ''))
  || form.efaktura_sapi_client_id.trim() !== String(props.company?.app_settings?.efaktura_sapi_client_id ?? '').trim());

const epostmanPortalUrl = computed(() => isEpostmanTest.value ? 'https://testpostman.slovakodata.com' : 'https://postman.slovakodata.com');
const hasEpostmanIdentity = computed(() => /^0245:\d{10}$/.test(derivedParticipantId.value ?? ''));
const hasUnsavedSettings = computed(() => {
  const saved = savedSettings.value;
  return form.efaktura_enabled !== saved.efaktura_enabled
    || form.efaktura_auto_send !== saved.efaktura_auto_send
    || form.efaktura_inbound_enabled !== saved.efaktura_inbound_enabled
    || normalizeBaseUrl(form.efaktura_sapi_base_url) !== normalizeBaseUrl(saved.efaktura_sapi_base_url)
    || form.efaktura_sapi_client_id.trim() !== saved.efaktura_sapi_client_id.trim()
    || form.efaktura_peppol_participant_id !== saved.efaktura_peppol_participant_id
    || form.efaktura_sapi_client_secret !== '' || credentialsDiscarded.value;
});
const hasSavedConnection = computed(() => savedSettings.value.efaktura_enabled
  && savedSettings.value.efaktura_inbound_enabled
  && savedSettings.value.efaktura_sapi_base_url !== ''
  && savedSettings.value.efaktura_sapi_client_id !== '' && secretSet.value
  && (!isEpostman.value || hasEpostmanIdentity.value));
const receivingStatus = computed(() => {
  if (bridgeSaveFailed.value) return 'save_failed';
  if (testFailed.value) return 'test_failed';
  if (hasUnsavedSettings.value) return 'unsaved';
  if (!hasSavedConnection.value) return 'setup';
  if (!form.efaktura_connection_tested_at) return 'untested';
  if (pollError.value || (form.efaktura_inbound_last_poll_stats?.failed ?? 0) > 0) return 'poll_failed';
  if (!form.efaktura_inbound_last_poll_at) return 'saved';
  const stats = form.efaktura_inbound_last_poll_stats;
  if (!stats) return 'checked';
  return stats.imported > 0 ? 'received' : 'empty';
});

// Provider selection records a choice, not receiving registration.
const step1Done = computed(() => form.efaktura_sapi_base_url.trim() !== '');
const step2Done = computed(
  () =>
    form.efaktura_sapi_client_id.trim() !== ''
    && (secretSet.value || form.efaktura_sapi_client_secret !== '')
    && !testFailed.value && form.efaktura_connection_tested_at !== null,
);

/** Server payload carries the derived ID; local-first derives client-side. */
const derivedParticipantId = computed(() => {
  const raw = (props.company?.app_settings ?? {}) as Record<string, unknown>;
  const fromServer = raw.efaktura_peppol_participant_id_derived;
  if (typeof fromServer === 'string' && fromServer !== '') {
    return fromServer;
  }

  return formatPeppolEndpoint(
    resolvePeppolEndpoint({
      tax_id: props.company?.tax_id as string | null,
      registration_number: props.company?.registration_number as string | null,
      country: props.company?.country as string | null,
      jurisdiction: props.company?.jurisdiction as string | null,
    }),
  );
});

watch(
  () => [props.company, props.companyId] as const,
  ([company]) => {
    if (!localFirst && company?.id !== props.companyId) return;
    // Local persistence updates parent props before the bridge request finishes.
    // Keep the submitted form stable until that request has a result.
    if (saving.value && synchronizedCompanyId.value === props.companyId) return;
    synchronizedCompanyId.value = props.companyId;
    connectionGeneration++;
    testMessage.value = '';
    testingConnection.value = false;
    Object.assign(form, efakturaSettingsFromCompany(company));
    savedSettings.value = efakturaSettingsFromCompany(company);
    credentialBaseUrl.value = normalizeBaseUrl(form.efaktura_sapi_base_url);
    credentialsDiscarded.value = false;
    pollMessage.value = '';
    pollError.value = false;
    secretSet.value = efakturaSecretIsSet(company);
    bridgeSaveFailed.value = company?.app_settings?.efaktura_inbound_bridge_sync_pending === true;
    syncSelectedPreset();
  },
  { immediate: true, deep: true }
);

watch(() => props.companyId, () => {
  saveRequestId++;
  pollRequestId++;
  testRequestId++;
  bridgeSaveFailed.value = props.company?.app_settings?.efaktura_inbound_bridge_sync_pending === true;
  saveError.value = '';
  saving.value = false;
  pollingInbound.value = false;
  testFailed.value = false;
});

// Receiving is the baseline for every SK company. Never opt a customer into sending.
watch(
  () => form.efaktura_enabled,
  (enabled, wasEnabled) => {
    if (!enabled || wasEnabled || form.efaktura_sapi_base_url.trim() !== '') {
      return;
    }
    form.efaktura_inbound_enabled = true;
  },
);

function normalizeBaseUrl(url: string): string {
  const value = url.trim().replace(/\/+$/, '');
  try {
    const parsed = new URL(value);
    if (['https://postman.slovakodata.com', 'https://testpostman.slovakodata.com'].includes(parsed.origin)
      && parsed.username === '' && parsed.password === '' && parsed.pathname === '/' && parsed.search === '' && parsed.hash === '') {
      return parsed.origin;
    }
  } catch {
    // Keep malformed input intact for validation instead of silently repairing it.
  }
  return value;
}

function syncSelectedPreset() {
  const url = normalizeBaseUrl(form.efaktura_sapi_base_url);
  if (url === '') {
    selectedPresetId.value = '';
    return;
  }
  const match = cpdsPresets.value.find((preset) => normalizeBaseUrl(preset.base_url) === url);
  selectedPresetId.value = match ? match.id : 'custom';
}

function applyPreset() {
  if (selectedPresetId.value === '' || selectedPresetId.value === 'custom') {
    form.efaktura_sapi_base_url = '';
    handleBaseUrlInput();
    return;
  }
  const preset = cpdsPresets.value.find((row) => row.id === selectedPresetId.value);
  if (preset) {
    form.efaktura_sapi_base_url = preset.base_url;
    handleBaseUrlInput();
    if (isEpostman.value) {
      form.efaktura_auto_send = false;
      form.efaktura_inbound_enabled = true;
      form.efaktura_peppol_participant_id = '';
    }
  }
}

function handleBaseUrlInput() {
  const next = normalizeBaseUrl(form.efaktura_sapi_base_url);
  if (next !== credentialBaseUrl.value) {
    form.efaktura_sapi_client_id = '';
    form.efaktura_sapi_client_secret = '';
    secretSet.value = false;
    credentialsDiscarded.value = true;
    form.efaktura_inbound_last_poll_at = null;
    form.efaktura_inbound_last_poll_stats = null;
    pollMessage.value = '';
    pollError.value = false;
    credentialBaseUrl.value = next;
  }
  invalidateConnectionTest();
}

/**
 * A URL/credential edit makes the last successful test stale. Wired as
 * `@input` (not a watcher) so the initial props.company synchronization
 * keeps the stored timestamp.
 */
function invalidateConnectionTest() {
  connectionGeneration++;
  form.efaktura_connection_tested_at = null;
  form.efaktura_inbound_last_poll_at = null;
  form.efaktura_inbound_last_poll_stats = null;
  testMessage.value = '';
  testFailed.value = false;
  testingConnection.value = false;
}

function stepBadgeClass(done: boolean): string {
  return [
    'inline-flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-bold',
    done ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700',
  ].join(' ');
}

function formatPollDate(iso: string) {
  const d = new Date(iso.includes('T') ? iso : `${iso}T12:00:00`);
  return d.toLocaleString(locale.value);
}

function applyInboundPollMeta(polledAt: string | null, stats: EfakturaInboundPollStats | null) {
  form.efaktura_inbound_last_poll_at = polledAt;
  form.efaktura_inbound_last_poll_stats = stats;
}

function buildPayload(): Partial<CompanyEfakturaSettingsState> {
  const payload: Partial<CompanyEfakturaSettingsState> = { ...form };
  if (inboundOnly.value) {
    payload.efaktura_auto_send = false;
    payload.efaktura_peppol_participant_id = '';
  }
  if (isEpostman.value) {
    payload.efaktura_auto_send = false;
    payload.efaktura_peppol_participant_id = '';
  }
  delete payload.efaktura_inbound_last_poll_at;
  delete payload.efaktura_inbound_last_poll_stats;
  // The server stamps the tested-at timestamp itself; Evolu keeps its own.
  delete payload.efaktura_connection_tested_at;
  if (!payload.efaktura_sapi_client_secret) {
    delete payload.efaktura_sapi_client_secret;
  }
  return payload;
}

/** Stored client-side plaintext secret (local-first only) as test fallback. */
function storedLocalSecret(): string {
  if (connectionChanged.value || credentialsDiscarded.value) return '';
  const raw = (props.company?.app_settings ?? {}) as Record<string, unknown>;
  const plain = raw.efaktura_sapi_client_secret;
  return typeof plain === 'string' ? plain : '';
}

async function testConnection() {
  const generation = connectionGeneration;
  const requestId = ++testRequestId;
  const companyId = props.companyId;
  const isCurrent = () => active && requestId === testRequestId && generation === connectionGeneration && companyId === props.companyId;
  testingConnection.value = true;
  testMessage.value = '';
  testFailed.value = false;
  form.efaktura_connection_tested_at = null;
  try {
    if ((connectionChanged.value || credentialsDiscarded.value) && !form.efaktura_sapi_client_secret) {
      testFailed.value = true;
      testMessage.value = t('invoicing.efaktura_connection_new_credentials');
      return;
    }
    const payload: Record<string, unknown> = {
      efaktura_sapi_base_url: form.efaktura_sapi_base_url || null,
      efaktura_sapi_client_id: form.efaktura_sapi_client_id || null,
      efaktura_sapi_client_secret: form.efaktura_sapi_client_secret || null,
    };

    let result: { ok?: boolean; code?: string; message?: string; tested_at?: string | null };
    if (localFirst) {
      if (!payload.efaktura_sapi_client_secret) {
        payload.efaktura_sapi_client_secret = storedLocalSecret() || null;
      }
      result = await invoicingApi.efaktura.testConnectionEphemeral(payload);
    } else {
      result = await invoicingApi.efaktura.testConnection(companyId, payload);
    }
    if (!isCurrent()) return;

    if (result.ok) {
      testFailed.value = false;
      testMessage.value = t('invoicing.efaktura_test_ok');
      form.efaktura_connection_tested_at = result.tested_at ?? new Date().toISOString();
    } else {
      testFailed.value = true;
      const code = result.code ?? 'error';
      testMessage.value = t(`invoicing.efaktura_test_error_${code}`, { message: result.message ?? '' });
    }
  } catch (rawError) {
    if (!isCurrent()) return;
    const e = asApiError(rawError);
    testFailed.value = true;
    testMessage.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    if (isCurrent()) testingConnection.value = false;
  }
}

function emitUpdatedFromEvoluRow() {
  if (!evolu) return;
  const row = evolu.getQueryRows(allCompaniesDetailQuery).find((c) => c.id === props.companyId);
  if (!row) return;
  emit(
    'updated',
    evoluCompanyToApi(row as EvoluCompanyRow, (storeId) => {
      const store = storesStore.stores.find((s) => s.id === storeId);
      return store
        ? {
            id: store.id,
            name: store.name,
            ...(store.default_currency !== undefined ? { default_currency: store.default_currency } : {}),
          }
        : undefined;
    }),
  );
}

/**
 * Local-first: the CPDS is polled on the bridge company (the server row that
 * mirrors this company's identity) - received documents land in its inbox
 * and the Expenses page imports them into Evolu.
 */
async function resolveBridgeCompanyIdOrFail(companyId = props.companyId): Promise<string> {
  const bridge = await ensureBridgeCompanyIdForLocalCompany(companyId);
  if (!bridge.ok) {
    throw new Error(t('invoicing.efaktura_inbound_bridge_failed'));
  }
  if (!bridge.bridgeCompanyId) {
    throw new Error(t('invoicing.efaktura_inbound_bridge_missing'));
  }
  return bridge.bridgeCompanyId;
}

/** Push the inbound-relevant subset of the settings to the bridge company. */
type ReceivingCompanyIdentity = {
  legal_name: string; registration_number: string | null; tax_id: string | null;
  jurisdiction: string | null; country: string | null;
};

async function syncInboundSettingsToBridge(payload: Partial<CompanyEfakturaSettingsState>, companyId: string, secret: string, identity: ReceivingCompanyIdentity): Promise<void> {
  const bridgeCompanyId = await resolveBridgeCompanyIdOrFail(companyId);
  const inboundOn = Boolean(payload.efaktura_enabled) && Boolean(payload.efaktura_inbound_enabled);
  const bridgePayload: Record<string, unknown> = {
    efaktura_enabled: inboundOn,
    efaktura_inbound_enabled: inboundOn,
    // Issuing never runs through the bridge - documents live in Evolu.
    efaktura_auto_send: false,
  };
  if (inboundOn) {
    // Bridge creation only carries numbering identity. Receiving also needs the
    // local tax identity before a derived participant can replace an override.
    const participant = formatPeppolEndpoint(resolvePeppolEndpoint(identity));
    const epostman = ['https://postman.slovakodata.com', 'https://testpostman.slovakodata.com']
      .includes(normalizeBaseUrl(payload.efaktura_sapi_base_url ?? ''));
    if (epostman && !/^0245:\d{10}$/.test(participant ?? '')) throw new Error(t('invoicing.efaktura_epostman_missing_identity'));
    if (!payload.efaktura_peppol_participant_id && /^0245:\d{10}$/.test(participant ?? '')) {
      const bridge = await invoicingApi.companies.get<Record<string, unknown>>(bridgeCompanyId);
      const matchesIdentity = (candidate: Record<string, unknown>) => candidate.id === bridgeCompanyId
        && matchCompanyByIdentity([{ id: String(candidate.id), legal_name: String(candidate.legal_name ?? ''),
          registration_number: typeof candidate.registration_number === 'string' ? candidate.registration_number : null }], identity) !== null;
      if (!matchesIdentity(bridge) || bridge.jurisdiction !== 'eu_sk'
        || formatPeppolEndpoint(resolvePeppolEndpoint({ tax_id: identity.tax_id,
          country: typeof bridge.country === 'string' ? bridge.country : null,
          jurisdiction: bridge.jurisdiction })) !== participant) throw new Error(t('invoicing.efaktura_inbound_bridge_failed'));
      const updated = await invoicingApi.companies.update<Record<string, unknown>>(bridgeCompanyId, { tax_id: identity.tax_id });
      if (!matchesIdentity(updated) || formatPeppolEndpoint(resolvePeppolEndpoint({
        tax_id: typeof updated.tax_id === 'string' ? updated.tax_id : null,
        country: typeof updated.country === 'string' ? updated.country : null,
        jurisdiction: typeof updated.jurisdiction === 'string' ? updated.jurisdiction : null,
      })) !== participant) throw new Error(t('invoicing.efaktura_inbound_bridge_failed'));
    }
    bridgePayload.efaktura_sapi_base_url = payload.efaktura_sapi_base_url || null;
    bridgePayload.efaktura_sapi_client_id = payload.efaktura_sapi_client_id || null;
    bridgePayload.efaktura_peppol_participant_id = payload.efaktura_peppol_participant_id || '';
    if (secret) {
      bridgePayload.efaktura_sapi_client_secret = secret;
    }
  }
  await invoicingApi.companies.updateAppSettings(bridgeCompanyId, bridgePayload);
  await reloadEfakturaInboxLive();
}

async function pollInboundNow() {
  if (hasUnsavedSettings.value || bridgeSaveFailed.value || !hasSavedConnection.value) return;
  const generation = connectionGeneration;
  const requestId = ++pollRequestId;
  const companyId = props.companyId;
  const isCurrent = () => active && requestId === pollRequestId && generation === connectionGeneration && companyId === props.companyId;
  pollingInbound.value = true;
  pollMessage.value = '';
  pollError.value = false;
  try {
    const targetCompanyId = localFirst ? await resolveBridgeCompanyIdOrFail(companyId) : companyId;
    if (!isCurrent()) return;
    const data = await invoicingApi.efaktura.pollInbound<{
      imported?: unknown; acknowledged?: unknown; skipped?: unknown; failed?: unknown; polled_at?: unknown;
    }>(targetCompanyId);
    if (!isCurrent()) return;
    const stats: EfakturaInboundPollStats = {
      imported: Number(data.imported ?? 0),
      acknowledged: Number(data.acknowledged ?? 0),
      skipped: Number(data.skipped ?? 0),
      failed: Number(data.failed ?? 0),
    };
    applyInboundPollMeta(data.polled_at ? String(data.polled_at) : new Date().toISOString(), stats);
    pollError.value = stats.failed > 0;
    pollMessage.value = t('invoicing.efaktura_inbound_poll_result', stats);
    if (localFirst && evolu) {
      updateLocalEfakturaSettings(evolu, asCompanyId(props.companyId), {
        efaktura_inbound_last_poll_at: form.efaktura_inbound_last_poll_at,
        efaktura_inbound_last_poll_stats: stats,
      });
      await refreshEfakturaInboxLive(true);
      return;
    }
    if (props.company) {
      emit('updated', {
        ...props.company,
        app_settings: {
          ...(props.company.app_settings as Record<string, unknown>),
          efaktura_inbound_last_poll_at: form.efaktura_inbound_last_poll_at,
          efaktura_inbound_last_poll_stats: stats,
        },
      });
    }
  } catch (rawError) {
    const e = asApiError(rawError);
    if (!isCurrent()) return;
    pollError.value = true;
    pollMessage.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    if (requestId === pollRequestId) pollingInbound.value = false;
  }
}

async function save() {
  if (!loadedCompanyMatches.value) return;
  const generation = connectionGeneration;
  const requestId = ++saveRequestId;
  const companyId = props.companyId;
  const isCurrent = () => active && requestId === saveRequestId && generation === connectionGeneration && companyId === props.companyId;
  saving.value = true;
  saveError.value = '';
  try {
    if (form.efaktura_enabled && (connectionChanged.value || credentialsDiscarded.value) && !form.efaktura_sapi_client_secret) {
      saveError.value = t('invoicing.efaktura_connection_new_credentials');
      return;
    }
    const payload = buildPayload();
    const secret = payload.efaktura_sapi_client_secret || storedLocalSecret();
    const localIdentity = {
      legal_name: String(props.company?.legal_name ?? ''),
      registration_number: typeof props.company?.registration_number === 'string' ? props.company.registration_number : null,
      tax_id: typeof props.company?.tax_id === 'string' ? props.company.tax_id : null,
      jurisdiction: typeof props.company?.jurisdiction === 'string' ? props.company.jurisdiction : null,
      country: typeof props.company?.country === 'string' ? props.company.country : null,
    };

    if (localFirst && evolu) {
      // Retain an encrypted local retry marker across reloads and interruption.
      // This metadata belongs to local custody and is never sent to the provider.
      const localPayload = {
        ...payload,
        efaktura_connection_tested_at: form.efaktura_connection_tested_at,
        efaktura_inbound_last_poll_at: form.efaktura_inbound_last_poll_at,
        efaktura_inbound_last_poll_stats: form.efaktura_inbound_last_poll_stats,
        efaktura_inbound_bridge_sync_pending: true,
      };
      const result = updateLocalEfakturaSettings(evolu, asCompanyId(companyId), localPayload);
      if (!result.ok) {
        saveError.value = t('invoicing.company_save_validation_error');
        return;
      }
      // Receiving needs the server poller - mirror the inbound settings to
      // the bridge company (only these; issuing stays local). A bridge
      // failure must not undo the local save, so it reports separately.
      try {
        await syncInboundSettingsToBridge(payload, companyId, secret, localIdentity);
        if (!isCurrent()) return;
        const completedPayload = { ...localPayload, efaktura_inbound_bridge_sync_pending: false };
        const completed = updateLocalEfakturaSettings(evolu, asCompanyId(companyId), completedPayload);
        if (!completed.ok) {
          bridgeSaveFailed.value = true;
          saveError.value = t('invoicing.efaktura_epostman_bridge_save_failed');
          return;
        }
        bridgeSaveFailed.value = false;
      } catch {
        if (!isCurrent()) return;
        bridgeSaveFailed.value = true;
        saveError.value = t('invoicing.efaktura_epostman_bridge_save_failed');
        return;
      }
      form.efaktura_sapi_client_secret = '';
      credentialsDiscarded.value = false;
      savedSettings.value = { ...form };
      emitUpdatedFromEvoluRow();
      const row = evolu.getQueryRows(allCompaniesDetailQuery).find((c) => c.id === props.companyId);
      if (row) {
        secretSet.value = efakturaSecretIsSet(
          evoluCompanyToApi(row as EvoluCompanyRow, (storeId) => {
            const store = storesStore.stores.find((s) => s.id === storeId);
            return store
              ? {
                  id: store.id,
                  name: store.name,
                  ...(store.default_currency !== undefined ? { default_currency: store.default_currency } : {}),
                }
              : undefined;
          }),
        );
      }
      notifySaved();
      return;
    }

    const updated = await invoicingApi.companies.updateAppSettings<Record<string, unknown>>(companyId, payload);
    if (!isCurrent()) return;
    bridgeSaveFailed.value = false;
    credentialsDiscarded.value = false;
    savedSettings.value = efakturaSettingsFromCompany(updated);
    emit('updated', updated);
    form.efaktura_sapi_client_secret = '';
    secretSet.value = efakturaSecretIsSet(updated);
    notifySaved();
  } catch (rawError) {
    if (!isCurrent()) return;
    const e = asApiError(rawError);
    saveError.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    if (requestId === saveRequestId) saving.value = false;
  }
}

onMounted(() => {
  void efakturaFeature.load().then(() => syncSelectedPreset());
});
</script>
