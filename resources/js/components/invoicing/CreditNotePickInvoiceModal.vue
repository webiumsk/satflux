<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
    role="dialog"
    aria-modal="true"
    @click.self="$emit('close')"
  >
    <div
      class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden"
    >
      <div
        class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between shrink-0"
      >
        <h2 class="text-lg font-semibold">
          {{ t("invoicing.credit_note_pick_invoice_title") }}
        </h2>
        <button
          type="button"
          class="text-white/80 hover:text-white text-2xl leading-none"
          @click="$emit('close')"
        >
          ×
        </button>
      </div>

      <div
        class="px-4 py-3 border-b border-gray-200 flex flex-wrap items-center gap-2 shrink-0"
      >
        <button
          v-for="f in statusFilters"
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
        <span class="text-xs text-gray-500 ml-2">{{ filterStatusLabel }}</span>
      </div>

      <div v-if="loading" class="p-8 text-center text-gray-500">
        {{ t("common.loading") }}
      </div>

      <div
        v-else-if="invoices.length === 0"
        class="p-8 text-center text-gray-600"
      >
        {{ t("invoicing.credit_note_no_invoices") }}
      </div>

      <div v-else class="overflow-auto flex-1">
        <table class="w-full text-sm text-left">
          <thead
            class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide sticky top-0"
          >
            <tr>
              <th class="px-4 py-3">{{ t("invoicing.col_number") }}</th>
              <th class="px-4 py-3">{{ t("invoicing.col_title") }}</th>
              <th class="px-4 py-3">{{ t("invoicing.col_client") }}</th>
              <th class="px-4 py-3 text-right">
                {{ t("invoicing.col_total") }}
              </th>
              <th class="px-4 py-3 text-right">
                {{ t("invoicing.col_dates") }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="inv in invoices"
              :key="inv.id"
              class="border-t border-gray-100 cursor-pointer hover:bg-indigo-50 transition-colors"
              :class="selectedId === inv.id ? 'bg-indigo-100' : ''"
              @click="selectedId = inv.id"
              @dblclick="confirmSelect(inv)"
            >
              <td class="px-4 py-3 font-semibold text-indigo-700">
                {{ inv.number || t("invoicing.draft_label") }}
                <p
                  v-if="inv.variable_symbol"
                  class="text-xs text-gray-500 font-normal"
                >
                  VS: {{ inv.variable_symbol }}
                </p>
              </td>
              <td class="px-4 py-3 text-gray-700">{{ invoiceTitle(inv) }}</td>
              <td class="px-4 py-3 text-gray-700">
                {{ inv.contact?.name || "-" }}
              </td>
              <td class="px-4 py-3 text-right font-medium whitespace-nowrap">
                {{ formatMoney(inv.total, inv.currency) }}
              </td>
              <td
                class="px-4 py-3 text-right text-xs text-gray-600 whitespace-nowrap"
              >
                {{ formatDate(inv.issue_date) }}
                <span class="mx-1">/</span>
                {{ formatDate(inv.due_date) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        class="px-4 py-3 border-t border-gray-200 flex items-center justify-end gap-3 shrink-0"
      >
        <p v-if="error" class="text-sm text-red-600 mr-auto">{{ error }}</p>
        <button
          type="button"
          class="invoicing-btn-secondary"
          @click="$emit('close')"
        >
          {{ t("common.cancel") }}
        </button>
        <button
          type="button"
          class="invoicing-btn-primary"
          :disabled="!selectedId || creating"
          @click="confirmSelected"
        >
          {{
            creating
              ? t("common.loading")
              : t("invoicing.credit_note_use_invoice")
          }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { invoicingApi } from "../../services/api";
import {
  resolveIssuePeriodRange,
  defaultIssuePeriodState,
} from "../../composables/useInvoicingIssuePeriod";
import { isInvoicingLocalFirst } from "../../evolu/flags";
import { useLocalInvoiceDocumentSupport } from "../../composables/useLocalInvoiceDocument";
import { useCompanyVatPolicy } from "../../composables/useCompanyVatPolicy";
import { createLocalCreditNoteFromInvoice } from "../../evolu/documentCrud";
import type { DocumentId } from "../../evolu/schema";
import type {
  EvoluDocumentRow,
  EvoluDocumentLineRow,
} from "../../evolu/documentMap";
import { evoluContactToApi } from "../../evolu/contactMap";

const props = defineProps<{
  open: boolean;
  companyId: string;
}>();

const emit = defineEmits<{
  close: [];
  selected: [documentId: string];
}>();

const { t, locale } = useI18n();
const localFirst = isInvoicingLocalFirst();
const localDoc = localFirst ? useLocalInvoiceDocumentSupport() : null;
const vatPolicy = useCompanyVatPolicy();

const loading = ref(false);
const creating = ref(false);
const error = ref("");
const invoices = ref<any[]>([]);
const selectedId = ref<string | null>(null);
const activeFilter = ref("paid");

const issuePeriod = defaultIssuePeriodState();

const statusFilters = computed(() => [
  { id: "all", label: t("invoicing.filter_all") },
  { id: "paid", label: t("invoicing.filter_paid") },
  { id: "unpaid", label: t("invoicing.filter_unpaid") },
  { id: "overdue", label: t("invoicing.filter_overdue") },
]);

const filterStatusLabel = computed(() => {
  const f = statusFilters.value.find((x) => x.id === activeFilter.value);
  return f ? `${t("invoicing.adv_status")}: ${f.label}` : "";
});

function setFilter(id: string) {
  activeFilter.value = id;
  loadInvoices();
}

function isOverdue(inv: { status: string; due_date?: string }) {
  if (inv.status !== "issued" || !inv.due_date) return false;
  return new Date(`${inv.due_date}T23:59:59`) < new Date();
}

function matchesIssuePeriod(inv: { issue_date?: string }) {
  const range = resolveIssuePeriodRange(issuePeriod);
  if (!inv.issue_date) return true;
  const issue = inv.issue_date.slice(0, 10);
  if (range.from && issue < range.from) return false;
  if (range.to && issue > range.to) return false;
  return true;
}

function matchesStatusFilter(inv: { status: string; due_date?: string }) {
  if (activeFilter.value === "paid") return inv.status === "paid";
  if (activeFilter.value === "unpaid")
    return inv.status === "issued" && !isOverdue(inv);
  if (activeFilter.value === "overdue")
    return inv.status === "issued" && isOverdue(inv);
  return true;
}

async function loadLocalInvoices() {
  if (!localDoc) return;
  await localDoc.refreshAll();
  const rows = localDoc.documentRows.value.filter(
    (d) =>
      d.companyId === props.companyId &&
      d.documentType === "invoice" &&
      d.status !== "cancelled" &&
      d.status !== "draft" &&
      d.number,
  );

  invoices.value = rows
    .filter((row) =>
      matchesIssuePeriod({ issue_date: row.issueDate ?? undefined }),
    )
    .filter((row) =>
      matchesStatusFilter({
        status: row.status,
        due_date: row.dueDate ?? undefined,
      }),
    )
    .map((row) => {
      const apiDoc = localDoc.documentApi(row.id as DocumentId);
      if (!apiDoc) return null;
      const contactRow = row.contactId
        ? localDoc.contactRows.value.find((c) => c.id === row.contactId)
        : null;
      return {
        ...apiDoc,
        contact: contactRow ? evoluContactToApi(contactRow) : null,
      };
    })
    .filter((inv): inv is NonNullable<typeof inv> => inv != null);
}

async function loadInvoices() {
  if (!props.companyId) return;
  loading.value = true;
  error.value = "";
  try {
    if (localFirst && localDoc) {
      await loadLocalInvoices();
      return;
    }

    const range = resolveIssuePeriodRange(issuePeriod);
    const params: Record<string, unknown> = {
      type: "invoice",
      filter: activeFilter.value,
      per_page: 50,
      page: 1,
    };
    if (range.from) params.issue_from = range.from;
    if (range.to) params.issue_to = range.to;

    const docs = await invoicingApi.documents.list<{ status: string; number?: string }>(props.companyId, params);
    invoices.value = docs.filter(
      (d: { status: string; number?: string }) =>
        d.status !== "cancelled" && d.status !== "draft",
    );
  } catch (e: any) {
    error.value = e?.response?.data?.message || t("common.error");
    invoices.value = [];
  } finally {
    loading.value = false;
  }
}

function localSaveOptions(contactId: string | null) {
  const company = localDoc!.companyApi(props.companyId);
  const contact = contactId
    ? (localDoc!
        .contactsForCompany(props.companyId)
        .find((c) => c.id === contactId) ?? null)
    : null;
  const defaultVat = Number(company?.vat_rate_default ?? 23);
  return localDoc!.saveOptions(
    defaultVat,
    () => vatPolicy.calculatesVatAmounts(company),
    (line) => vatPolicy.resolveLineTaxRate(company, contact, line.tax_rate),
  );
}

const creditNoteErrors: Record<string, string> = {
  not_found: "common.error",
  not_invoice: "common.error",
  cancelled: "common.error",
  not_issued: "common.error",
  no_number: "common.error",
};

async function createLocalCreditNote(invoiceId: string) {
  if (!localDoc) return null;
  await localDoc.refreshAll();
  const invoiceRow = localDoc.documentRows.value.find(
    (d) => d.id === invoiceId,
  );
  const result = createLocalCreditNoteFromInvoice(
    localDoc.evolu,
    invoiceId as DocumentId,
    localDoc.documentRows.value as EvoluDocumentRow[],
    localDoc.lineRows.value as EvoluDocumentLineRow[],
    localSaveOptions(invoiceRow?.contactId ?? null),
    invoiceRow?.number
      ? `${t("invoicing.linked_credited_invoice", { number: invoiceRow.number })}.`
      : undefined,
  );
  if (!result.ok) {
    const key = creditNoteErrors[result.error] ?? "common.error";
    error.value = t(key);
    return null;
  }
  return result.value.id;
}

function invoiceTitle(inv: { title?: string; number?: string }) {
  if (inv.title) return inv.title;
  if (inv.number) return `${t("invoicing.invoice_title_prefix")} ${inv.number}`;
  return t("invoicing.draft_label");
}

function formatMoney(n: number | string, currency?: string) {
  return `${Number(n || 0).toLocaleString(locale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency || "EUR"}`;
}

function formatDate(iso?: string) {
  if (!iso) return "-";
  return new Date(
    iso.includes("T") ? iso : `${iso}T12:00:00`,
  ).toLocaleDateString(locale.value);
}

async function confirmSelect(inv: { id: string }) {
  selectedId.value = inv.id;
  await confirmSelected();
}

async function confirmSelected() {
  if (!selectedId.value) return;
  creating.value = true;
  error.value = "";
  try {
    if (localFirst && localDoc) {
      const docId = await createLocalCreditNote(selectedId.value);
      if (docId) emit("selected", docId);
      return;
    }

    const created = await invoicingApi.documents.creditNoteFromInvoice<{ id: string }>(props.companyId, selectedId.value);
    emit("selected", created.id);
  } catch (e: any) {
    error.value =
      e?.response?.data?.message ||
      e?.response?.data?.errors?.invoice_id?.[0] ||
      t("common.error");
  } finally {
    creating.value = false;
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      selectedId.value = null;
      activeFilter.value = "paid";
      loadInvoices();
    }
  },
);
</script>
