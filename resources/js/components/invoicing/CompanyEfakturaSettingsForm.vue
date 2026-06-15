<template>
  <form class="space-y-6 max-w-2xl" @submit.prevent="save">
    <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-4 text-sm text-gray-700 space-y-2">
      <p>{{ t('invoicing.efaktura_intro') }}</p>
      <p class="text-xs text-gray-600">{{ t('invoicing.efaktura_cpds_note') }}</p>
    </div>

    <label class="flex items-start gap-2 text-sm text-gray-800">
      <input
        v-model="form.efaktura_enabled"
        type="checkbox"
        class="mt-0.5 rounded border-gray-300 text-indigo-600"
      />
      <span>{{ t('invoicing.efaktura_enabled') }}</span>
    </label>

    <fieldset v-if="form.efaktura_enabled" class="space-y-4 border-0 p-0 m-0">
      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.efaktura_sapi_base_url') }}</label>
        <input
          v-model="form.efaktura_sapi_base_url"
          type="url"
          class="invoicing-sf-input font-mono text-sm"
          :placeholder="t('invoicing.efaktura_sapi_base_url_placeholder')"
          required
        />
        <p class="text-xs text-gray-500 mt-1">{{ t('invoicing.efaktura_sapi_base_url_hint') }}</p>
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.efaktura_peppol_participant_id') }}</label>
        <input
          v-model="form.efaktura_peppol_participant_id"
          type="text"
          class="invoicing-sf-input font-mono text-sm"
          :placeholder="t('invoicing.efaktura_peppol_participant_id_placeholder')"
          required
        />
        <p class="text-xs text-gray-500 mt-1">{{ t('invoicing.efaktura_peppol_participant_id_hint') }}</p>
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.efaktura_sapi_client_id') }}</label>
        <input
          v-model="form.efaktura_sapi_client_id"
          type="text"
          class="invoicing-sf-input font-mono text-sm"
          autocomplete="off"
          required
        />
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.efaktura_sapi_client_secret') }}</label>
        <input
          v-model="form.efaktura_sapi_client_secret"
          type="password"
          class="invoicing-sf-input font-mono text-sm"
          autocomplete="new-password"
          :placeholder="secretSet ? t('invoicing.efaktura_sapi_client_secret_placeholder_set') : t('invoicing.efaktura_sapi_client_secret_placeholder')"
        />
        <p v-if="secretSet && !form.efaktura_sapi_client_secret" class="text-xs text-emerald-700 mt-1">
          {{ t('invoicing.efaktura_sapi_client_secret_saved') }}
        </p>
      </div>

      <div class="space-y-2 pt-2">
        <label class="flex items-start gap-2 text-sm text-gray-700">
          <input
            v-model="form.efaktura_auto_send"
            type="checkbox"
            class="mt-0.5 rounded border-gray-300 text-indigo-600"
          />
          <span>{{ t('invoicing.efaktura_auto_send') }}</span>
        </label>
        <label class="flex items-start gap-2 text-sm text-gray-700">
          <input
            v-model="form.efaktura_inbound_enabled"
            type="checkbox"
            class="mt-0.5 rounded border-gray-300 text-indigo-600"
          />
          <span>{{ t('invoicing.efaktura_inbound_enabled') }}</span>
        </label>
        <p class="text-xs text-gray-500">{{ t('invoicing.efaktura_inbound_hint') }}</p>

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
            :disabled="pollingInbound || saving"
            @click="pollInboundNow"
          >
            {{ pollingInbound ? t('invoicing.efaktura_inbound_polling') : t('invoicing.efaktura_inbound_poll_now') }}
          </button>
          <p v-if="pollMessage" class="text-xs" :class="pollError ? 'text-red-600' : 'text-emerald-700'">
            {{ pollMessage }}
          </p>
        </div>
      </div>
    </fieldset>

    <div class="pt-2 border-t border-gray-100">
      <button type="submit" class="invoicing-btn-primary" :disabled="saving">
        {{ t('invoicing.save_my_details') }}
      </button>
      <p class="text-xs text-gray-500 mt-2">{{ t('invoicing.profile_save_note') }}</p>
      <p v-if="saveError" class="text-sm text-red-600 mt-2">{{ saveError }}</p>
    </div>
  </form>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { EfakturaInboundPollStats } from '../../composables/useCompanyEfakturaSettings';
