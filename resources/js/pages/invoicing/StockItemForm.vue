<template>
  <InvoicingPageShell>
    <template #header>
      <InvoicingAppHeader :show-filter-bar="false" />
    </template>
    <template #toolbar>
      <RouterLink :to="stockListTo()" class="invoicing-back mb-0">
        ← {{ t("invoicing.stock_title") }}
      </RouterLink>
    </template>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-2">
      <h1 class="invoicing-title">
        {{
          isNew
            ? t("invoicing.stock_new_item")
            : form.name || t("invoicing.stock_edit_item")
        }}
      </h1>
      <div
        v-if="!isNew && neighborIds.length > 1"
        class="flex items-center gap-2 text-sm text-gray-600"
      >
        <button
          type="button"
          class="invoicing-btn-secondary py-1 px-2"
          :disabled="neighborIndex <= 0"
          @click="goNeighbor(-1)"
        >
          ‹
        </button>
        <span>{{ neighborIndex + 1 }} / {{ neighborIds.length }}</span>
        <button
          type="button"
          class="invoicing-btn-secondary py-1 px-2"
          :disabled="neighborIndex >= neighborIds.length - 1"
          @click="goNeighbor(1)"
        >
          ›
        </button>
      </div>
    </div>

    <form class="grid lg:grid-cols-[1fr_280px] gap-6" @submit.prevent="save">
      <div class="invoicing-card-pad space-y-4">
        <div>
          <label class="invoicing-sf-label">
            {{ t("invoicing.stock_field_name") }}
            <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            class="invoicing-sf-input"
            required
          />
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="invoicing-sf-label">{{
              t("invoicing.stock_col_sku")
            }}</label>
            <input v-model="form.sku" type="text" class="invoicing-sf-input" />
          </div>
          <div>
            <label class="invoicing-sf-label">{{
              t("invoicing.stock_field_unit")
            }}</label>
            <input v-model="form.unit" type="text" class="invoicing-sf-input" />
          </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input
            v-model="form.track_inventory"
            type="checkbox"
            class="rounded border-gray-300"
          />
          {{ t("invoicing.stock_field_track_inventory") }}
        </label>

        <div v-if="form.track_inventory" class="space-y-2">
          <div class="flex items-center justify-between gap-2">
            <label class="invoicing-sf-label mb-0">{{
              t("invoicing.stock_balances_title")
            }}</label>
            <button
              v-if="!isNew && form.balances.length > 1"
              type="button"
              class="text-sm text-indigo-600 hover:underline"
              @click="showTransferModal = true"
            >
              {{ t("invoicing.stock_transfer_title") }}
            </button>
          </div>
          <div class="rounded border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                  <th class="text-left px-3 py-2">
                    {{ t("invoicing.warehouse_col_name") }}
                  </th>
                  <th class="text-right px-3 py-2 w-32">
                    {{ t("invoicing.stock_col_on_hand") }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in form.balances"
                  :key="row.warehouse_id"
                  class="border-t border-gray-100"
                >
                  <td class="px-3 py-2">{{ row.warehouse_name }}</td>
                  <td class="px-3 py-2">
                    <input
                      v-model.number="row.quantity_on_hand"
                      type="number"
                      step="any"
                      min="0"
                      class="invoicing-sf-input-table text-right w-full"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div>
          <label class="invoicing-sf-label">{{
            t("invoicing.stock_field_description")
          }}</label>
          <textarea
            v-model="form.description"
            rows="4"
            class="invoicing-sf-input"
          />
        </div>

        <div>
          <label class="invoicing-sf-label">{{
            t("invoicing.stock_field_internal_note")
          }}</label>
          <textarea
            v-model="form.internal_note"
            rows="3"
            class="invoicing-sf-input"
          />
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input
            v-model="form.exclude_from_suggester"
            type="checkbox"
            class="rounded border-gray-300"
          />
          {{ t("invoicing.stock_field_exclude_suggester") }}
        </label>
      </div>

      <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-4">
          <h3 class="font-semibold text-gray-800">
            {{ t("invoicing.stock_purchase_price") }}
          </h3>
          <div>
            <label class="invoicing-sf-label">{{
              t("invoicing.col_unit_price")
            }}</label>
            <input
              v-model.number="form.purchase_unit_price"
              type="number"
              min="0"
              step="0.01"
              class="invoicing-sf-input"
            />
          </div>
          <div>
            <label class="invoicing-sf-label">{{
              t("invoicing.stock_field_currency")
            }}</label>
            <select v-model="form.purchase_currency" class="invoicing-sf-input">
              <option v-for="c in currencyOptions" :key="c" :value="c">
                {{ c }}
              </option>
            </select>
          </div>

          <h3 class="font-semibold text-gray-800 pt-2 border-t border-gray-200">
            {{ t("invoicing.stock_sale_price") }}
          </h3>
          <div>
            <label class="invoicing-sf-label">
              {{ t("invoicing.col_unit_price") }}
              <span class="text-red-500">*</span>
            </label>
            <input
              v-model.number="form.sale_unit_price"
              type="number"
              min="0"
              step="0.01"
              class="invoicing-sf-input"
            />
          </div>
          <p class="text-xs text-gray-500">
            {{
              t("invoicing.stock_sale_currency_hint", {
                currency: saleCurrency,
              })
            }}
          </p>
        </div>

        <div class="flex justify-center">
          <button
            type="submit"
            class="invoicing-btn-primary px-8"
            :disabled="saving"
          >
            {{ t("invoicing.stock_save_item") }}
          </button>
        </div>
        <p v-if="error" class="text-sm text-red-600 text-center">{{ error }}</p>
      </div>
    </form>

    <section
      v-if="!isNew && movements.length"
      class="mt-8 invoicing-card overflow-hidden"
    >
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
        <h2 class="font-semibold text-gray-800">
          {{ t("invoicing.stock_history_title") }}
        </h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[700px]">
          <thead
            class="text-xs uppercase text-gray-500 bg-white border-b border-gray-100"
          >
            <tr>
              <th class="text-left px-3 py-2">
                {{ t("invoicing.stock_history_date") }}
              </th>
              <th class="text-left px-3 py-2">
                {{ t("invoicing.warehouse_col_name") }}
              </th>
              <th class="text-right px-3 py-2">
                {{ t("invoicing.stock_history_quantity") }}
              </th>
              <th class="text-right px-3 py-2">
                {{ t("invoicing.stock_col_purchase") }}
              </th>
              <th class="text-right px-3 py-2">
                {{ t("invoicing.stock_col_sale") }}
              </th>
              <th class="text-left px-3 py-2">
                {{ t("invoicing.stock_history_note") }}
              </th>
              <th class="text-left px-3 py-2">
                {{ t("invoicing.stock_history_document") }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="m in movements"
              :key="m.id"
              class="border-t border-gray-100"
            >
              <td class="px-3 py-2 whitespace-nowrap">
                {{ formatDate(m.created_at) }}
              </td>
              <td class="px-3 py-2">{{ m.warehouse_name || "-" }}</td>
              <td class="px-3 py-2 text-right">{{ m.quantity_after }}</td>
              <td class="px-3 py-2 text-right">
                {{ formatStockPrice(m.purchase_unit_price) }}
              </td>
              <td class="px-3 py-2 text-right">
                {{ formatStockPrice(m.sale_unit_price) }}
              </td>
              <td class="px-3 py-2 text-gray-600">{{ m.note || "-" }}</td>
              <td class="px-3 py-2">{{ m.document_number || "-" }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <StockTransferModal
      :open="showTransferModal"
      :company-id="companyId"
      :stock-item-id="itemId || ''"
      :warehouses="warehouses"
      @close="showTransferModal = false"
      @transferred="onTransferred"
    />
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import InvoicingAppHeader from "../../components/invoicing/InvoicingAppHeader.vue";
import InvoicingPageShell from "../../components/invoicing/InvoicingPageShell.vue";
import StockTransferModal from "../../components/invoicing/StockTransferModal.vue";
import type { WarehouseRow } from "../../composables/useCompanyWarehouse";
import {
  emptyStockItemForm,
  formatStockPrice,
  formToStockPayload,
  stockItemToForm,
  useStockItemPage,
  useStockRoutes,
  type StockItemMovementRow,
} from "../../composables/useCompanyStockItem";
import { useInvoicingLayout } from "../../composables/useInvoicingLayout";
import { useInvoicingCompanySummary } from "../../composables/useInvoicingCompanySummary";
import { useInvoicingWarehouses } from "../../composables/useInvoicingWarehouses";
import { useLocalStockItemDetail } from "../../composables/useInvoicingStockItems";
import { isInvoicingLocalFirst } from "../../evolu/flags";
import { saveLocalStockItem } from "../../evolu/stockCrud";
import type { CompanyId, StockItemId } from "../../evolu/schema";
import type {
  EvoluStockBalanceRow,
  EvoluStockItemRow,
} from "../../evolu/stockMap";
import type { StockItemSavePayload } from "../../evolu/stockMap";
import { invoicingApi } from "../../services/api";
import { useInvoicingSaveFeedback } from "../../composables/useInvoicingSaveFeedback";

const { t, locale } = useI18n();
const { notifySaved } = useInvoicingSaveFeedback();
const router = useRouter();
const localFirst = isInvoicingLocalFirst();
const { companyId, itemId, isNew } = useStockItemPage();
const { rememberCompany } = useInvoicingLayout();
const { defaultCurrency } = useInvoicingCompanySummary();
const invoicingWarehouses = useInvoicingWarehouses(companyId);
const localStockDetail = localFirst ? useLocalStockItemDetail(companyId) : null;
const { stockListTo, stockEditTo } = useStockRoutes(companyId);

const form = reactive(emptyStockItemForm());
const saving = ref(false);
const error = ref("");
const saleCurrency = ref("EUR");
const neighborIds = ref<string[]>([]);
const movements = ref<StockItemMovementRow[]>([]);
const warehouses = ref<WarehouseRow[]>([]);
const showTransferModal = ref(false);
const currencyOptions = ["EUR", "CZK", "USD", "GBP", "PLN", "HUF"];

const neighborIndex = computed(() => {
  if (!itemId.value) return 0;
  const idx = neighborIds.value.indexOf(itemId.value);
  return idx >= 0 ? idx : 0;
});

function formatDate(iso?: string) {
  if (!iso) return "-";
  return new Date(iso).toLocaleString(locale.value);
}

async function loadWarehouses() {
  if (localFirst) {
    await invoicingWarehouses.refresh(true);
    warehouses.value = invoicingWarehouses.warehouses.value.filter(
      (w) => w.is_active,
    );
    return;
  }
  warehouses.value = (await invoicingApi.warehouses.list<WarehouseRow>(companyId.value)).filter(
    (w) => w.is_active,
  );
}

function initBalancesForNew() {
  form.balances = warehouses.value.map((w) => ({
    warehouse_id: w.id,
    warehouse_name: w.name,
    quantity_on_hand: 0,
  }));
}

async function loadCompany() {
  if (localFirst) {
    saleCurrency.value = defaultCurrency.value || "EUR";
    Object.assign(form, emptyStockItemForm(saleCurrency.value));
    await loadWarehouses();
    if (isNew.value) initBalancesForNew();
    return;
  }
  const summary = await invoicingApi.companies.summary(companyId.value);
  saleCurrency.value = summary.default_currency || "EUR";
  Object.assign(form, emptyStockItemForm(saleCurrency.value));
  await loadWarehouses();
  if (isNew.value) initBalancesForNew();
}

async function loadItem() {
  if (isNew.value || !itemId.value) return;
  if (localFirst && localStockDetail) {
    await localStockDetail.refreshAll();
    const data = localStockDetail.itemApi(itemId.value);
    if (!data) return;
    Object.assign(
      form,
      stockItemToForm(data, saleCurrency.value, warehouses.value),
    );
    neighborIds.value = data.neighbor_ids ?? [];
    movements.value = data.movements ?? [];
    return;
  }
  const data = await invoicingApi.stockItems.get<Record<string, any>>(companyId.value, itemId.value!);
  Object.assign(
    form,
    stockItemToForm(data, saleCurrency.value, warehouses.value),
  );
  neighborIds.value = data.neighbor_ids ?? [];
  movements.value = data.movements ?? [];
}

function toLocalStockPayload(): StockItemSavePayload {
  const raw = formToStockPayload(form);
  return {
    name: String(raw.name),
    sku: (raw.sku as string | null) ?? null,
    description: (raw.description as string | null) ?? null,
    unit: String(raw.unit || "ks"),
    track_inventory: Boolean(raw.track_inventory),
    purchase_unit_price: raw.purchase_unit_price as number | null,
    purchase_currency: (raw.purchase_currency as string | null) ?? null,
    sale_unit_price: raw.sale_unit_price as number | null,
    internal_note: (raw.internal_note as string | null) ?? null,
    exclude_from_suggester: Boolean(raw.exclude_from_suggester),
    balances: (raw.balances as StockItemSavePayload["balances"]) ?? [],
  };
}

async function onTransferred() {
  await loadItem();
}

async function save() {
  saving.value = true;
  error.value = "";
  try {
    if (localFirst && localStockDetail?.evolu) {
      await localStockDetail.refreshAll();
      const result = saveLocalStockItem(
        localStockDetail.evolu,
        companyId.value as CompanyId,
        toLocalStockPayload(),
        {
          itemId: isNew.value ? undefined : (itemId.value as StockItemId),
          existingItems: localStockDetail.itemRows.value as EvoluStockItemRow[],
          existingBalances: localStockDetail.balanceRows
            .value as EvoluStockBalanceRow[],
        },
      );
      if (!result.ok) {
        error.value =
          result.error === "duplicate_sku"
            ? t("invoicing.stock_sku_duplicate")
            : t("errors.generic");
        return;
      }
      if (isNew.value) {
        await router.push(stockEditTo(result.value.id));
      } else {
        await loadItem();
        notifySaved("invoicing.changes_saved");
      }
      return;
    }
    const payload = formToStockPayload(form);
    if (isNew.value) {
      const created = await invoicingApi.stockItems.create<{ id: string }>(companyId.value, payload);
      await router.push(stockEditTo(created.id));
    } else {
      await invoicingApi.stockItems.update(companyId.value, itemId.value!, payload);
      await loadItem();
      notifySaved("invoicing.changes_saved");
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || t("errors.generic");
    const errors = e?.response?.data?.errors;
    if (errors) {
      const first = Object.values(errors)[0];
      if (Array.isArray(first) && first[0]) error.value = String(first[0]);
    }
  } finally {
    saving.value = false;
  }
}

function goNeighbor(delta: number) {
  const next = neighborIds.value[neighborIndex.value + delta];
  if (next) {
    router.push(stockEditTo(next));
  }
}

onMounted(async () => {
  rememberCompany(companyId.value);
  await loadCompany();
  if (!isNew.value) {
    await loadItem();
  }
});

watch(itemId, async () => {
  await loadItem();
});
</script>
