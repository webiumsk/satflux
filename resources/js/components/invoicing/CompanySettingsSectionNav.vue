<template>
  <nav class="invoicing-settings-section-nav" aria-label="Company settings sections">
    <RouterLink
      :to="{ name: 'invoicing-company', params: { companyId } }"
      class="invoicing-settings-section-nav__item"
      :class="{ 'invoicing-settings-section-nav__item--active': section === 'profile' }"
    >
      {{ t('invoicing.settings_nav_profile') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'invoicing-company-app', params: { companyId } }"
      class="invoicing-settings-section-nav__item"
      :class="{ 'invoicing-settings-section-nav__item--active': section === 'app' }"
    >
      {{ t('invoicing.settings_nav_application') }}
    </RouterLink>
    <RouterLink
      :to="{ name: 'account' }"
      class="invoicing-settings-section-nav__item"
      :class="{ 'invoicing-settings-section-nav__item--active': section === 'subscription' }"
    >
      {{ t('invoicing.settings_nav_subscription') }}
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
  companyId: string;
  section: 'profile' | 'app' | 'subscription';
}>();

const { t } = useI18n();
</script>
