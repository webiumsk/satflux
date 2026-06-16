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
        :disabled="isRelaySyncing"
        :title="isRelaySyncing ? t('invoicing.relay_sync_wait_hint') : undefined"
        @click="onAddCompany"
      >
        {{ t('invoicing.add_company') }}
      </button>
      <p v-if="canUse && isRelaySyncing" class="text-xs text-amber-800 max-w-xs text-right">
        {{ t('invoicing.relay_sync_wait_hint') }}
      </p>
      <p v-else-if="canUse && !canCreateCompany && companyLimitMax" class="text-xs text-amber-800 max-w-xs text-right">
        {{ t('invoicing.company_limit_reached', { max: companyLimitMax }) }}
      </p>
    </template>

    <div v-if="localFirst && isRelaySyncing" class="invoicing-alert-warn mb-4">
      <p class="text-sm font-medium">{{ t('invoicing.relay_sync_loading') }}</p>
      <p class="text-sm mt-2 opacity-90">{{ t('invoicing.relay_sync_wait_detail') }}</p>
    </div>

    <div v-else-if="localFirst && !loading && companyList.length > 0" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 mb-4">
      <p class="text-sm text-emerald-900">{{ t('invoicing.relay_sync_ready') }}</p>
    </div>

    <div v-if="localFirst && duplicateCompanyNames.length" class="invoicing-alert-warn mb-4">
      <p class="text-sm font-medium">{{ t('invoicing.duplicate_companies_title') }}</p>
      <p class="text-sm mt-2 opacity-90">{{ t('invoicing.duplicate_companies_detail') }}</p>
      <ul class="text-sm mt-2 list-disc list-inside opacity-90">
        <li v-for="name in duplicateCompanyNames" :key="name">{{ name }}</li>
      </ul>
    </div>

    <div v-if="localFirst && showServerMigration" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-4 mb-4">
      <p class="text-sm font-medium text-amber-950">{{ t('invoicing.server_migration_title') }}</p>
      <p class="text-sm text-amber-900 mt-2">{{ t('invoicing.server_migration_detail') }}</p>
      <p v-if="migrationStatus" class="text-xs text-amber-800 mt-2">
        {{ t('invoicing.server_migration_counts', {
          companies: migrationStatus.companies_count,
          documents: migrationStatus.documents_count,
          contacts: migrationStatus.contacts_count,
          expenses: migrationStatus.expenses_count,
        }) }}
      </p>
      <button
        type="button"
        class="invoicing-btn-primary mt-3"
        :disabled="migrationImporting || isRelaySyncing"
        @click="runServerMigration"
      >
        {{ migrationImporting ? t('invoicing.server_migration_importing') : t('invoicing.server_migration_import') }}
      </button>
    </div>

    <p v-if="localFirst && !showServerMigration" class="text-sm text-gray-600 mb-4">
      {{ t('invoicing.local_first_data_notice') }}
      <router-link to="/legal/privacy" class="invoicing-link">{{ t('legal.nav.privacy') }}</router-link>.
    </p>

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
      <p v-if="localFirst && !showServerMigration" class="text-sm text-gray-500 mt-3 max-w-md mx-auto">
        {{ isRelaySyncing ? t('invoicing.relay_sync_empty_hint') : t('invoicing.no_companies_restore_hint') }}
      </p>
      <button
        v-if="canCreateCompany && !showServerMigration"
        type="button"
        class="invoicing-btn-primary mt-4"
        :disabled="isRelaySyncing"
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
import { computed, onMounted, ref, unref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useBusinessInvoicing } from '../../composables/useBusinessInvoicing';
import { useInvoicingCompanies } from '../../composables/useInvoicingCompanies';
import type { InvoicingCompanyListItem } from '../../composables/useInvoicingCompanies';
import { useInvoicingRelaySync } from '@/composables/useInvoicingRelaySync';
import { useLocalStoreSanitizer } from '@/composables/useLocalStoreSanitizer';
import { useInvoicingEvolu } from '@/evolu/client';
import { isEvoluRelaySyncPending } from '@/evolu/relaySyncWait';
import {
  fetchServerMigrationStatus,
  importServerInvoicingToEvolu,
  isServerMigrationCompleted,
  serverMigrationErrorMessage,
  type ServerMigrationStatus,
} from '@/evolu/serverMigration';
import { findDuplicateCompanyGroups } from '@/evolu/duplicateCompanies';
import { useAuthStore } from '../../store/auth';
import { useFlashStore } from '@/store/flash';
import UpgradeModal from '../../components/stores/UpgradeModal.vue';

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();
const flashStore = useFlashStore();
const { canUse } = useBusinessInvoicing();
const { isRelaySyncing, localFirst } = useInvoicingRelaySync();
const evolu = useInvoicingEvolu();
const { ensureStoresLoaded } = useLocalStoreSanitizer();

