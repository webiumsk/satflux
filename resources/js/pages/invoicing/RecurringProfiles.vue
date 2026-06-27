<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader
        :company-label="companyName"
        :show-filter-bar="false"
        show-mobile-filters
        :mobile-filter-active-count="activeFilter !== 'all' ? 1 : 0"
        @mobile-filter-apply="load"
        @mobile-filter-clear="
          activeFilter = 'all';
          load();
        "
      >
        <template #mobile-filters>
          <div class="invoicing-mobile-filter-stack">
            <button
              v-for="f in filters"
              :key="f.id"
              type="button"
              class="invoicing-filter"
              :class="
                activeFilter === f.id
                  ? 'invoicing-filter--active'
                  : 'invoicing-filter--idle'
              "
              @click="setFilter(f.id)"
            >
              {{ f.label }}
            </button>
          </div>
        </template>
        <template #mobile-actions>
          <RouterLink
            :to="{ name: 'invoicing-recurring-new', params: { companyId } }"
            class="invoicing-btn-primary w-full text-center"
          >
            + {{ t("invoicing.new_recurring") }}
          </RouterLink>
        </template>
        <template #mobile-primary-action>
          <RouterLink
            :to="{ name: 'invoicing-recurring-new', params: { companyId } }"
            class="invoicing-mobile-icon-btn"
            :title="t('invoicing.new_recurring')"
          >
            <InvoicingIcons name="plus" />
          </RouterLink>
        </template>
      </InvoicingAppHeader>
    </template>

    <template #subheader>
      <div
        class="invoicing-filter-bar bg-white border-b border-gray-200 hidden md:block sticky top-0 z-20"
      >
        <div
          :class="[
            INVOICING_CONTAINER_CLASS,
            'flex flex-wrap items-center gap-3 py-3',
          ]"
        >
          <div class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
            <button
              v-for="f in filters"
              :key="f.id"
              type="button"
              class="invoicing-filter"
              :class="
                activeFilter === f.id
                  ? 'invoicing-filter--active'
                  : 'invoicing-filter--idle'
              "
              @click="setFilter(f.id)"
            >
              {{ f.label }}
            </button>
          </div>
          <RouterLink
            :to="{ name: 'invoicing-recurring-new', params: { companyId } }"
            class="invoicing-btn-primary shrink-0"
          >
            + {{ t("invoicing.new_recurring") }}
          </RouterLink>
        </div>
      </div>
    </template>

    <div v-if="loading" class="invoicing-muted py-8">
      {{ t("common.loading") }}
    </div>

    <div
      v-else-if="profiles.length === 0"
      class="invoicing-card-pad text-center text-gray-600"
    >
      {{ t("invoicing.no_recurring") }}
    </div>

    <div v-else class="my-4 md:my-8 invoicing-card overflow-hidden">
      <div class="hidden md:block overflow-x-auto">
        <table class="invoice-table w-full min-w-[800px] text-sm text-left">
          <thead
            class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide border-b border-gray-200"
          >
            <tr>
              <th class="w-4 px-0 py-3" aria-hidden="true"></th>
              <th class="px-4 py-3">{{ t("invoicing.recurring_col_name") }}</th>
              <th class="px-4 py-3">{{ t("invoicing.col_customer") }}</th>
              <th class="px-4 py-3 text-right">
                {{ t("invoicing.col_amount") }}
              </th>
              <th class="px-4 py-3">
                {{ t("invoicing.recurring_col_interval") }}
              </th>
              <th class="px-4 py-3">{{ t("invoicing.recurring_col_next") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="p in profiles"
              :key="p.id"
              class="border-b border-gray-100 hover:bg-gray-50 group"
            >
              <td class="px-0 py-3 relative w-4">
                <span
                  class="status-corner"
                  :class="
                    p.is_active ? 'status-corner--paid' : 'status-corner--draft'
                  "
                ></span>
              </td>
              <td class="px-4 py-3">
                <RouterLink
                  :to="{
                    name: 'invoicing-recurring-edit',
                    params: { companyId, profileId: p.id },
                  }"
                  class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                >
                  {{ profileLabel(p) }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-indigo-600">
                {{ p.contact?.name || "-" }}
              </td>
              <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">
                {{ formatMoney(p.total) }} {{ p.currency }}
              </td>
              <td class="px-4 py-3 text-gray-600">
                {{ intervalLabel(p.recurrence_interval) }}
              </td>
              <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                {{ p.next_issue_date ? formatDate(p.next_issue_date) : "-" }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="md:hidden divide-y divide-gray-100">
        <InvoicingMobileCard
          v-for="p in profiles"
          :key="p.id"
          @open="
            $router.push({
              name: 'invoicing-recurring-edit',
              params: { companyId, profileId: p.id },
            })
          "
        >
          <div class="relative pl-3">
            <span
              class="status-corner absolute left-0 top-0"
              :class="
                p.is_active ? 'status-corner--paid' : 'status-corner--draft'
              "
            />
            <p class="font-semibold text-gray-900 truncate">
              {{ profileLabel(p) }}
            </p>
            <p class="text-sm text-gray-600 truncate">
              {{ p.contact?.name || "-" }}
            </p>
            <p class="text-xs text-gray-500 mt-1">
              {{ intervalLabel(p.recurrence_interval) }}
              · {{ p.next_issue_date ? formatDate(p.next_issue_date) : "-" }}
            </p>
          </div>
          <template #actions>
            <span class="text-sm font-semibold text-gray-900"
              >{{ formatMoney(p.total) }} {{ p.currency }}</span
            >
          </template>
        </InvoicingMobileCard>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-600 mt-4">{{ error }}</p>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import "../../styles/invoicing-theme.css";
import InvoicingAppHeader from "../../components/invoicing/InvoicingAppHeader.vue";
import InvoicingPageShell from "../../components/invoicing/InvoicingPageShell.vue";
import InvoicingMobileCard from "../../components/invoicing/InvoicingMobileCard.vue";
import InvoicingIcons from "../../components/invoicing/icons/InvoicingIcons.vue";
import api from "../../services/api";
import {
  INVOICING_CONTAINER_CLASS,
  useInvoicingLayout,
} from "../../composables/useInvoicingLayout";
import { useInvoicingCompanySummary } from "../../composables/useInvoicingCompanySummary";
import {
  useInvoicingRecurringProfiles,
  type RecurringListFilter,
} from "../../composables/useInvoicingRecurringProfiles";
import { isInvoicingLocalFirst } from "../../evolu/flags";

const { t, locale } = useI18n();
const localFirst = isInvoicingLocalFirst();
const { companyId, rememberCompany } = useInvoicingLayout();
const { companyName: summaryCompanyName } = useInvoicingCompanySummary();
const invoicingRecurring = useInvoicingRecurringProfiles(companyId);

const error = ref("");
const companyName = ref("");
const activeFilter = ref<RecurringListFilter>("all");

const loading = computed(() => invoicingRecurring.loading.value);
const profiles = computed(() => invoicingRecurring.profiles.value);

const filters = computed(() => [
  { id: "all" as const, label: t("invoicing.filter_all") },
  { id: "active" as const, label: t("invoicing.recurring_filter_active") },
  { id: "inactive" as const, label: t("invoicing.recurring_filter_inactive") },
]);

function formatMoney(n: string | number) {
  return Number(n || 0).toLocaleString(locale.value, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDate(iso: string) {
  const d = new Date(`${iso.slice(0, 10)}T12:00:00`);
  return d.toLocaleDateString(locale.value);
}

function intervalLabel(interval: string) {
  const key =
    interval === "monthly"
      ? "invoicing.recurring_interval_monthly"
      : "invoicing.recurring_interval_yearly";
  return t(key);
}

function profileLabel(p: { title?: string | null; document_type?: string }) {
  if (p.title) return p.title;
  return p.document_type === "proforma"
    ? t("invoicing.proforma_title_prefix") + " #INVOICE_NUMBER#"
    : t("invoicing.invoice_title_prefix") + " #INVOICE_NUMBER#";
}

function setFilter(id: RecurringListFilter) {
  activeFilter.value = id;
  void load();
}

async function load() {
  error.value = "";
  try {
    if (localFirst) {
      companyName.value = summaryCompanyName.value;
      await invoicingRecurring.refresh(activeFilter.value);
      return;
    }
    const [companyRes] = await Promise.all([
      api.get(`/invoicing/companies/${companyId.value}/summary`),
      invoicingRecurring.refresh(activeFilter.value),
    ]);
    companyName.value =
      companyRes.data.data?.trade_name ||
      companyRes.data.data?.legal_name ||
      "";
  } catch (e: any) {
    error.value = e?.response?.data?.message || t("common.error");
  }
}

onMounted(() => {
  rememberCompany(companyId.value);
  void load();
});
</script>
