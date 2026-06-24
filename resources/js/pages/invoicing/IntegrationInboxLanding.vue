<template>
  <InvoicingPageShell :title="t('invoicing.integration_inbox_deeplink_title')">
    <div class="py-12 text-center text-sm text-gray-600">
      <p v-if="error" class="text-red-700">{{ error }}</p>
      <p v-else>{{ t('invoicing.integration_inbox_deeplink_loading') }}</p>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import InvoicingPageShell from '@/components/invoicing/InvoicingPageShell.vue';
import api from '@/services/api';
import { ensureEvoluBoundToAccountSeed } from '@/evolu/bootstrap';
import { isInvoicingLocalFirst } from '@/evolu/flags';

type DeepLinkPayload = {
  store_id: string;
  company_id: string | null;
  invoices_path: string;
};

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const error = ref('');

async function findEvoluCompanyIdForStore(satfluxStoreId: string): Promise<string | null> {
  await ensureEvoluBoundToAccountSeed();
  const mod = await import('@/evolu/client');
  const rows = await mod.evolu.loadQuery(mod.allCompaniesQuery);
  for (const row of rows) {
    const linked = typeof row.linkedStoreId === 'string' ? row.linkedStoreId.trim() : '';
    if (linked === satfluxStoreId) {
      return String(row.id);
    }
  }
  return null;
}

async function resolveTargetPath(data: DeepLinkPayload): Promise<string> {
  if (isInvoicingLocalFirst()) {
    const evoluCompanyId = await findEvoluCompanyIdForStore(data.store_id);
    if (evoluCompanyId) {
      return `/invoicing/companies/${evoluCompanyId}/invoices`;
    }
  }

  if (data.invoices_path && data.invoices_path !== '/invoicing') {
    return data.invoices_path;
  }

  return '/invoicing';
}

onMounted(async () => {
  const storeRef = typeof route.params.storeId === 'string' ? route.params.storeId : '';
  const companyRef = typeof route.params.companyId === 'string' ? route.params.companyId : '';

  try {
    const params = new URLSearchParams();
    if (storeRef) {
      params.set('store', storeRef);
    } else if (companyRef) {
      params.set('company', companyRef);
    }

    const { data: res } = await api.get<{ data: DeepLinkPayload }>(
      `/invoicing/integration-inbox/deeplink?${params.toString()}`,
    );
    const payload = res.data;
    const targetPath = await resolveTargetPath(payload);

    await router.replace({
      path: targetPath,
      query: { integration_inbox: '1' },
    });
  } catch (e: unknown) {
    const message =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? t('invoicing.integration_inbox_deeplink_failed');
    error.value = message;
  }
});
</script>
