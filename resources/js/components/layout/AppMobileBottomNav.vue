<template>
  <nav
    class="shrink-0 border-t border-gray-800 bg-gray-900 md:hidden"
    :aria-label="t('common.mobile_nav_aria')"
  >
    <div class="mx-auto flex max-w-lg items-center justify-around px-1 py-2">
      <RouterLink
        to="/stores"
        class="mobile-nav-item"
        :class="{ 'mobile-nav-item--active': isStoresActive }"
      >
        <svg class="mobile-nav-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
        <span class="mobile-nav-item__label">{{ t('header.stores') }}</span>
      </RouterLink>

      <RouterLink
        to="/invoicing"
        class="mobile-nav-item"
        :class="{ 'mobile-nav-item--active': isInvoicingActive }"
      >
        <svg class="mobile-nav-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span class="mobile-nav-item__label">{{ t('header.invoicing') }}</span>
      </RouterLink>

      <RouterLink
        to="/dashboard"
        class="mobile-nav-logo"
        :class="{ 'mobile-nav-logo--active': isDashboardActive }"
        :aria-label="t('common.mobile_nav_dashboard_aria')"
      >
        <div class="mobile-nav-logo__mark">
          <img src="/img/logo-satflux-white.svg" alt="" class="h-5 w-5 object-contain" />
        </div>
      </RouterLink>

      <RouterLink
        to="/account"
        class="mobile-nav-item"
        :class="{ 'mobile-nav-item--active': isProfileActive }"
      >
        <svg class="mobile-nav-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        <span class="mobile-nav-item__label">{{ t('common.mobile_nav_profile') }}</span>
      </RouterLink>

      <RouterLink
        to="/info"
        class="mobile-nav-item"
        :class="{ 'mobile-nav-item--active': isInfoActive }"
      >
        <svg class="mobile-nav-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="mobile-nav-item__label">{{ t('common.mobile_nav_info') }}</span>
      </RouterLink>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';

const { t } = useI18n();
const route = useRoute();

const isStoresActive = computed(
  () => route.path === '/stores' || /^\/stores\/[^/]+/.test(route.path),
);
const isInvoicingActive = computed(() => route.path.startsWith('/invoicing'));
const isDashboardActive = computed(
  () => route.path === '/dashboard' || route.name === 'home',
);
const isProfileActive = computed(() => route.path.startsWith('/account'));
const isInfoActive = computed(() => route.path.startsWith('/info'));
</script>

<style scoped>
.mobile-nav-item {
  @apply flex min-w-[3.5rem] flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-1.5 text-gray-500 transition-colors;
}

.mobile-nav-item__icon {
  @apply h-6 w-6 shrink-0;
}

.mobile-nav-item__label {
  @apply max-w-[4.5rem] truncate text-[10px] font-medium leading-tight;
}

.mobile-nav-item--active {
  @apply text-indigo-300;
}

.mobile-nav-logo {
  @apply flex flex-col items-center justify-center px-2;
}

.mobile-nav-logo__mark {
  @apply flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg shadow-indigo-600/20;
}

.mobile-nav-logo--active .mobile-nav-logo__mark {
  @apply ring-2 ring-indigo-300/80 ring-offset-2 ring-offset-gray-900;
}
</style>
