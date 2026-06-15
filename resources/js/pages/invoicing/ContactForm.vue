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
      <h1 class="invoicing-title">
        {{ isNew ? t('invoicing.new_contact') : contactName || t('invoicing.edit_contact') }}
      </h1>
      <button
        type="button"
        class="invoicing-btn-primary"
        :disabled="saving"
        @click="save"
      >
        {{ t('common.save') }}
      </button>
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

    <form class="invoicing-card-pad" @submit.prevent="save">
      <ContactFormFields v-model="form" :active-tab="activeTab" :company="company" />

      <p class="text-center text-xs text-gray-500 mt-8">{{ t('invoicing.contact_changes_note') }}</p>

      <div class="flex justify-center mt-4">
        <button type="submit" class="invoicing-btn-primary px-8" :disabled="saving">
          {{ t('common.save') }}
        </button>
      </div>
      <p v-if="error" class="text-sm text-red-600 text-center mt-3">{{ error }}</p>
    </form>
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import ContactFormFields from '../../components/invoicing/ContactFormFields.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useInvoicingCompany, asCompanyId } from '../../composables/useInvoicingCompany';
import { useInvoicingContact } from '../../composables/useInvoicingContacts';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import {
  contactToForm,
  emptyContactForm,
  formToPayload,
  useContactRoutes,
  type ContactFormState,
} from '../../composables/useCompanyContact';
import { isInvoicingLocalFirst } from '../../evolu/flags';
import { useInvoicingEvolu } from '../../evolu/client';
import {
  insertLocalContactFromForm,
  updateLocalContactFromForm,
} from '../../evolu/contactCrud';
import type { ContactId } from '../../evolu/schema';
import api from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { companyId, rememberCompany } = useInvoicingLayout();
const contactId = computed(() => route.params.contactId as string | undefined);
const isNew = computed(() => route.name === 'invoicing-contact-new');
const { contactListTo, contactShowTo } = useContactRoutes(companyId);
const localFirst = isInvoicingLocalFirst();
const evolu = localFirst ? useInvoicingEvolu() : null;

const { company } = useInvoicingCompany(companyId);
const { contact, refresh: refreshContact } = useInvoicingContact(companyId, contactId);

const form = ref<ContactFormState>(emptyContactForm());
const activeTab = ref<'billing' | 'defaults'>('billing');
const saving = ref(false);
const error = ref('');
const contactName = ref('');

const showDelivery = computed(
  () =>
    Boolean(
      form.value.delivery_street ||
        form.value.delivery_postal_code ||
        form.value.delivery_city ||
        form.value.delivery_country
    )
);

watch(contact, (row) => {
  if (!row || isNew.value) return;
  contactName.value = row.name;
  form.value = contactToForm(row);
});

async function loadContact() {
  if (isNew.value) return;
  await refreshContact();
}

async function save() {
  error.value = '';
  if (!form.value.name.trim()) {
    error.value = t('validation.required');
    return;
  }
  saving.value = true;
  try {
    if (localFirst && evolu) {
      const result = isNew.value
        ? insertLocalContactFromForm(
            evolu,
            asCompanyId(companyId.value),
            form.value,
            showDelivery.value,
          )
        : updateLocalContactFromForm(
            evolu,
            contactId.value as ContactId,
            form.value,
            showDelivery.value,
          );
      if (!result.ok) {
        error.value = t('invoicing.company_save_validation_error');
        return;
      }
      const savedId = isNew.value && "value" in result ? result.value.id : contactId.value!;
      await router.push(contactShowTo(savedId));
      return;
    }

    const payload = formToPayload(form.value, showDelivery.value);
    if (isNew.value) {
      const res = await api.post(`/invoicing/companies/${companyId.value}/contacts`, payload);
      await router.push(contactShowTo(res.data.data.id));
    } else {
      await api.patch(`/invoicing/companies/${companyId.value}/contacts/${contactId.value}`, payload);
      await router.push(contactShowTo(contactId.value!));
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('errors.generic');
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  rememberCompany(companyId.value);
  await loadContact();
});
</script>
