<template>
  <InvoicingPageShell>
    <template #header>
      <InvoicingAppHeader :show-filter-bar="false" />
    </template>
    <template #toolbar>
      <RouterLink :to="warehouseListTo()" class="invoicing-back mb-0">
        ← {{ t('invoicing.warehouses_title') }}
      </RouterLink>
    </template>

    <h1 class="invoicing-title mb-6">
      {{ isNew ? t('invoicing.warehouse_new') : form.name || t('invoicing.warehouse_edit') }}
    </h1>

    <form class="max-w-xl space-y-4 invoicing-card-pad" @submit.prevent="save">
      <div>
        <label class="invoicing-sf-label">
          {{ t('invoicing.warehouse_col_name') }}
          <span class="text-red-500">*</span>
        </label>
        <input v-model="form.name" type="text" class="invoicing-sf-input" required />
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.warehouse_col_type') }}</label>
        <select v-model="form.type" class="invoicing-sf-input">
          <option value="own">{{ t('invoicing.warehouse_type_own') }}</option>
          <option value="owned_external">{{ t('invoicing.warehouse_type_owned_external') }}</option>
          <option value="supplier_availability">{{ t('invoicing.warehouse_type_supplier_availability') }}</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">{{ typeHint }}</p>
      </div>

      <div v-if="form.type !== 'own'">
        <label class="invoicing-sf-label">{{ t('invoicing.warehouse_col_supplier') }}</label>
        <select v-model="form.company_contact_id" class="invoicing-sf-input">
          <option value="">{{ t('invoicing.warehouse_supplier_none') }}</option>
          <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.warehouse_field_city') }}</label>
          <input v-model="form.city" type="text" class="invoicing-sf-input" />
        </div>
        <div>
          <label class="invoicing-sf-label">{{ t('invoicing.warehouse_field_country') }}</label>
          <input v-model="form.country" type="text" maxlength="2" class="invoicing-sf-input uppercase" />
        </div>
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.warehouse_field_street') }}</label>
        <input v-model="form.street" type="text" class="invoicing-sf-input" />
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.warehouse_field_postal_code') }}</label>
        <input v-model="form.postal_code" type="text" class="invoicing-sf-input" />
      </div>

      <div>
        <label class="invoicing-sf-label">{{ t('invoicing.stock_field_internal_note') }}</label>
        <textarea v-model="form.notes" rows="3" class="invoicing-sf-input" />
      </div>

      <label class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300" />
        {{ t('invoicing.warehouse_set_default') }}
      </label>

      <label class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
        {{ t('invoicing.warehouse_active') }}
      </label>

      <div class="flex flex-wrap gap-3 pt-2">
        <button type="submit" class="invoicing-btn-primary" :disabled="saving">
          {{ t('common.save') }}
        </button>
        <button
          v-if="!isNew"
          type="button"
          class="invoicing-btn-secondary text-red-600"
          :disabled="saving"
          @click="remove"
        >
          {{ t('common.delete') }}
        </button>
      </div>
      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
    </form>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import {
  emptyWarehouseForm,
  formToWarehousePayload,
  useWarehousePage,
  useWarehouseRoutes,
  warehouseToForm,
} from '../../composables/useCompanyWarehouse';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import { useInvoicingWarehouses } from '../../composables/useInvoicingWarehouses';
import { useQuery } from '@evolu/vue';
import { allContactsQuery } from '../../evolu/client';
import { evoluContactToApi } from '../../evolu/contactMap';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import { deleteLocalWarehouse, localWarehouseApi, saveLocalWarehouse } from '../../evolu/warehouseCrud';
import type { CompanyId, WarehouseId } from '../../evolu/schema';
import { allCompanyStockBalancesQuery } from '../../evolu/client';
import api from '../../services/api';

const { t } = useI18n();
const router = useRouter();
const localFirst = isInvoicingLocalFirst();
const { companyId, warehouseId, isNew } = useWarehousePage();
const { rememberCompany } = useInvoicingLayout();
const { warehouseListTo } = useWarehouseRoutes(companyId);
const invoicingWarehouses = useInvoicingWarehouses(companyId);
const contactRows = localFirst ? useQuery(allContactsQuery) : null;

