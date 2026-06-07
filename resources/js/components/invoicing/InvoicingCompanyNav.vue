<template>
  <nav class="invoicing-nav">
    <RouterLink
      :to="{ name: 'invoicing-invoices', params: { companyId } }"
      class="invoicing-nav-link"
      :class="{ 'invoicing-nav-link--active': isActive('invoicing-invoices') || isActive('invoicing-invoice-show') || isActive('invoicing-invoice-edit') || isActive('invoicing-invoice-new') }"
    >
      {{ t('invoicing.nav_invoices') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'invoicing-contacts', params: { companyId } }"
      class="invoicing-nav-link"
      :class="{
        'invoicing-nav-link--active':
          isActive('invoicing-contacts')
          || isActive('invoicing-contact-show')
          || isActive('invoicing-contact-edit')
          || isActive('invoicing-contact-new'),
      }"
    >
      {{ t('invoicing.nav_contacts') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'invoicing-company', params: { companyId } }"
      class="invoicing-nav-link"
      :class="{
        'invoicing-nav-link--active':
          isActive('invoicing-company')
          || isActive('invoicing-company-app')
          || isActive('invoicing-company-app-emails'),
      }"
    >
      {{ t('invoicing.nav_company') }}
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';

defineProps<{ companyId: string }>();

const { t } = useI18n();
const route = useRoute();

function isActive(name: string) {
  return route.name === name;
}
</script>
