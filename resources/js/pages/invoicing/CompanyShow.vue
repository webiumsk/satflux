<template>
  <InvoicingPageShell v-if="company" content-class="pb-8">
    <template #header>
      <InvoicingAppHeader
        :company-label="company.trade_name || company.legal_name"
        :show-filter-bar="false"
      />
    </template>

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
      v-else-if="settingsSection === 'app' && appTab === 'basic'"
      :company-id="companyId"
      :company="company"
      @updated="onCompanyUpdated"
    />

    <CompanySettingsForm
      v-else-if="settingsSection === 'profile'"
      :company-id="companyId"
      :company="company"
      :local-first="localFirst"
      @updated="onCompanyUpdated"
    />
  </InvoicingPageShell>

  <InvoicingPageShell v-else-if="loading" content-class="pb-8">
    <div class="invoicing-muted py-8">{{ t('common.loading') }}</div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import CompanyAppSettingsForm from '../../components/invoicing/CompanyAppSettingsForm.vue';
import CompanyEmailSettingsForm from '../../components/invoicing/CompanyEmailSettingsForm.vue';
import CompanyNumberSeriesPanel from '../../components/invoicing/CompanyNumberSeriesPanel.vue';
import CompanySettingsForm from '../../components/invoicing/CompanySettingsForm.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useInvoicingCompany } from '../../composables/useInvoicingCompany';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import type { InvoicingCompanyRecord } from '../../evolu/companyMap';

const { t } = useI18n();
const route = useRoute();
const { companyId, rememberCompany } = useInvoicingLayout();
const { localFirst, company, loading, applyCompanyPatch } = useInvoicingCompany(companyId);

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

function onCompanyUpdated(c: InvoicingCompanyRecord) {
  applyCompanyPatch(c);
}

onMounted(() => {
  rememberCompany(companyId.value);
});
</script>
