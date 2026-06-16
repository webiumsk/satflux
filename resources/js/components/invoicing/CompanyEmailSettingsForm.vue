<template>
  <section class="invoicing-card-pad">
    <CompanyAppTabsNav :company-id="companyId" active-tab="emails" />

    <form class="space-y-10" @submit.prevent="saveAll">
      <!-- Odosielanie emailov -->
      <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ t('invoicing.email_sending_title') }}</h2>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <div class="lg:col-span-3 space-y-2">
            <label
              v-for="method in deliveryMethods"
              :key="method.id"
              class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer"
            >
              <input
                v-model="form.delivery_method"
                type="radio"
                :value="method.id"
                class="text-indigo-600"
                @change="onDeliveryMethodChange(method.id)"
              />
              <span>{{ t(method.labelKey) }}</span>
            </label>
          </div>

          <div class="lg:col-span-9">
            <div v-if="form.delivery_method === 'system'" class="text-sm text-gray-600 p-4 bg-gray-50 rounded-lg">
              {{ t('invoicing.email_delivery_system_hint') }}
            </div>

            <div
              v-else-if="form.delivery_method === 'gmail' || form.delivery_method === 'office'"
              class="text-sm text-amber-800 p-4 bg-amber-50 rounded-lg border border-amber-200"
            >
              {{ t('invoicing.email_oauth_coming_soon') }}
            </div>

            <div v-else-if="form.delivery_method === 'smtp'" class="space-y-4 max-w-xl">
              <h3 class="font-medium text-gray-900">{{ t('invoicing.email_smtp_title') }}</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="invoicing-sf-label">{{ t('invoicing.email_smtp_login') }}</label>
                  <input v-model="form.smtp.username" type="email" class="invoicing-sf-input" autocomplete="off" />
                </div>
                <div>
                  <label class="invoicing-sf-label">{{ t('invoicing.email_smtp_password') }}</label>
                  <input
                    v-model="form.smtp.password"
                    type="password"
                    class="invoicing-sf-input"
                    :placeholder="form.smtp.password_set ? t('invoicing.email_smtp_password_unchanged') : ''"
                    autocomplete="new-password"
                  />
                </div>
                <div>
                  <label class="invoicing-sf-label">{{ t('invoicing.email_smtp_host') }}</label>
                  <input
                    v-model="form.smtp.host"
                    type="text"
                    class="invoicing-sf-input"
                    :placeholder="t('invoicing.email_smtp_host_placeholder')"
                  />
                </div>
                <div>
                  <label class="invoicing-sf-label">{{ t('invoicing.email_smtp_port') }}</label>
                  <input
                    v-model.number="form.smtp.port"
                    type="number"
                    class="invoicing-sf-input"
                    :placeholder="t('invoicing.email_smtp_port_placeholder')"
                  />
                </div>
                <div>
                  <label class="invoicing-sf-label">{{ t('invoicing.email_smtp_from_name') }}</label>
                  <input v-model="form.smtp.from_name" type="text" class="invoicing-sf-input" />
                </div>
                <div>
                  <label class="invoicing-sf-label">{{ t('invoicing.email_smtp_encryption') }}</label>
                  <select v-model="form.smtp.encryption" class="invoicing-sf-input">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none">{{ t('invoicing.email_smtp_encryption_none') }}</option>
                  </select>
                </div>
              </div>
              <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="form.smtp.use_smtp_email_as_from" type="checkbox" class="rounded border-gray-300" />
                {{ t('invoicing.email_smtp_use_login_as_from') }}
              </label>
              <div class="flex flex-wrap gap-2 pt-2">
                <button
                  type="button"
                  class="invoicing-btn-secondary"
                  :disabled="testingSmtp"
                  @click="testSmtp"
                >
                  {{ t('invoicing.email_smtp_test') }}
                </button>
              </div>
              <p v-if="smtpTestMessage" class="text-sm" :class="smtpTestOk ? 'text-green-700' : 'text-red-600'">
                {{ smtpTestMessage }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Šablóny emailov -->
      <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ t('invoicing.email_templates_title') }}</h2>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 border border-gray-200 rounded-lg overflow-hidden">
          <aside class="lg:col-span-3 bg-gray-50 border-b lg:border-b-0 lg:border-r border-gray-200 max-h-[480px] overflow-y-auto">
            <button
              v-for="key in EMAIL_TEMPLATE_KEYS"
              :key="key"
              type="button"
              class="w-full text-left px-4 py-2.5 text-sm border-b border-gray-100 last:border-0 transition-colors"
              :class="
                selectedTemplateKey === key
                  ? 'bg-white text-indigo-700 font-medium'
                  : 'text-gray-700 hover:bg-gray-100'
              "
              @click="selectedTemplateKey = key"
            >
              {{ t(EMAIL_TEMPLATE_LABEL_KEYS[key]) }}
            </button>
          </aside>

          <div class="lg:col-span-9 p-4 space-y-4">
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.email_template_subject') }}</label>
              <input v-model="activeTemplate.subject" type="text" class="invoicing-sf-input" />
            </div>
            <div>
              <label class="invoicing-sf-label">{{ t('invoicing.email_template_body') }}</label>
              <textarea
                v-model="activeTemplate.body"
                rows="12"
                class="invoicing-sf-input font-mono text-sm"
              />
            </div>
            <p class="text-xs text-gray-500">{{ t('invoicing.email_template_placeholders_hint') }}</p>
            <div class="text-xs text-gray-600 leading-relaxed space-y-0.5 max-h-40 overflow-y-auto">
              <p v-for="ph in EMAIL_PLACEHOLDERS" :key="ph.token">
                <code class="text-indigo-700">{{ ph.token }}</code> - {{ t(ph.key) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="pt-4 border-t border-gray-100 text-center">
        <button type="submit" class="invoicing-btn-primary px-10" :disabled="saving">
          {{ t('invoicing.save_my_details') }}
        </button>
        <p class="text-xs text-gray-500 mt-2">{{ t('invoicing.app_save_note') }}</p>
        <p v-if="saveError" class="text-sm text-red-600 mt-2">{{ saveError }}</p>
      </div>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
  EMAIL_PLACEHOLDERS,
  EMAIL_TEMPLATE_KEYS,
  EMAIL_TEMPLATE_LABEL_KEYS,
  emailSettingsFromCompany,
  type CompanyEmailSettingsState,
  type EmailTemplateKey,
} from '../../composables/useCompanyEmailSettings';
import { buildDefaultEmailTemplates } from '../../composables/companyEmailTemplateDefaults';
import { asCompanyId } from '../../composables/useInvoicingCompany';
import { allCompaniesDetailQuery, useInvoicingEvolu } from '../../evolu/client';
import { evoluCompanyToApi, type EvoluCompanyRow } from '../../evolu/companyMap';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import { updateLocalEmailSettings } from '../../evolu/companySettingsCrud';
import api from '../../services/api';
import { useStoresStore } from '../../store/stores';
import CompanyAppTabsNav from './CompanyAppTabsNav.vue';

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

const deliveryMethods = [
  { id: 'system' as const, labelKey: 'invoicing.email_delivery_system' },
  { id: 'smtp' as const, labelKey: 'invoicing.email_delivery_smtp' },
  { id: 'gmail' as const, labelKey: 'invoicing.email_delivery_gmail' },
  { id: 'office' as const, labelKey: 'invoicing.email_delivery_office' },
];

const saving = ref(false);
const saveError = ref('');
const testingSmtp = ref(false);
const smtpTestMessage = ref('');
const smtpTestOk = ref(false);
const selectedTemplateKey = ref<EmailTemplateKey>('invoice');
const form = reactive<CompanyEmailSettingsState>(emailSettingsFromCompany(null, locale.value));

watch(
  selectedTemplateKey,
  (key) => {
    if (!form.templates[key]) {
      form.templates[key] = buildDefaultEmailTemplates(locale.value)[key] ?? { subject: '', body: '' };
    }
  },
  { immediate: true }
);

const activeTemplate = computed({
  get() {
    return form.templates[selectedTemplateKey.value] ?? { subject: '', body: '' };
  },
  set(v) {
    form.templates[selectedTemplateKey.value] = v;
  },
});

watch(
  () => props.company,
  (c) => Object.assign(form, emailSettingsFromCompany(c, locale.value)),
  { immediate: true, deep: true }
);

function onDeliveryMethodChange(id: string) {
  if (id === 'gmail' || id === 'office') {
    saveError.value = '';
  }
}

function buildPayload() {
  const payload: Record<string, unknown> = {
    delivery_method: form.delivery_method,
    templates: form.templates,
  };
  if (form.delivery_method === 'smtp') {
    payload.smtp = {
      username: form.smtp.username || null,
      host: form.smtp.host || null,
      port: form.smtp.port || null,
      from_name: form.smtp.from_name || null,
      encryption: form.smtp.encryption,
      use_smtp_email_as_from: form.smtp.use_smtp_email_as_from,
    };
    if (form.smtp.password) {
      (payload.smtp as Record<string, unknown>).password = form.smtp.password;
    }
  }
  return payload;
}

async function saveAll() {
  if (form.delivery_method === 'gmail' || form.delivery_method === 'office') {
    saveError.value = t('invoicing.email_oauth_coming_soon');
    return;
  }
  saving.value = true;
  saveError.value = '';
  try {
    const payload = buildPayload();

    if (localFirst && evolu) {
      const result = updateLocalEmailSettings(evolu, asCompanyId(props.companyId), payload);
      if (!result.ok) {
        saveError.value = t('invoicing.company_save_validation_error');
        return;
      }
      form.smtp.password = '';
      const row = evolu.getQueryRows(allCompaniesDetailQuery).find((c) => c.id === props.companyId);
      if (row) {
        const updated = evoluCompanyToApi(row as EvoluCompanyRow, (storeId) => {
          const store = storesStore.stores.find((s) => s.id === storeId);
          return store
            ? { id: store.id, name: store.name, default_currency: store.default_currency }
            : undefined;
        });
        emit('updated', updated);
        if (updated.email_settings?.smtp) {
          form.smtp.password_set = updated.email_settings.smtp.password_set;
        }
      }
      return;
    }

    const res = await api.patch(`/invoicing/companies/${props.companyId}/email-settings`, payload);
    emit('updated', res.data.data);
    form.smtp.password = '';
    if (res.data.data.email_settings?.smtp) {
      form.smtp.password_set = res.data.data.email_settings.smtp.password_set;
    }
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    saving.value = false;
  }
}

async function testSmtp() {
  if (localFirst) {
    smtpTestOk.value = false;
    smtpTestMessage.value = t('invoicing.local_first_smtp_test_unavailable');
    return;
  }
  testingSmtp.value = true;
  smtpTestMessage.value = '';
  try {
    if (form.smtp.password || form.delivery_method === 'smtp') {
      await api.patch(`/invoicing/companies/${props.companyId}/email-settings`, buildPayload());
    }
    const res = await api.post(`/invoicing/companies/${props.companyId}/email-settings/test-smtp`, {
      to: form.smtp.username || props.company?.issuer_email,
    });
    smtpTestOk.value = true;
    smtpTestMessage.value = res.data.message ?? t('invoicing.email_smtp_test_ok');
  } catch (e: any) {
    smtpTestOk.value = false;
    smtpTestMessage.value = e?.response?.data?.message ?? t('common.error_generic');
  } finally {
    testingSmtp.value = false;
  }
}
</script>
