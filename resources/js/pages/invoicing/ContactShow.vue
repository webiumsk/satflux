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
        <RouterLink :to="issueInvoiceTo(contactId)" class="invoicing-btn-secondary text-sm">
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
      <span class="invoicing-tab invoicing-tab--disabled">{{ t('invoicing.tab_documents') }}</span>
      <span class="invoicing-tab invoicing-tab--disabled">{{ t('invoicing.tab_history') }}</span>
    </nav>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else class="invoicing-card-pad">
      <div v-if="contact?.stats && (contact.stats.invoiced_count > 0 || contact.stats.overdue_count > 0)" class="mb-6 grid sm:grid-cols-3 gap-4 text-sm">
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
          <span class="text-xs text-gray-500 block">{{ t('invoicing.col_invoiced') }}</span>
          <span class="font-semibold">{{ formatOverduePair(contact.stats.invoiced_total, contact.stats.invoiced_count, 0, 0).main }}</span>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
          <span class="text-xs text-gray-500 block">{{ t('invoicing.col_overdue_sub') }}</span>
          <span
            class="font-semibold"
            :class="contact.stats.overdue_count > 0 ? 'text-red-600' : 'text-gray-600'"
          >
            {{ formatOverduePair(0, 0, contact.stats.overdue_total, contact.stats.overdue_count).sub }}
          </span>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
          <span class="text-xs text-gray-500 block">{{ t('invoicing.col_avg_payment') }}</span>
          <span class="font-semibold">
            <template v-if="contact.stats.avg_payment_days != null">
              {{ contact.stats.avg_payment_days }} {{ t('invoicing.days_suffix') }}
            </template>
            <template v-else>—</template>
          </span>
        </div>
      </div>

      <ContactFormFields v-model="form" readonly :active-tab="activeTab" :company="companyProfile" />

      <p class="text-center text-xs text-gray-500 mt-8">{{ t('invoicing.contact_changes_note') }}</p>
    </div>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import ContactFormFields from '../../components/invoicing/ContactFormFields.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import {
  contactToForm,
  emptyContactForm,
  formatOverduePair,
  useContactRoutes,
  type CompanyContactRow,
  type ContactFormState,
} from '../../composables/useCompanyContact';
import api from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { companyId, rememberCompany } = useInvoicingLayout();
const contactId = computed(() => route.params.contactId as string);
const { contactListTo, contactEditTo, issueInvoiceTo } = useContactRoutes(companyId);

const contact = ref<CompanyContactRow | null>(null);
const companyProfile = ref<Record<string, unknown> | null>(null);
const form = ref<ContactFormState>(emptyContactForm());
const activeTab = ref<'billing' | 'defaults'>('billing');
const loading = ref(true);
const deleting = ref(false);

const canDelete = computed(() => (contact.value?.stats?.invoiced_count ?? 0) === 0);

async function load() {
  loading.value = true;
  try {
    const res = await api.get(`/invoicing/companies/${companyId.value}/contacts/${contactId.value}`);
    contact.value = res.data.data;
    form.value = contactToForm(contact.value);
  } finally {
    loading.value = false;
  }
}

async function removeContact() {
  if (!canDelete.value) return;
  if (!window.confirm(t('invoicing.confirm_delete_contact'))) return;
  deleting.value = true;
  try {
    await api.delete(`/invoicing/companies/${companyId.value}/contacts/${contactId.value}`);
    await router.push(contactListTo());
  } finally {
    deleting.value = false;
  }
}

async function loadCompany() {
  const res = await api.get(`/invoicing/companies/${companyId.value}`);
  companyProfile.value = res.data.data;
}

watch(() => route.params.contactId, load);
onMounted(async () => {
  rememberCompany(companyId.value);
  await Promise.all([loadCompany(), load()]);
});
</script>