import { asCompanyId } from '../../composables/useInvoicingCompany';
import { allCompaniesDetailQuery, useInvoicingEvolu } from '../../evolu/client';
import { evoluCompanyToApi, type EvoluCompanyRow } from '../../evolu/companyMap';
import { updateLocalEfakturaSettings } from '../../evolu/companySettingsCrud';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import api from '../../services/api';
import { useStoresStore } from '../../store/stores';
import {
  efakturaSecretIsSet,
  efakturaSettingsFromCompany,
  type CompanyEfakturaSettingsState,
} from '../../composables/useCompanyEfakturaSettings';

const props = defineProps<{
  companyId: string;
  company: Record<string, any> | null;
}>();

const emit = defineEmits<{
  updated: [company: Record<string, any>];
}>();

const { t, locale } = useI18n();
const localFirst = isInvoicingLocalFirst();
const evolu = localFirst ? useInvoicingEvolu() : null;
const storesStore = useStoresStore();
const saving = ref(false);
const saveError = ref('');
const secretSet = ref(false);
const pollingInbound = ref(false);
const pollMessage = ref('');
const pollError = ref(false);
const form = reactive<CompanyEfakturaSettingsState>(efakturaSettingsFromCompany(null));

watch(
  () => props.company,
  (company) => {
    Object.assign(form, efakturaSettingsFromCompany(company));
    secretSet.value = efakturaSecretIsSet(company);
  },
  { immediate: true, deep: true }
);

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
  delete payload.efaktura_inbound_last_poll_at;
  delete payload.efaktura_inbound_last_poll_stats;
  if (!payload.efaktura_sapi_client_secret) {
    delete payload.efaktura_sapi_client_secret;
  }
  return payload;
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
        ? { id: store.id, name: store.name, default_currency: store.default_currency }
        : undefined;
    }),
  );
}

async function pollInboundNow() {
  if (localFirst) {
    pollError.value = true;
    pollMessage.value = t('invoicing.local_first_efaktura_inbound_unavailable');
    return;
  }

  pollingInbound.value = true;
  pollMessage.value = '';
  pollError.value = false;
  try {
    const res = await api.post(`/invoicing/companies/${props.companyId}/efaktura/poll-inbound`);
    const data = res.data?.data ?? {};
    const stats: EfakturaInboundPollStats = {
      imported: Number(data.imported ?? 0),
      acknowledged: Number(data.acknowledged ?? 0),
      skipped: Number(data.skipped ?? 0),
      failed: Number(data.failed ?? 0),
    };
    applyInboundPollMeta(data.polled_at ? String(data.polled_at) : new Date().toISOString(), stats);
    pollMessage.value = t('invoicing.efaktura_inbound_poll_result', stats);
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
  } catch (e: any) {
    pollError.value = true;
    pollMessage.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    pollingInbound.value = false;
  }
}

async function save() {
  saving.value = true;
  saveError.value = '';
  try {
    const payload = buildPayload();

    if (localFirst && evolu) {
      const result = updateLocalEfakturaSettings(evolu, asCompanyId(props.companyId), payload);
      if (!result.ok) {
        saveError.value = t('invoicing.company_save_validation_error');
        return;
      }
      form.efaktura_sapi_client_secret = '';
      emitUpdatedFromEvoluRow();
      const row = evolu.getQueryRows(allCompaniesDetailQuery).find((c) => c.id === props.companyId);
      if (row) {
        secretSet.value = efakturaSecretIsSet(
          evoluCompanyToApi(row as EvoluCompanyRow, (storeId) => {
            const store = storesStore.stores.find((s) => s.id === storeId);
            return store
              ? { id: store.id, name: store.name, default_currency: store.default_currency }
              : undefined;
          }),
        );
      }
      return;
    }

    const res = await api.patch(`/invoicing/companies/${props.companyId}/app-settings`, payload);
    emit('updated', res.data.data);
    form.efaktura_sapi_client_secret = '';
    secretSet.value = efakturaSecretIsSet(res.data.data);
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    saving.value = false;
  }
}
</script>
