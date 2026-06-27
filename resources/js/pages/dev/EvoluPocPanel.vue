<template>
  <div class="min-h-0 flex-1 max-md:flex-none max-md:overflow-visible md:overflow-y-auto overscroll-y-contain custom-scrollbar">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-8 space-y-6">
      <header>
        <p class="text-xs font-medium uppercase tracking-wide text-amber-400/90">{{ t("evolu.poc_badge") }}</p>
        <h1 class="text-2xl font-bold text-white mt-1">{{ t("evolu.poc_title") }}</h1>
        <p class="mt-2 text-sm text-gray-400">{{ t("evolu.poc_subtitle") }}</p>
      </header>

      <section class="rounded-2xl border border-gray-800 bg-gray-900/80 p-5 space-y-3">
        <h2 class="text-sm font-semibold text-white">{{ t("evolu.sync_heading") }}</h2>
        <dl class="grid gap-2 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-gray-500">{{ t("evolu.relay_label") }}</dt>
            <dd class="font-mono text-xs text-gray-300 break-all">{{ relayUrl }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">{{ t("evolu.sync_state_label") }}</dt>
            <dd class="text-gray-200">{{ syncStateLabel }}</dd>
          </div>
        </dl>
      </section>

      <section class="rounded-2xl border border-gray-800 bg-gray-900/80 p-5 space-y-4">
        <h2 class="text-sm font-semibold text-white">{{ t("evolu.owner_heading") }}</h2>
        <p class="text-sm text-gray-400">{{ t("evolu.owner_hint") }}</p>
        <div v-if="ownerMnemonic" class="rounded-lg border border-amber-500/30 bg-amber-950/30 p-3">
          <p class="text-xs text-amber-200/80 mb-2">{{ t("evolu.mnemonic_warning") }}</p>
          <p class="font-mono text-sm text-amber-100 break-words select-all">{{ ownerMnemonic }}</p>
        </div>
        <div v-else class="text-sm text-gray-500">{{ t("common.loading") }}</div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="invoicing-btn-secondary text-sm" @click="onCopyMnemonic">
            {{ t("evolu.copy_mnemonic") }}
          </button>
          <button type="button" class="invoicing-btn-secondary text-sm" @click="onRestoreMnemonic">
            {{ t("evolu.restore_mnemonic") }}
          </button>
          <button type="button" class="text-sm text-red-400 hover:text-red-300 underline" @click="onResetOwner">
            {{ t("evolu.reset_owner") }}
          </button>
        </div>
      </section>

      <section class="rounded-2xl border border-gray-800 bg-gray-900/80 p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-white">{{ t("evolu.companies_heading") }}</h2>
          <button type="button" class="invoicing-btn-primary text-sm" @click="createCompany">
            + {{ t("evolu.add_company") }}
          </button>
        </div>
        <ul v-if="companies.length" class="space-y-2">
          <li
            v-for="company in companies"
            :key="company.id"
            class="flex items-center justify-between gap-3 rounded-lg border border-gray-800 bg-gray-950/50 px-3 py-2"
          >
            <div>
              <p class="text-sm font-medium text-white">{{ company.tradeName || company.legalName }}</p>
              <p v-if="company.tradeName" class="text-xs text-gray-500">{{ company.legalName }}</p>
            </div>
            <button
              type="button"
              class="text-xs text-red-400 hover:text-red-300"
              @click="deleteCompany(company.id)"
            >
              {{ t("common.delete") }}
            </button>
          </li>
        </ul>
        <p v-else class="text-sm text-gray-500">{{ t("evolu.no_companies") }}</p>
      </section>

      <section class="rounded-2xl border border-gray-800 bg-gray-900/80 p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-white">{{ t("evolu.contacts_heading") }}</h2>
          <button
            type="button"
            class="invoicing-btn-primary text-sm"
            :disabled="!companies.length"
            @click="createContact"
          >
            + {{ t("evolu.add_contact") }}
          </button>
        </div>
        <ul v-if="contacts.length" class="space-y-2">
          <li
            v-for="contact in contacts"
            :key="contact.id"
            class="flex items-center justify-between gap-3 rounded-lg border border-gray-800 bg-gray-950/50 px-3 py-2"
          >
            <div>
              <p class="text-sm font-medium text-white">{{ contact.name }}</p>
              <p class="text-xs text-gray-500">{{ contactLabel(contact) }}</p>
            </div>
            <button
              type="button"
              class="text-xs text-red-400 hover:text-red-300"
              @click="deleteContact(contact.id)"
            >
              {{ t("common.delete") }}
            </button>
          </li>
        </ul>
        <p v-else class="text-sm text-gray-500">{{ t("evolu.no_contacts") }}</p>
      </section>

      <section class="rounded-2xl border border-gray-800 bg-gray-900/80 p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-white">{{ t("evolu.documents_heading") }}</h2>
          <button
            type="button"
            class="invoicing-btn-primary text-sm"
            :disabled="!companies.length"
            @click="createDocument"
          >
            + {{ t("evolu.add_document") }}
          </button>
        </div>
        <ul v-if="documents.length" class="space-y-2">
          <li
            v-for="doc in documents"
            :key="doc.id"
            class="flex items-center justify-between gap-3 rounded-lg border border-gray-800 bg-gray-950/50 px-3 py-2"
          >
            <div>
              <p class="text-sm font-medium text-white">{{ doc.title }}</p>
              <p class="text-xs text-gray-500">{{ documentLabel(doc) }}</p>
            </div>
            <button
              type="button"
              class="text-xs text-red-400 hover:text-red-300"
              @click="deleteDocument(doc.id)"
            >
              {{ t("common.delete") }}
            </button>
          </li>
        </ul>
        <p v-else class="text-sm text-gray-500">{{ t("evolu.no_documents") }}</p>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import {
    NonEmptyString100,
    NonEmptyString1000,
    sqliteTrue,
} from "@evolu/common";
import { useQuery } from "@evolu/vue";
import {
    allCompaniesQuery,
    allContactsQuery,
    allDocumentsQuery,
    EVOLU_RELAY_URL,
    useInvoicingEvolu,
} from "@/evolu/client";
import { insertLocalCompanyFromPayload } from "@/evolu/companyInsert";
import { seedDefaultNumberSeries } from "@/evolu/numberSeriesCrud";
import { getStoredAccountMnemonic, initEvoluFromAccountSeedIfNeeded } from "@/services/accountSeed";
import type { CompanyId, ContactId, DocumentId } from "@/evolu/schema";

const { t } = useI18n();
const evolu = useInvoicingEvolu();
const companies = useQuery(allCompaniesQuery);
const contacts = useQuery(allContactsQuery);
const documents = useQuery(allDocumentsQuery);

const relayUrl = EVOLU_RELAY_URL;
const ownerMnemonic = ref<string | null>(null);

onMounted(async () => {
    const owner = await evolu.appOwner;
    ownerMnemonic.value = owner.mnemonic;
});

// @evolu/vue useSyncState() is not implemented yet (throws TODO in 1.4.0).
const syncStateLabel = computed(() => t("evolu.sync_unavailable"));

function companyNameById(id: CompanyId): string {
    const row = companies.value.find((c) => c.id === id);
    return row?.tradeName || row?.legalName || id;
}

function contactLabel(contact: { companyId: CompanyId; name: string; email: string | null }): string {
    const parts = [companyNameById(contact.companyId), contact.name];
    if (contact.email) {
        parts.push(contact.email);
    }
    return parts.join(" · ");
}

function documentLabel(doc: { companyId: CompanyId; status: string }): string {
    return `${companyNameById(doc.companyId)} · ${doc.status}`;
}

function createCompany(): void {
    const legalName = window.prompt(t("evolu.prompt_company_legal_name"));
    if (legalName == null) return;
    const result = insertLocalCompanyFromPayload(evolu, {
        legal_name: legalName,
        store_id: null,
        jurisdiction: "eu_sk",
        default_currency: "EUR",
        registration_number: null,
        tax_id: null,
        street: null,
        city: null,
        postal_code: null,
        state_region: null,
        country: "SK",
        bank_name: null,
        bank_account: null,
        bank_code: null,
        iban: null,
        bic: null,
        vat_number: null,
        commercial_register: null,
        vat_payer: false,
        vat_status: "none",
    });
    if (!result.ok) {
        window.alert(t("evolu.validation_name"));
        return;
    }
    seedDefaultNumberSeries(evolu, result.value.id);
}

function createContact(): void {
    const company = companies.value[0];
    if (!company) return;
    const displayName = window.prompt(t("evolu.prompt_contact_name"));
    if (displayName == null) return;
    const parsed = NonEmptyString100.from(displayName.trim());
    if (!parsed.ok) {
        window.alert(t("evolu.validation_name"));
        return;
    }
    evolu.insert("contact", {
        companyId: company.id,
        name: parsed.value,
        registrationNumber: null,
        peppolParticipantId: null,
        email: null,
        phone: null,
        fax: null,
        taxId: null,
        vatId: null,
        street: null,
        city: null,
        postalCode: null,
        stateRegion: null,
        country: null,
        bankAccount: null,
        bankCode: null,
        iban: null,
        swift: null,
        deliveryStreet: null,
        deliveryPostalCode: null,
        deliveryCity: null,
        deliveryCountry: null,
        defaultPaymentTermsDays: null,
        notes: null,
        contactPersonsJson: null,
        isActive: sqliteTrue,
    });
}

function createDocument(): void {
    const company = companies.value[0];
    if (!company) return;
    const title = window.prompt(t("evolu.prompt_document_title"));
    if (title == null) return;
    const parsed = NonEmptyString1000.from(title.trim());
    if (!parsed.ok) {
        window.alert(t("evolu.validation_title"));
        return;
    }
    evolu.insert("document", {
        companyId: company.id,
        contactId: null,
        documentType: "invoice",
        title: parsed.value,
        number: null,
        status: "draft",
        currency: null,
        issueDate: null,
        total: null,
    });
}

function deleteCompany(id: CompanyId): void {
    evolu.update("company", { id, isDeleted: sqliteTrue });
}

function deleteContact(id: ContactId): void {
    evolu.update("contact", { id, isDeleted: sqliteTrue });
}

function deleteDocument(id: DocumentId): void {
    evolu.update("document", { id, isDeleted: sqliteTrue });
}

async function onCopyMnemonic(): Promise<void> {
    if (!ownerMnemonic.value) return;
    try {
        await navigator.clipboard.writeText(ownerMnemonic.value);
        window.alert(t("evolu.mnemonic_copied"));
    } catch {
        window.alert(t("evolu.mnemonic_copy_failed"));
    }
}

async function onRestoreMnemonic(): Promise<void> {
    const stored = getStoredAccountMnemonic();
    if (stored) {
        if (!window.confirm(t("evolu.restore_confirm"))) return;
        await initEvoluFromAccountSeedIfNeeded(stored);
        return;
    }
    const mnemonic = window.prompt(t("evolu.prompt_mnemonic"));
    if (mnemonic == null || !mnemonic.trim()) return;
    if (!window.confirm(t("evolu.restore_confirm"))) return;
    await initEvoluFromAccountSeedIfNeeded(mnemonic.trim());
}

async function onResetOwner(): Promise<void> {
    if (!window.confirm(t("evolu.reset_confirm"))) return;
    await evolu.resetAppOwner();
}
</script>
