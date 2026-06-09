<template>
  <InvoicingPageShell content-class="pb-8">
    <template #header>
      <InvoicingAppHeader :company-label="companyName">
        <template #filters>
          <input
            v-model="searchQuery"
            type="search"
            class="invoicing-sf-input max-w-xs min-w-[200px]"
            :placeholder="t('invoicing.search_contact')"
            @input="onSearchInput"
          />
        </template>
        <template #actions>
          <button type="button" class="invoicing-btn-secondary" @click="showImportModal = true">
            {{ t('invoicing.import_contacts') }}
          </button>
          <RouterLink :to="contactNewTo()" class="invoicing-btn-primary">
            + {{ t('invoicing.new_contact') }}
          </RouterLink>
        </template>
      </InvoicingAppHeader>
    </template>

    <div class="invoicing-letter-filter">
      <button
        type="button"
        class="invoicing-letter"
        :class="activeLetter === 'all' ? 'invoicing-letter--active' : 'invoicing-letter--idle'"
        @click="setLetter('all')"
      >
        {{ t('invoicing.letter_all') }}
      </button>
      <button
        v-for="ch in ALPHABET_FILTER"
        :key="ch"
        type="button"
        class="invoicing-letter"
        :class="letterClass(ch)"
        @click="setLetter(ch)"
      >
        {{ ch }}
      </button>
    </div>

    <p v-if="success" class="text-sm text-green-700 mb-4">{{ success }}</p>

    <div v-if="selectionCount > 0" class="invoicing-bulk-bar">
      <span class="text-sm text-indigo-800 font-medium">
        {{ t('invoicing.bulk_selected', { count: selectionCount }) }}
      </span>
      <div class="relative">
        <button type="button" class="invoicing-btn-secondary text-sm py-1.5" @click="showBulkMenu = !showBulkMenu">
          {{ t('invoicing.bulk_actions') }} ▾
        </button>
        <div v-if="showBulkMenu" class="invoicing-dropdown">
          <button type="button" class="invoicing-dropdown-item" @click="runBulk('export_xlsx')">
            {{ t('invoicing.bulk_export_xlsx') }}
          </button>
          <button type="button" class="invoicing-dropdown-item text-red-600" @click="runBulk('delete')">
            {{ t('invoicing.bulk_delete_contacts') }}
          </button>
          <div class="border-t border-gray-200 my-1"></div>
          <button type="button" class="invoicing-dropdown-item text-gray-500" @click="clearSelection">
            {{ t('invoicing.bulk_clear') }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="invoicing-muted py-8">{{ t('common.loading') }}</div>

    <div v-else-if="contacts.length === 0" class="invoicing-card-pad text-center text-gray-600">
      {{ t('invoicing.no_contacts') }}
    </div>

    <div v-else class="invoicing-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="contact-table w-full min-w-[800px] text-sm">
          <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 text-xs uppercase tracking-wide">
            <tr>
              <th class="w-12 px-2 py-3 relative text-center">
                <button
                  type="button"
                  class="text-gray-500 hover:text-gray-800 text-base leading-none"
                  :title="t('invoicing.select_menu')"
                  @click.stop="showSelectMenu = !showSelectMenu"
                >
                  ☰
                </button>
                <div
                  v-if="showSelectMenu"
                  class="invoicing-dropdown min-w-[240px] text-xs normal-case tracking-normal font-normal"
                >
                  <button type="button" class="invoicing-dropdown-item" @click="selectPage">
                    {{ t('invoicing.select_page', { count: contacts.length }) }}
                  </button>
                  <button type="button" class="invoicing-dropdown-item" @click="selectAllFiltered">
                    {{ t('invoicing.select_all_filtered', { count: contacts.length }) }}
                  </button>
                  <button
                    v-if="selectionCount > 0"
                    type="button"
                    class="invoicing-dropdown-item text-gray-500"
                    @click="clearSelection"
                  >
                    {{ t('invoicing.bulk_clear') }}
                  </button>
                </div>
              </th>
              <th class="min-w-[220px] text-left">{{ t('invoicing.col_contact_name') }}</th>
              <th class="min-w-[160px] text-right">
                <span class="block">{{ t('invoicing.col_invoiced') }}</span>
                <span class="block font-normal normal-case text-gray-500">{{ t('invoicing.col_overdue_sub') }}</span>
              </th>
              <th class="min-w-[140px] text-right">{{ t('invoicing.col_avg_payment') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in contacts"
              :key="c.id"
              class="bg-white hover:bg-gray-50"
              :class="rowSelected(c.id) ? 'bg-indigo-50' : ''"
            >
              <td class="px-2 py-3">
                <input
                  type="checkbox"
                  class="rounded border-gray-300"
                  :checked="rowSelected(c.id)"
                  @change="toggleRow(c.id)"
                />
              </td>
              <td>
                <RouterLink :to="contactShowTo(c.id)" class="text-indigo-600 hover:text-indigo-800 hover:underline font-medium">
                  {{ c.name }}
                </RouterLink>
              </td>
              <td class="text-right">
                <template v-if="statLine(c)">
                  <span class="invoicing-stat-main">{{ statLine(c).main }}</span>
                  <span
                    class="invoicing-stat-sub"
                    :class="statLine(c).subAlert ? 'invoicing-stat-sub--alert' : ''"
                  >
                    {{ statLine(c).sub }}
                  </span>
                </template>
              </td>
              <td class="text-right text-gray-700 whitespace-nowrap">
                <template v-if="c.stats?.avg_payment_days != null">
                  {{ c.stats.avg_payment_days }} {{ t('invoicing.days_suffix') }}
                </template>
                <span v-else class="text-gray-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <ContactImportModal
      :open="showImportModal"
      :company-id="companyId"
      @close="showImportModal = false"
      @imported="onContactsImported"
    />
  </InvoicingPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import ContactImportModal from '../../components/invoicing/ContactImportModal.vue';
import InvoicingAppHeader from '../../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../../components/invoicing/InvoicingPageShell.vue';
import { useInvoicingLayout } from '../../composables/useInvoicingLayout';
import {
  ALPHABET_FILTER,
  formatOverduePair,
  useContactRoutes,
  type CompanyContactRow,
} from '../../composables/useCompanyContact';
import api from '../../services/api';

const { t } = useI18n();
const route = useRoute();
const { companyId, rememberCompany } = useInvoicingLayout();
const { contactShowTo, contactNewTo } = useContactRoutes(companyId);
const companyName = ref('');

const contacts = ref<CompanyContactRow[]>([]);
const availableLetters = ref<string[]>([]);
const loading = ref(true);
const searchQuery = ref('');
const activeLetter = ref('all');
const selectedIds = ref(new Set<string>());
const selectAllMode = ref(false);
const showSelectMenu = ref(false);
const showBulkMenu = ref(false);
const showImportModal = ref(false);
const success = ref('');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const selectionCount = computed(() =>
  selectAllMode.value ? contacts.value.length : selectedIds.value.size
);

function onContactsImported() {
  load();
}

function listFilterParams(): Record<string, string | undefined> {
  return {
    q: searchQuery.value.trim() || undefined,
    letter: activeLetter.value === 'all' ? undefined : activeLetter.value,
  };
}

async function load() {
  loading.value = true;
  try {
    const companyRes = await api.get(`/invoicing/companies/${companyId.value}/summary`);
    companyName.value = companyRes.data.data?.trade_name || companyRes.data.data?.legal_name || '';

    const res = await api.get(`/invoicing/companies/${companyId.value}/contacts`, {
      params: listFilterParams(),
    });
    contacts.value = res.data.data ?? [];
    availableLetters.value = res.data.meta?.letters ?? [];
  } finally {
    loading.value = false;
  }
}

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    clearSelection();
    load();
  }, 300);
}

