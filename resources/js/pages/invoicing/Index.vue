<template>
  <InvoicingPageShell
    :title="t('invoicing.title')"
    :subtitle="t('invoicing.subtitle')"
  >
    <template #actions>
      <button
        v-if="canUse && canCreateCompany"
        type="button"
        class="invoicing-btn-primary"
        @click="onAddCompany"
      >
        {{ t('invoicing.add_company') }}
      </button>
      <p v-if="canUse && !canCreateCompany && companyLimitMax" class="text-xs text-amber-800 max-w-xs text-right">
        {{ t('invoicing.company_limit_reached', { max: companyLimitMax }) }}
      </p>
    </template>

    <div v-if="localFirst" class="invoicing-alert-warn mb-4">
      <p class="text-sm">{{ t('invoicing.local_first_notice') }}</p>
    </div>

    <div v-if="!canUse" class="invoicing-alert-warn">
      <p class="font-medium">{{ t('invoicing.pro_required') }}</p>
      <p class="text-sm mt-2 opacity-90">{{ t('invoicing.pro_required_detail') }}</p>
      <button type="button" class="invoicing-link mt-3" @click="showUpgrade = true">
        {{ t('invoicing.upgrade_cta') }}
      </button>
    </div>

    <div v-else-if="loading" class="invoicing-muted py-8">
      {{ relaySyncLoading ? t('invoicing.relay_sync_loading') : t('common.loading') }}
    </div>

    <div v-else-if="companyList.length === 0" class="invoicing-card-pad text-center">
      <p class="text-gray-700">{{ t('invoicing.no_companies') }}</p>
      <p v-if="localFirst" class="text-sm text-gray-500 mt-3 max-w-md mx-auto">
        {{ t('invoicing.no_companies_restore_hint') }}
      </p>
      <button
        v-if="canCreateCompany"
        type="button"
        class="invoicing-btn-primary mt-4"
        @click="onAddCompany"
      >
        {{ t('invoicing.create_first_company') }}
      </button>
    </div>

    <ul v-else class="space-y-3">
      <li v-for="c in companyList" :key="c.id">
        <button
          type="button"
          class="invoicing-list-item w-full text-left"
          @click="openCompany(c)"
        >
          <div>
            <p class="font-medium text-gray-900">{{ c.trade_name || c.legal_name }}</p>
            <p class="text-xs text-gray-500">{{ c.legal_name }}</p>
          </div>
          <span class="text-xs text-gray-500">{{ c.documents_count ?? 0 }} {{ t('invoicing.invoices_short') }}</span>
        </button>
      </li>
    </ul>

    <UpgradeModal :show="showUpgrade" @close="showUpgrade = false" />
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, ref, unref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useBusinessInvoicing } from '../../composables/useBusinessInvoicing';
import { useInvoicingCompanies } from '../../composables/useInvoicingCompanies';
import { isEvoluRelaySyncPending } from '@/evolu/relaySyncWait';
import { useAuthStore } from '../../store/auth';
import UpgradeModal from '../../components/stores/UpgradeModal.vue';

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();
const { canUse } = useBusinessInvoicing();

const {
  localFirst,
  companies,
  loading,
  forbidden,
} = useInvoicingCompanies();

const showUpgrade = ref(false);

const relaySyncLoading = computed(() => localFirst && isEvoluRelaySyncPending() && loading.value);

watch(forbidden, (isForbidden) => {
  if (isForbidden) showUpgrade.value = true;
});

const companyList = computed(() => unref(companies));

const companyLimitMax = computed(() => {
  const plan = authStore.user?.plan;
  if (!plan || plan.companies_unlimited) return null;
  return typeof plan.max_companies === 'number' ? plan.max_companies : null;
});

const canCreateCompany = computed(() => {
  if (!canUse.value) return false;
  const max = companyLimitMax.value;
  if (max === null) return true;
  return companyList.value.length < max;
});

function openCompany(c: { id: string }): void {
  router.push({ name: 'invoicing-invoices', params: { companyId: c.id } });
}

function onAddCompany(): void {
  router.push({ name: 'invoicing-company-new' });
}
</script>
