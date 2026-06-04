<template>
  <InvoicingPageShell v-if="company" content-class="pb-8">
    <template #header>
      <InvoicingAppHeader
        :company-label="company.trade_name || company.legal_name"
        :show-filter-bar="false"
      />
    </template>

    <CompanySettingsSectionNav :company-id="companyId" :section="settingsSection" class="mt-4" />

    <h1 class="invoicing-title mb-4 mt-4">
      {{
        settingsSection === 'app'
          ? appTab === 'emails'
            ? t('invoicing.email_settings_title')
            : appTab === 'series'
              ? t('invoicing.series_settings_title')
              : t('invoicing.app_settings_title')
          : t('invoicing.company_settings_title')
      }}
    </h1>

    <CompanyNumberSeriesPanel
      v-if="settingsSection === 'app' && appTab === 'series'"
      :company-id="companyId"
    />
    <CompanyEmailSettingsForm
      v-else-if="settingsSection === 'app' && appTab === 'emails'"
      :company-id="companyId"
      :company="company"
      @updated="onCompanyUpdated"
    />
    <CompanyAppSettingsForm
      v-else-if="settingsSection === 'app'"
      :company-id="companyId"
      :company="company"
      @updated="onCompanyUpdated"
    />

    <CompanySettingsForm
      v-else
      :company-id="companyId"
      :company="company"
      @updated="onCompanyUpdated"
    />
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import CompanyAppSettingsForm from '../../components/invoicing/CompanyAppSettingsForm.vue';
import CompanyEmailSettingsForm from '../../components/invoicing/CompanyEmailSettingsForm.vue';
import CompanyNumberSeriesPanel from '../../components/invoicing/CompanyNumberSeriesPanel.vue';
import CompanySettingsForm from '../../components/invoicing/CompanySettingsForm.vue';
import CompanySettingsSectionNav from '../../components/invoicing/CompanySettingsSectionNav.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import api from '../../services/api';
const { t } = useI18n();
const route = useRoute();
const { companyId, rememberCompany } = useInvoicingLayout();
const company = ref<Record<string, any> | null>(null);

const settingsSection = computed<'profile' | 'app' | 'subscription'>(() => {
  if (
    route.name === 'invoicing-company-app'
    || route.name === 'invoicing-company-app-emails'
    || route.name === 'invoicing-company-app-series'
  ) {
    return 'app';
  }
  return 'profile';
});

const appTab = computed<'basic' | 'emails' | 'series'>(() => {
  if (route.name === 'invoicing-company-app-emails') return 'emails';
  if (route.name === 'invoicing-company-app-series') return 'series';
  return 'basic';
});

async function load() {
  const res = await api.get(`/invoicing/companies/${companyId.value}`);
  company.value = res.data.data;
}

function onCompanyUpdated(c: Record<string, any>) {
  company.value = { ...company.value, ...c, stores: company.value?.stores ?? c.stores };
}

onMounted(async () => {
  rememberCompany(companyId.value);
  await load();
});
</script>