function setLetter(letter: string) {
  if (letter !== 'all' && !availableLetters.value.includes(letter)) return;
  activeLetter.value = letter;
  clearSelection();
  load();
}

function letterClass(ch: string) {
  if (activeLetter.value === ch) return 'invoicing-letter--active';
  if (!availableLetters.value.includes(ch)) return 'invoicing-letter--disabled';
  return 'invoicing-letter--idle';
}

function statLine(c: CompanyContactRow) {
  const s = c.stats;
  if (!s) return null;
  return formatOverduePair(
    s.invoiced_total,
    s.invoiced_count,
    s.overdue_total,
    s.overdue_count
  );
}

function rowSelected(id: string) {
  if (selectAllMode.value) return true;
  return selectedIds.value.has(id);
}

function toggleRow(id: string) {
  selectAllMode.value = false;
  const next = new Set(selectedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selectedIds.value = next;
}

function selectPage() {
  selectAllMode.value = false;
  const next = new Set(selectedIds.value);
  contacts.value.forEach((c) => next.add(c.id));
  selectedIds.value = next;
  showSelectMenu.value = false;
}

function selectAllFiltered() {
  selectAllMode.value = true;
  selectedIds.value = new Set();
  showSelectMenu.value = false;
}

function clearSelection() {
  selectAllMode.value = false;
  selectedIds.value = new Set();
  showBulkMenu.value = false;
  showSelectMenu.value = false;
}

function bulkPayload(action: string) {
  const base: Record<string, unknown> = {
    action,
    ...listFilterParams(),
  };
  if (selectAllMode.value) {
    base.select_all = true;
  } else {
    base.contact_ids = Array.from(selectedIds.value);
  }
  return base;
}

async function runBulk(action: 'export_xlsx' | 'delete') {
  if (selectionCount.value === 0) return;
  showBulkMenu.value = false;

  if (action === 'delete' && !window.confirm(t('invoicing.confirm_bulk_delete_contacts'))) {
    return;
  }

  success.value = '';
  loading.value = true;
  try {
    const isFile = action === 'export_xlsx';
    const res = await api.post(
      `/invoicing/companies/${companyId.value}/contacts/bulk`,
      bulkPayload(action),
      isFile ? { responseType: 'blob' } : {}
    );

    if (isFile) {
      const url = URL.createObjectURL(res.data as Blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'contacts.xlsx';
      a.click();
      URL.revokeObjectURL(url);
      clearSelection();
    } else {
      const data = res.data.data;
      success.value = t('invoicing.bulk_contact_delete_result', {
        deleted: data.deleted ?? 0,
        anonymized: data.anonymized ?? 0,
      });
      await load();
      clearSelection();
    }
  } catch (e: any) {
    if (e?.response?.data instanceof Blob) {
      const text = await e.response.data.text();
      try {
        const json = JSON.parse(text);
        alert(json.message || t('common.error'));
      } catch {
        alert(t('common.error'));
      }
    } else {
      alert(e?.response?.data?.message || t('common.error'));
    }
  } finally {
    loading.value = false;
  }
}

watch(
  () => route.params.companyId,
  () => {
    activeLetter.value = 'all';
    searchQuery.value = '';
    clearSelection();
    load();
  }
);

onMounted(() => {
  rememberCompany(companyId.value);
  load();
});
</script>
