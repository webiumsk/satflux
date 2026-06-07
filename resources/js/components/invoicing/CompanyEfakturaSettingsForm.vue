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
import api from '../../services/api';
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

const { t } = useI18n();
const saving = ref(false);
const saveError = ref('');
const secretSet = ref(false);
const form = reactive<CompanyEfakturaSettingsState>(efakturaSettingsFromCompany(null));

watch(
  () => props.company,
  (company) => {
    Object.assign(form, efakturaSettingsFromCompany(company));
    secretSet.value = efakturaSecretIsSet(company);
  },
  { immediate: true, deep: true }
);

async function save() {
  saving.value = true;
  saveError.value = '';
  try {
    const payload: Record<string, unknown> = { ...form };
    if (!payload.efaktura_sapi_client_secret) {
      delete payload.efaktura_sapi_client_secret;
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