const {
  companies,
  loading,
  forbidden,
  refresh,
} = useInvoicingCompanies();

const showUpgrade = ref(false);
const migrationStatus = ref<ServerMigrationStatus | null>(null);
const migrationImporting = ref(false);

const relaySyncLoading = computed(() => localFirst && isEvoluRelaySyncPending() && loading.value);

watch(forbidden, (isForbidden) => {
  if (isForbidden) showUpgrade.value = true;
});

const companyList = computed(() => unref(companies));

const showServerMigration = computed(() => {
  if (!localFirst || loading.value || isRelaySyncing.value) return false;
  if (companyList.value.length > 0) return false;
  if (isServerMigrationCompleted()) return false;
  return migrationStatus.value?.available === true;
});

async function loadMigrationStatus(): Promise<void> {
  if (!localFirst || isServerMigrationCompleted()) return;
  try {
    migrationStatus.value = await fetchServerMigrationStatus();
  } catch {
    migrationStatus.value = null;
  }
}

async function runServerMigration(): Promise<void> {
  if (migrationImporting.value) return;
  migrationImporting.value = true;
  try {
    const validStoreIds = await ensureStoresLoaded();
    const result = await importServerInvoicingToEvolu(evolu, validStoreIds);
    const companiesCount = result.counts.company ?? migrationStatus.value?.companies_count ?? 0;
    flashStore.success(t('invoicing.server_migration_success', { companies: companiesCount }));
    if (result.warnings.length > 0) {
      flashStore.warning(t('invoicing.server_migration_warnings'));
    }
    migrationStatus.value = null;
    await refresh();
  } catch (error) {
    flashStore.error(serverMigrationErrorMessage(error, t));
  } finally {
    migrationImporting.value = false;
  }
}

onMounted(() => {
  void loadMigrationStatus();
});

watch(companyList, (list) => {
  if (localFirst && list.length === 0 && !isServerMigrationCompleted()) {
    void loadMigrationStatus();
  }
});

const duplicateCompanyNames = computed(() => {
  if (!localFirst) return [];
  const groups = findDuplicateCompanyGroups(companyList.value);
  return groups.map((group) => {
    const row = group[0] as InvoicingCompanyListItem | undefined;
    return row?.trade_name || row?.legal_name || '';
  }).filter(Boolean);
});

const companyLimitMax = computed(() => {
  const plan = authStore.user?.plan;
  if (!plan || plan.companies_unlimited) return null;
  return typeof plan.max_companies === 'number' ? plan.max_companies : null;
});

const canCreateCompany = computed(() => {
  if (!canUse.value) return false;
  if (isRelaySyncing.value) return false;
  const max = companyLimitMax.value;
  if (max === null) return true;
  return companyList.value.length < max;
});

function openCompany(c: { id: string }): void {
  router.push({ name: 'invoicing-invoices', params: { companyId: c.id } });
}

function onAddCompany(): void {
  if (isRelaySyncing.value) return;
  router.push({ name: 'invoicing-company-new' });
}
</script>
