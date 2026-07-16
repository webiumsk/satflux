<template>
  <InvoicingPageShell>
    <template #header>
      <InvoicingAppHeader :show-filter-bar="false" />
    </template>
    <template #toolbar>
      <RouterLink :to="contactListTo()" class="invoicing-back mb-0">
        ← {{ t('invoicing.contacts_title') }}
      </RouterLink>
    </template>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-2">
      <h1 class="invoicing-title">{{ contact?.name || t('common.loading') }}</h1>
      <div class="flex flex-wrap gap-2">
        <RouterLink
          :to="issueInvoiceTo(contactId)"
          class="invoicing-btn-secondary text-sm"
        >
          {{ t('invoicing.action_issue') }}
        </RouterLink>
        <RouterLink :to="contactEditTo(contactId)" class="invoicing-btn-secondary text-sm">
          {{ t('invoicing.action_change') }}
        </RouterLink>
        <button
          type="button"
          class="invoicing-btn-secondary text-sm disabled:opacity-40"
          :disabled="!canDelete || deleting"
          @click="removeContact"
        >
          {{ t('common.delete') }}
        </button>
      </div>
    </div>

    <nav class="invoicing-tabs">
      <button
        type="button"
        class="invoicing-tab"
        :class="activeTab === 'billing' ? 'invoicing-tab--active' : ''"
        @click="activeTab = 'billing'"
      >
        {{ t('invoicing.tab_billing') }}
      </button>
      <button
        type="button"
        class="invoicing-tab"
        :class="activeTab === 'defaults' ? 'invoicing-tab--active' : ''"
        @click="activeTab = 'defaults'"
      >
        {{ t('invoicing.tab_defaults') }}
      </button>
      <RouterLink
        :to="contactDocumentsTo()"
        class="invoicing-tab"
      >
        {{ documentsTabLabel }}
      </RouterLink>
      <span class="invoicing-tab invoicing-tab--disabled">{{ t('invoicing.tab_history') }}</span>
    </nav>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else class="invoicing-card-pad">
      <div
        v-if="contactStats && (contactStats.invoiced_count > 0 || contactStats.overdue_count > 0)"
        class="mb-6 grid sm:grid-cols-3 gap-4 text-sm"
      >
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
          <span class="text-xs text-gray-500 block">{{ t('invoicing.col_invoiced') }}</span>
          <span class="font-semibold">{{ formatOverduePair(contactStats.invoiced_total, contactStats.invoiced_count, 0, 0).main }}</span>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
          <span class="text-xs text-gray-500 block">{{ t('invoicing.col_overdue_sub') }}</span>
          <span
            class="font-semibold"
            :class="contactStats.overdue_count > 0 ? 'text-red-600' : 'text-gray-600'"
          >
            {{ formatOverduePair(0, 0, contactStats.overdue_total, contactStats.overdue_count).sub }}
          </span>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
          <span class="text-xs text-gray-500 block">{{ t('invoicing.col_avg_payment') }}</span>
          <span class="font-semibold">
            <template v-if="contactStats.avg_payment_days != null">
              {{ contactStats.avg_payment_days }} {{ t('invoicing.days_suffix') }}
            </template>
            <template v-else>-</template>
          </span>
        </div>
      </div>

      <ContactFormFields v-model="form" readonly :active-tab="activeTab" :company="companyProfile" />

      <p class="text-center text-xs text-gray-500 mt-8">{{ t('invoicing.contact_changes_note') }}</p>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { toAppRows } from "../../evolu/queryLoad";
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import ContactFormFields from '../../components/invoicing/ContactFormFields.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useInvoicingCompany } from '../../composables/useInvoicingCompany';
import { useInvoicingContact, useInvoicingContacts } from '../../composables/useInvoicingContacts';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import {
  contactToForm,
  emptyContactForm,
  formatOverduePair,
  useContactRoutes,
  type ContactFormState,
} from '../../composables/useCompanyContact';
import { isContactReferencedByIssuedOrPaid } from '../../evolu/contactBulkLocal';
import { computeContactStats } from '../../evolu/contactStatsLocal';
import type { EvoluDocumentRow } from '../../evolu/documentMap';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { companyId, rememberCompany } = useInvoicingLayout();
const contactId = computed(() => route.params.contactId as string);
const { contactListTo, contactEditTo, issueInvoiceTo } = useContactRoutes(companyId);

function contactDocumentsTo() {
  return {
    name: 'invoicing-invoices',
    params: { companyId: companyId.value },
    query: { contact_id: contactId.value },
  };
}

const { company: companyProfile } = useInvoicingCompany(companyId);
const { localFirst, contact, loading, refresh, remove } = useInvoicingContact(companyId, contactId);
const { documentRows } = useInvoicingContacts(companyId);

const contactDocumentCount = computed(() => {
  if (!localFirst || !contactId.value) return 0;
  return toAppRows<EvoluDocumentRow>(documentRows.value).filter(
    (row) => row.contactId === contactId.value,
  ).length;
});

const documentsTabLabel = computed(() => {
  const label = t('invoicing.tab_documents');
  return contactDocumentCount.value > 0 ? `${label} (${contactDocumentCount.value})` : label;
});

const form = ref<ContactFormState>(emptyContactForm());
const activeTab = ref<'billing' | 'defaults'>('billing');
const deleting = ref(false);

const contactStats = computed(() => {
  if (localFirst) {
    if (!contactId.value) return null;
    return computeContactStats(
      contactId.value,
      toAppRows<EvoluDocumentRow>(documentRows.value),
    );
  }
  return contact.value?.stats ?? null;
});

const canDelete = computed(() => {
  if (localFirst) {
    if (!contactId.value) return false;
    return !isContactReferencedByIssuedOrPaid(
      contactId.value,
      toAppRows<EvoluDocumentRow>(documentRows.value),
    );
  }
  return (contact.value?.stats?.invoiced_count ?? 0) === 0;
});

watch(contact, (row) => {
  if (!row) return;
  form.value = contactToForm(row);
});

async function removeContact() {
  if (!canDelete.value) return;
  if (!window.confirm(t('invoicing.confirm_delete_contact'))) return;
  deleting.value = true;
  try {
    const ok = await remove();
    if (ok) {
      await router.push(contactListTo());
    }
  } finally {
    deleting.value = false;
  }
}

watch(() => route.params.contactId, () => void refresh());
onMounted(async () => {
  rememberCompany(companyId.value);
  await refresh();
});
</script>