const form = reactive(emptyWarehouseForm());
const saving = ref(false);
const error = ref('');
const contacts = ref<{ id: string; name: string }[]>([]);

const typeHint = computed(() => {
  if (form.type === 'supplier_availability') {
    return t('invoicing.warehouse_type_supplier_availability_hint');
  }
  if (form.type === 'owned_external') {
    return t('invoicing.warehouse_type_owned_external_hint');
  }
  return t('invoicing.warehouse_type_own_hint');
});

async function loadContacts() {
  if (localFirst && contactRows) {
    contacts.value = contactRows.value
      .filter((c) => c.companyId === companyId.value)
      .map((c) => evoluContactToApi(c))
      .map((c) => ({ id: c.id, name: c.name }));
    return;
  }
  const res = await api.get(`/invoicing/companies/${companyId.value}/contacts`);
  contacts.value = (res.data.data ?? []).map((c: { id: string; name: string }) => ({
    id: c.id,
    name: c.name,
  }));
}

async function loadWarehouse() {
  if (isNew.value || !warehouseId.value) return;
  if (localFirst && invoicingWarehouses.evolu) {
    await invoicingWarehouses.refresh(false);
    const contactNames = new Map(contacts.value.map((c) => [c.id, c.name]));
    const row = localWarehouseApi(
      warehouseId.value as WarehouseId,
      invoicingWarehouses.warehouseRows.value,
      contactNames,
    );
    if (row) Object.assign(form, warehouseToForm(row));
    return;
  }
  const res = await api.get(`/invoicing/companies/${companyId.value}/warehouses/${warehouseId.value}`);
  Object.assign(form, warehouseToForm(res.data.data));
}

async function save() {
  saving.value = true;
  error.value = '';
  try {
    const payload = formToWarehousePayload(form);
    if (localFirst && invoicingWarehouses.evolu) {
      const result = saveLocalWarehouse(
        invoicingWarehouses.evolu,
        companyId.value as CompanyId,
        payload,
        {
          warehouseId: isNew.value ? undefined : (warehouseId.value as WarehouseId),
          existingRows: invoicingWarehouses.warehouseRows.value,
        },
      );
      if (!result.ok) {
        error.value = t('errors.generic');
        return;
      }
      await router.push(warehouseListTo());
      return;
    }
    if (isNew.value) {
      await api.post(`/invoicing/companies/${companyId.value}/warehouses`, payload);
    } else {
      await api.patch(`/invoicing/companies/${companyId.value}/warehouses/${warehouseId.value}`, payload);
    }
    await router.push(warehouseListTo());
  } catch (e: unknown) {
    error.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message || t('errors.generic');
  } finally {
    saving.value = false;
  }
}

async function remove() {
  if (!warehouseId.value || !confirm(t('invoicing.warehouse_delete_confirm'))) return;
  saving.value = true;
  error.value = '';
  try {
    if (localFirst && invoicingWarehouses.evolu) {
      await invoicingWarehouses.evolu.loadQuery(allCompanyStockBalancesQuery);
      const balanceRows = (await invoicingWarehouses.evolu.loadQuery(allCompanyStockBalancesQuery)) as Array<{
        companyWarehouseId: string;
        quantityOnHand: string | null;
      }>;
      const result = deleteLocalWarehouse(
        invoicingWarehouses.evolu,
        companyId.value as CompanyId,
        warehouseId.value as WarehouseId,
        {
          warehouseRows: invoicingWarehouses.warehouseRows.value,
          balanceRows,
        },
      );
      if (!result.ok) {
        error.value =
          result.error === 'has_stock'
            ? t('invoicing.warehouse_delete_has_stock')
            : t('errors.generic');
        return;
      }
      await router.push(warehouseListTo());
      return;
    }
    await api.delete(`/invoicing/companies/${companyId.value}/warehouses/${warehouseId.value}`);
    await router.push(warehouseListTo());
  } catch (e: unknown) {
    error.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message || t('errors.generic');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  rememberCompany(companyId.value);
  await loadContacts().catch(() => {});
  await loadWarehouse().catch(() => {});
});
</script>
