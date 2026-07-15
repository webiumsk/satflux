<template>
  <nav class="invoicing-tabs invoicing-app-tabs-scroll mb-6" role="tablist">
    <RouterLink
      :to="{ name: 'invoicing-company-app', params: { companyId } }"
      role="tab"
      class="invoicing-tab"
      :class="{ 'invoicing-tab--active': activeTab === 'basic' }"
    >
      {{ t('invoicing.app_tab_basic') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'invoicing-company-app-emails', params: { companyId } }"
      role="tab"
      class="invoicing-tab"
      :class="{ 'invoicing-tab--active': activeTab === 'emails' }"
    >
      {{ t('invoicing.app_tab_emails') }}
    </RouterLink>
    <span class="invoicing-tab invoicing-tab--disabled" :title="t('invoicing.coming_soon')">
      {{ t('invoicing.app_tab_vat') }}
    </span>
    <RouterLink
      :to="{ name: 'invoicing-company-app-series', params: { companyId } }"
      role="tab"
      class="invoicing-tab"
      :class="{ 'invoicing-tab--active': activeTab === 'series' }"
    >
      {{ t('invoicing.app_tab_series') }}
    </RouterLink>
    <span class="invoicing-tab invoicing-tab--disabled" :title="t('invoicing.coming_soon')">
      {{ t('invoicing.app_tab_notes') }}
    </span>
    <span class="invoicing-tab invoicing-tab--disabled" :title="t('invoicing.coming_soon')">
      {{ t('invoicing.app_tab_pairing') }}
    </span>
    <RouterLink
      :to="{ name: 'invoicing-company-app-woocommerce', params: { companyId } }"
      role="tab"
      class="invoicing-tab"
      :class="{ 'invoicing-tab--active': activeTab === 'woocommerce' }"
    >
      {{ t('invoicing.app_tab_woocommerce') }}
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
  companyId: string;
  activeTab: 'basic' | 'emails' | 'series' | 'woocommerce';
}>();

const { t } = useI18n();
</script>
